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
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryManager;
use Symfony\Component\Filesystem\Path;

final class Dependencies
{
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
            $package = $repo->findPackage($project, '*');

            if (!$package instanceof PackageInterface) {
                continue;
            }

            if (!($u = $package->getDistUrl())) {
                continue;
            }

            $autoload = $package->getAutoload();
            $autoloadByProjects[$project] = [];

            foreach (array_keys($autoload['psr-4'] ?? []) as $ns) {
                $autoloadByProjects[$project][] = $ns;
                $namespaces[] = $ns;
            }

            foreach (array_keys($autoload['psr-0'] ?? []) as $ns) {
                $autoloadByProjects[$project][] = $ns;
                $namespaces[] = $ns;
            }

            $requires = $package->getRequires();
            if ($includeDev) {
                $requires = array_merge($requires, $package->getDevRequires());
            }

            $dependenciesByProjects[$project] = [];
            foreach (array_keys($requires) as $require) {
                if (in_array($require, $config->projects, true)) {
                    $dependenciesByProjects[$project][] = $require;
                }
            }

            if ($computeClassMap) {
                $classMapGenerator->scanPaths($u);
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
