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
use Pmu\Composer\ReadJsonFileTrait;
use Pmu\Config;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class BlendCommand extends BaseCommand
{
    use ReadJsonFileTrait;
    private Composer $composer;
    private Config $config;

    protected function configure(): void
    {
        $this->setName('blend')
            ->setDefinition([
                new InputOption('dev', 'D', InputOption::VALUE_NONE, 'Blend dev requirements'),
                new InputOption('all', 'A', InputOption::VALUE_NONE, 'Blend all (dev + non-dev) requirements'),
                new InputOption('self', 'S', InputOption::VALUE_NONE, 'Blend only the mono-repository projects requirements'),
                new InputOption('json-path', null, InputOption::VALUE_REQUIRED, 'Json path to blend'),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Force to write value even if it is not present yet'),
                new InputOption('value', null, InputOption::VALUE_REQUIRED, 'Value to use'),
                new InputArgument('projects', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Projects to generate the graph for'),
            ])
            ->setDescription('Blend the mono-repository dependencies into each projects. We read all the dependencies of the root package and map them to either dev, non-dev or all.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->composer = $this->requireComposer();
        $this->config = Config::create($this->composer);

        $configProjects = array_flip($this->config->projects);
        $requireKey = true === $input->getOption('dev') ? 'require-dev' : 'require';

        if (is_string($input->getOption('json-path'))) {
            return $this->blendJsonPath($input, $output);
        }

        $all = $input->getOption('all');
        $projects = $this->getProjects($input);

        // blend a value on the mono-repository's dependencies
        if (($v = $input->getOption('value')) && $input->getOption('self')) {
            foreach ($this->config->composerFiles as $p => $composerFile) {
                if ($projects && !in_array($p, $projects, true)) {
                    continue;
                }

                $projectPackage = $this->readJsonFile($composerFile);

                foreach ($all ? ['require', 'require-dev'] : [$requireKey] as $k) {
                    foreach ($this->config->projects as $p) {
                        if (isset($projectPackage[$k][$p])) {
                            $projectPackage[$k][$p] = $v;

                        }
                    }
                }

                file_put_contents($composerFile, json_encode($projectPackage, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
            }

            return 0;
        }

        $package = $this->composer->getPackage();
        foreach ($this->config->composerFiles as $p => $composerFile) {
            if ($projects && !in_array($p, $projects, true)) {
                continue;
            }

            $projectPackage = $this->readJsonFile($composerFile);
            foreach (array_merge($package->getRequires(), $package->getDevRequires()) as $g) {
                $packageName = $g->getTarget();
                if ($input->getOption('self') && !isset($configProjects[$packageName])) {
                    continue;
                }

                foreach ($all ? ['require', 'require-dev'] : [$requireKey] as $k) {
                    // Only update the package if it's found in the project's package
                    if (!$input->getOption('force')) {
                        $hasPackage = false;
                        foreach ($projectPackage[$k] ?? [] as $r => $constraint) {
                            if ($packageName === $r) {
                                $hasPackage = true;
                                break;
                            }
                        }

                        if (!$hasPackage) {
                            continue;
                        }
                    }

                    $projectPackage[$k][$packageName] = $g->getPrettyConstraint();
                }
            }

            file_put_contents($composerFile, json_encode($projectPackage, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        }

        return 0;
    }

    private function blendJsonPath(InputInterface $input, OutputInterface $output): int {
        /** @var string */
        $jsonPath = $input->getOption('json-path');
        $path = $this->composer->getConfig()->getConfigSource()->getName();
		$pattern = '/(?<!\\\\)\./';  // Regex pattern to match a dot not preceded by a backslash
		$pointers = preg_split($pattern, $jsonPath);

        if (!$pointers) {
            $output->writeln('No pointers.');
            return 1;
        }

		foreach ($pointers as &$part) {
			$part = str_replace('\.', '.', $part);
		}

        if (!$value = $input->getOption('value')) {
            $p = $pointers;
            $data = $this->readJsonFile($path);
            $value = $data;
            while($pointer = array_shift($p)) {
                /** @var string $pointer */
                if (!is_array($value) || !isset($value[$pointer])) {
                    $output->writeln(sprintf('Node "%s" not found.', $jsonPath));
                    return 1;
                }

                $value = $value[$pointer];
            }
        }

        $projects = $this->getProjects($input);
        foreach ($this->config->composerFiles as $project => $composerFile) {
            if ($projects && !in_array($project, $projects, true)) {
                continue;
            }

            $json = $this->readJsonFile($composerFile);
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
            $fileContent = file_put_contents($composerFile, json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

            if (!$fileContent) {
                $output->writeln(sprintf('Could not write JSON at path "%s".', $composerFile));
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
