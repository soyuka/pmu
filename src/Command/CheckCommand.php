<?php

/*
 * This file is part of the PMU project.
 *
 * (c) Antoine Bluchet <soyuka@pm.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pmu\Command;

use Composer\Command\BaseCommand;
use Composer\Console\Input\InputOption;
use Pmu\Composer\BaseDirTrait;
use Pmu\Config;
use Pmu\Dependencies;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckCommand extends BaseCommand
{
    use BaseDirTrait;

    protected function configure(): void
    {
        $this->setName('check-dependencies')
             ->setDescription('Checks the monorepo dependencies.')
            ->setDefinition([
                new InputOption('working-directory', 'wd', InputOption::VALUE_REQUIRED, "Defaults to SERVER['PWD']"),
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $wd = $input->getOption('working-directory') ?? $_SERVER['PWD'] ?? null;
        $autoload = $wd . '/vendor/autoload.php';

        if (!file_exists($autoload)) {
            $output->writeln(sprintf('No autoload at path "%s".', $autoload));
            return 1;
        }

        require_once $autoload;
        $composer = $this->requireComposer();
        $config = Config::create($composer);
        $repo = $composer->getRepositoryManager();

        [
            'autoloadByProjects' => $autoloadByProjects,
            'dependenciesByProjects' => $dependenciesByProjects,
            'namespaces' => $namespaces,
            'classMap' => $classMap
        ] = Dependencies::collectProjectsData($config, $repo, computeClassMap: true, includeDev: true);

        /** 
         * @var array<string, string[]>
         */
        $namespaceDependencies = [];

        // builds a map Namespace => Namespace[]
        foreach ($dependenciesByProjects as $project => $dependencies) {
            foreach ($autoloadByProjects[$project] as $ns) {
                if (!isset($namespaceDependencies[$ns])) {
                    $namespaceDependencies[$ns] = [];
                }

                foreach ($dependencies as $d) {
                    foreach ($autoloadByProjects[$d] as $dns) {
                        $namespaceDependencies[$ns][] = $dns;
                    }
                }
            }
        }

        $exitCode = 0;
        foreach ($classMap as $class => $file) {
            require_once $file;
            $r = new \ReflectionClass($class);

            $classNs = $r->getNamespaceName();
            if (!isset($namespaceDependencies[$classNs])) {
                $classNs = $classNs . '\\';
                if (!isset($namespaceDependencies[$classNs])) {
                    continue;
                }
            }

            foreach ($this->classUsesNamespaces($r, $namespaces) as $useNs) {
                $ok = false;
                foreach ($namespaceDependencies[$classNs] as $depNs) {
                    if (str_starts_with($useNs, $classNs) || str_starts_with($useNs, $depNs)) {
                        $ok = true;
                        continue;
                    }
                }

                $error = sprintf('Class "%s" uses "%s" but it is not declared as dependency.', $class, $useNs);

                if (isset($config->baseLine[$error])) {
                    continue;
                }

                if (!$ok) {
                    $exitCode = 1;
                    $output->writeln($error);
                }
            }
        }

        if ($exitCode === 0) {
            $output->writeln('All your projects dependencies are declared as "require" or "require_dev"');
        }

        return $exitCode;
    }

    /**
     * @param string[] $namespaces
     * @param \ReflectionClass<object> $r
     *
     * @return \Generator<int, string>
     */
    private function classUsesNamespaces(\ReflectionClass $r, array $namespaces): \Generator
    {
        $fileName = $r->getFileName();

        if (!$fileName) {
            return;
        }

        $fp = fopen($fileName, 'r');

        if (!$fp) {
            return;
        }

        $u = 'use';
        $c = false;
        while (($buffer = fgets($fp, 4096)) !== false) {
            if ($c && \PHP_EOL === $buffer) {
                break;
            }

            if (!str_starts_with($buffer, $u)) {
                continue;
            }

            $c = true;
            $buffer = substr($buffer, 4, -2);

            foreach ($namespaces as $namespace) {
                if (str_starts_with($buffer, $namespace)) {
                    yield substr($buffer, 0, strpos($buffer, ' ') ?: null);
                }
            }
        }

        fclose($fp);
    }
}
