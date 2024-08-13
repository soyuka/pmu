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
use Composer\Composer;
use Composer\Console\Input\InputArgument;
use Composer\Console\Input\InputOption;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Pmu\Config;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class BlendCommand extends BaseCommand
{
    private Composer $composer;
    private Config $config;

    protected function configure(): void
    {
        $this->setName('blend')
            ->setDefinition([
                new InputOption('dev', 'D', InputOption::VALUE_NONE, 'Blend dev requirements.'),
                new InputOption('json-path', null, InputOption::VALUE_REQUIRED, 'Json path to blend'),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Force'),
                new InputArgument('projects', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, ''),
            ])
            ->setDescription('Blend the mono-repository dependencies into each projects.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->composer = $this->requireComposer();
        $this->config = Config::create($this->composer);
        $requireKey = true === $input->getOption('dev') ? 'require-dev' : 'require';
        $packageAccessor = true === $input->getOption('dev') ? 'getDevRequires' : 'getRequires';

        if (is_string($input->getOption('json-path'))) {
            return $this->blendJsonPath($input, $output);
        }

        $projects = $this->getProjects($input);
        $repo = $this->composer->getRepositoryManager();
        $package = $this->composer->getPackage();
        foreach ($this->config->projects as $p) {
            if ($projects && !in_array($p, $projects, true)) {
                continue;
            }

            $projectPackage = $repo->findPackage($p, '*');
            if (!$projectPackage || !$projectPackage instanceof PackageInterface) {
                $output->writeln(sprintf('Package "%s" could not be found.', $p));
                return 1;
            }

            $dir = $projectPackage->getDistUrl();
            if (!is_string($dir) || !is_dir($dir)) {
                $output->writeln(sprintf('Package "%s" could not be found at path "%s".', $p, $dir));
                continue;
            }

            $packagesToUpdate = [];
            foreach ($package->{$packageAccessor}() as $g) {
                // Only update the package if it's found in the project's package
                if (!$input->getOption('force')) {
                    $hasPackage = false;
                    foreach ($projectPackage->{$packageAccessor}() as $r) {
                        if ($g->getTarget() === $r->getTarget()) {
                            $hasPackage = true;
                            break;
                        }
                    }

                    if (!$hasPackage) {
                        continue;
                    }
                }

                $packagesToUpdate[$g->getTarget()] = $g->getPrettyConstraint();
            }

            if (!$packagesToUpdate) {
                continue;
            }

            $json = new JsonFile($dir . '/composer.json');
            /** @var array{require: array<string, string>, 'require-dev': array<string, string>} */
            $composerDefinition = $json->read();
            foreach ($packagesToUpdate as $target => $constraint) {
                $composerDefinition[$requireKey][$target] = $constraint;
            }

            $json->write($composerDefinition);
        }

        return 0;
    }

    private function blendJsonPath(InputInterface $input, OutputInterface $output): int {
        /** @var string */
        $jsonPath = $input->getOption('json-path');
        $path = $this->composer->getConfig()->getConfigSource()->getName();
        $file = file_get_contents($path) ?: throw new \RuntimeException(sprintf('File "%s" not found.', $path));
        $data = json_decode($file, true) ?: throw new \RuntimeException(sprintf('File "%s" is not JSON.', $path));
		$pattern = '/(?<!\\\\)\./';  // Regex pattern to match a dot not preceded by a backslash
		$pointers = preg_split($pattern, $jsonPath);

        if (!$pointers) {
            $output->writeln('No pointers.');
            return 1;
        }

		foreach ($pointers as &$part) {
			$part = str_replace('\.', '.', $part);
		}

        $p = $pointers;
        $value = $data;
        while($pointer = array_shift($p)) {
            /** @var string $pointer */
            if (!is_array($value) || !isset($value[$pointer])) {
                $output->writeln(sprintf('Node "%s" not found.', $jsonPath));
                return 1;
            }

            $value = $value[$pointer];
        }

        $repo = $this->composer->getRepositoryManager();
        $projects = $this->getProjects($input);
        foreach ($this->config->projects as $project) {
            if ($projects && !in_array($project, $projects, true)) {
                continue;
            }

            $package = $repo->findPackage($project, '*');
            if (!$package || !$package instanceof PackageInterface) {
                $output->writeln(sprintf('Package "%s" could not be found.', $project));
                return 1;
            }

            $dir = $package->getDistUrl();
            if (!is_string($dir) || !is_dir($dir)) {
                $output->writeln(sprintf('Package "%s" could not be found at path "%s".', $project, $dir));
                continue;
            }
            
            $path = $dir . '/composer.json';
            $fileContent = file_get_contents($path) ?: throw new \RuntimeException(sprintf('File "%s" not found.', $path));
            $json = json_decode($fileContent, true) ?: throw new \RuntimeException(sprintf('File "%s" is not JSON.', $path));
            $force = $input->getOption('force');

            $p = $pointers;
            $ref =& $json;
            while($pointer = array_shift($p)) {
                if (!is_array($ref)) {
                    $output->writeln(sprintf('Package "%s" has no pointer "%s".', $project, $pointer));
                    break 2;
                }

                if (!isset($ref[$pointer])) {
                    if (!$force) {
                        $output->writeln(sprintf('Package "%s" has no pointer "%s".', $project, $pointer));
                        break 2;
                    }

                    $ref[$pointer] = [];
                }

                if (\count($p) === 0) {
                    $ref[$pointer] = $value;
                    break;
                }

                $ref = &$ref[$pointer];
            }

            unset($ref);
            $ref = $value;
            $fileContent = file_put_contents($path, json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

            if (!$fileContent) {
                $output->writeln(sprintf('Could not write JSON at path "%s".', $path));
            }
        }

        return 0;
    }

    /**
     * @return string[]|null
     */
    private function getProjects(InputInterface $input): ?array {
        if (is_array($p = $input->getArgument('projects')) && $p) {
            return array_map('strval', $p);
        }
        
        return null;
    }
}
