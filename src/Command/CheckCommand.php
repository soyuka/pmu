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
use Pmu\Config;
use Pmu\Dependencies;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('check-dependencies')->setDescription('Checks the monorepo dependencies.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();
        $config = Config::create($composer);
        $repo = $composer->getRepositoryManager()->getLocalRepository();

        [
            'autoloadByProjects' => $autoloadByProjects,
            'dependenciesByProjects' => $dependenciesByProjects,
            'namespaces' => $namespaces,
            'classMap' => $classMap
        ] = Dependencies::collectProjectsData($config, $repo, computeClassMap: true, includeDev: true);

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
                    if (str_starts_with($useNs, $depNs)) {
                        $ok = true;
                        continue;
                    }
                }

                if (!$ok) {
                    $exitCode = 1;
                    $output->writeln(sprintf('Class "%s" uses "%s" but it is not declared as dependency.', $class, $useNs));
                }
            }
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
