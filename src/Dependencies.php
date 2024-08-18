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

namespace Pmu;

use Composer\ClassMapGenerator\ClassMapGenerator;
use Composer\Repository\RepositoryManager;
use Symfony\Component\Filesystem\Path;

/**
 * @phpstan-type ComposerJsonType array{repositories?: list<array{type: string, url: string}>, require?: array<string, string>, require-dev?: array<string, string>, extra?: array{pmu?: array{projects?: string[], exclude?: string[]}}, autoload?: array<string, array<string, array<string, string>>>}
 */
final class Dependencies
{
    /**
     * @return ComposerJsonType
     */
    private static function readJsonFile(string $composerFile): array
    {
        $composer = file_get_contents($composerFile);
        if (!$composer) {
            throw new \RuntimeException(sprintf('Composer file "%s" is not readable.', $composerFile));
        }

        /** @var ComposerJsonType */
        return json_decode($composer, true);
    }

    /**
     * Collects data in this single function for performance reasons we want to avoid looping more then once over projects.
     *
     * @param string[] $projects
     *
     * @return array{
     *    autoloadByProjects: array<string, string[]>,
     *    dependenciesByProjects: array<string, string[]>,
     *    classMap: array<class-string, string>,
     *    namespaces: string[]
     * }
     */
    public static function collectProjectsData(Config $config, RepositoryManager $repo, bool $computeClassMap = false, bool $includeDev = false, ?array $projects = null): array
    {
        $classMap = [];
        $autoloadByProjects = [];
        $dependenciesByProjects = [];
        $namespaces = [];
        $classMapGenerator = new ClassMapGenerator();

        // Collect project data
        foreach (($projects ?? $config->projects) as $project) {
            if (!($composerFile = $config->composerFiles[$project] ?? null)) 
            {
                continue;
            }

            $package = static::readJsonFile($composerFile);
            $autoload = $package['autoload'] ?? [];
            $autoloadByProjects[$project] = [];

            foreach (array_keys($autoload['psr-4'] ?? []) as $ns) {
                $autoloadByProjects[$project][] = $ns;
                $namespaces[] = $ns;
            }

            foreach (array_keys($autoload['psr-0'] ?? []) as $ns) {
                $autoloadByProjects[$project][] = $ns;
                $namespaces[] = $ns;
            }

            $requires = $package['require'] ?? [];
            if ($includeDev) {
                $requires = array_merge($requires, $package['require-dev'] ?? []);
            }

            $dependenciesByProjects[$project] = [];
            foreach (array_keys($requires) as $require) {
                if (isset($config->composerFiles[$require])) {
                    $dependenciesByProjects[$project][] = $require;
                }
            }

            if ($computeClassMap) {
                $classMapGenerator->scanPaths(dirname($composerFile));
                foreach ($classMapGenerator->getClassMap()->getMap() as $class => $path) {
                    foreach ($namespaces as $ns) {
                        foreach ($config->exclude as $g) {
                            if (fnmatch($g, $path) || Path::isBasePath($g, $path)) {
                                continue;
                            }
                        }


                        if (str_starts_with($class, $ns)) {
                            $classMap[$class] = $path;
                        }
                    }
                }
            }
        }

        return [
            'autoloadByProjects' => $autoloadByProjects,
            'dependenciesByProjects' => $dependenciesByProjects,
            'namespaces' => $namespaces,
            'classMap' => $classMap
        ];
    }
}
