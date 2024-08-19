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
use Composer\Console\Application;
use Composer\Console\Input\InputArgument;
use Composer\Console\Input\InputOption;
use Composer\Factory;
use Pmu\Composer\ReadJsonFileTrait;
use Pmu\Config;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @phpstan-type ComposerJsonType array{repositories?: list<array{type: string, url: string}>, require?: array<string, string>, require-dev?: array<string, string>, extra?: array{pmu?: array{projects?: string[], exclude?: string[]}}}
 */
final class LinkCommand extends BaseCommand
{
    use ReadJsonFileTrait;

    /**
     * @var array<string, ComposerJsonType>
     */
    private static array $fileContents = [];

    protected function configure(): void
    {
        $this->setName('link')
            ->setDescription('Link mono-repository projects.')
            ->setDefinition([
                new InputArgument('path', InputArgument::OPTIONAL, 'Path to link.'),
                new InputOption('working-directory', 'wd', InputOption::VALUE_REQUIRED, "Defaults to SERVER['PWD']"),
            ]);
    }

    private function getComposerFileAtPath(mixed $path): string
    {
        return is_string($path) ? join('/', [$path, 'composer.json']) : join('/', [getcwd(), Factory::getComposerFile()]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $wd = $input->getOption('working-directory') ?? $_SERVER['PWD'] ?? null;
        $path = $input->getArgument('path') ?? null;

        if (is_string($path) && !str_starts_with($path, '/')) {
            if (!$wd) {
                throw new RuntimeException(sprintf('Can not find working directory, specify its value using "composer link %s --working-directory=$(pwd)"', $path));
            }

            $path = join('/', [$wd, $path]);
        }

        $monoRepositoryComposerFile = $this->getComposerFileAtPath($path);
        $monoRepositoryComposer = $this->readJsonFile($monoRepositoryComposerFile);
        $baseDir = dirname($monoRepositoryComposerFile);
        $config = Config::createFromJson($monoRepositoryComposer, $baseDir);
        $composerFile = $this->getComposerFileAtPath($wd);
        $repositories = $this->buildRepositories($config->composerFiles);
        $composer = static::$fileContents[$composerFile] = $this->readJsonFile($composerFile);

        $filesToWrite = [];
        $revert = [];
        
        $dependencies = [
            'require' => $this->mapRequireDependencies($composer, $config->composerFiles),
            'require-dev' => $this->mapRequireDevDependencies($composer, $config->composerFiles)
        ];

        foreach ($dependencies['require'] as $dependency) {
            static::$fileContents[$composerFile]['require'][$dependency] = '@dev';
        }

        foreach ($dependencies['require-dev'] as $dependency) {
            static::$fileContents[$composerFile]['require-dev'][$dependency] = '@dev';
        }

        foreach (array_merge($dependencies['require'], $dependencies['require-dev']) as $dependency) {
            foreach ($config->composerFiles as $f) {
                // mapDependencies reads the composer.json contents, if it's not there we don't need to map this dependency
                if (!isset(static::$fileContents[$f])) {
                    continue;
                }

                $contents = static::$fileContents[$f];

                if (isset($contents['require'][$dependency])) {
                    static::$fileContents[$f]['require'][$dependency] = '@dev';
                    $filesToWrite[$f] = 0;
                }

                if (isset($contents['require-dev'][$dependency])) {
                    static::$fileContents[$f]['require-dev'][$dependency] = '@dev';
                    $filesToWrite[$f] = 0;
                }
            }
        }

        static::$fileContents[$composerFile] = $this->setRepositories(static::$fileContents[$composerFile], $repositories);

        foreach(array_keys($filesToWrite + [$composerFile => 0]) as $file) {
            $backup = str_replace('composer.json', 'composer.bak.json', $file);
            copy($file, $backup);
            $revert[$file] = $backup;
            file_put_contents($file, json_encode(static::$fileContents[$file], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        }

        try {
            chdir(dirname($composerFile));
            $application = new Application();
            $application->setAutoExit(false);
            $application->run(new StringInput('update'), $output);
        } catch (\Exception $e) {
        }

        foreach ($revert as $file => $backup) {
            rename($backup, $file);
        }

        return 0;
    }

    /**
     * @param ComposerJsonType $composer
     * @param array<string, string> $composerFiles
     *
     * @return array<string>
     */
    private function mapRequireDependencies(array $composer, array $composerFiles): array {
        return array_unique(iterator_to_array($this->mapDependencies($composer, $composerFiles, 'require')));
    }

    /**
     * @param ComposerJsonType $composer
     * @param array<string, string> $composerFiles
     *
     * @return array<string>
     */
    private function mapRequireDevDependencies(array $composer, array $composerFiles): array {
        return array_unique(iterator_to_array($this->mapDependencies($composer, $composerFiles, 'require-dev')));
    }

    /**
     * @param ComposerJsonType $composer
     * @param array<string, string> $composerFiles
     *
     * @return iterable<string>
     */
    private function mapDependencies(array $composer, array $composerFiles, string $key): iterable {
        foreach (array_keys($composer[$key] ?? []) as $package) {
            if (!isset($composerFiles[$package]) || !is_string($package)) {
                continue;
            }

            yield $package;
            
            static::$fileContents[$composerFiles[$package]] = $this->readJsonFile($composerFiles[$package]);
            foreach ($this->mapDependencies(static::$fileContents[$composerFiles[$package]], $composerFiles, $key) as $package) {
                yield $package;
            }
        }
    }

    /**
     * @param ComposerJsonType $composer
     * @param list<array{type: string, url: string}> $repositories
     *
     * @return ComposerJsonType
     */
    private function setRepositories(array $composer, array $repositories): array
    {
        if (!isset($composer['repositories'])) {
            $composer['repositories'] = [];
        }

        $composer['repositories'] = array_merge($composer['repositories'], $repositories);
        return $composer;
    }

    /**
     * @param array<string, string> $composerFiles
     *
     * @return list<array{type: string, url: string}>
     */
    private function buildRepositories(array $composerFiles): array
    {
        $repositories = [];
        foreach ($composerFiles as $filename) {
            $repositories[] = ['type' => 'path', 'url' => dirname($filename)];
        }
        return $repositories;
    }
}
