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

use Composer\Composer;

final class Config
{
    /**
     * @param string[] $projects
     * @param string[] $exclude
     * @param array<string, string> $composerFiles
     * @param array<string, bool> $baseLine errors to omit from "check-dependencies" command, strings only
     */
    public function __construct(public readonly array $projects = [], public readonly array $exclude = [], public readonly array $composerFiles = [], public readonly array $baseLine = [])
    {
    }

    /**
     * @param array{name?: string, replace?: array<string, string>, extra?: array{pmu?: array{projects?: string[], exclude?: string[]}}} $composer
     * @param array{name?: string, require?: array<string, string>, 'require-dev'?: array<string, string>} $projectComposer
     */
    public static function createFromJson($composer, string $baseDir = '.', array $projectComposer = []): static
    {
        $extra = $composer['extra'] ?? [];
        if (!isset($extra['pmu'])) {
            return new self();
        }

        if (file_exists('pmu.baseline')) {
            $contents = file_get_contents('pmu.baseline');
            if ($contents) {
                $baseLine = [];
                foreach (explode(PHP_EOL, $contents) as $v) {
                    if ($v) {
                        $baseLine[$v] = true;
                    }
                }
            }
        }
    
        $composerFiles = [];

        // If the project requires the monorepository package, we add a repository for it.
        // useful to work with `replace`
        $name = $composer['name'] ?? null;
        $hasMonorepositoryAsDependency = false;
        if ($name && (array_key_exists($name, $projectComposer['require'] ?? []) || array_key_exists($name, $projectComposer['require-dev'] ?? []))) {
            $hasMonorepositoryAsDependency = true;
            $composerFiles[$name] = implode(DIRECTORY_SEPARATOR, [$baseDir, 'composer.json']);
        }

        $files = self::toArrayString($extra['pmu']['projects'] ?? null);
        $projects = [];
        foreach ($files as $glob) {
            $filenames = glob(implode(DIRECTORY_SEPARATOR, [$baseDir, $glob]));
            if (!$filenames) {
                return new self();
            }

            foreach ($filenames as $filename) {
                if (!$content = file_get_contents($filename)) {
                    throw new \RuntimeException(sprintf('File %s not found.', $filename));
                }

                $json = json_decode($content, true);
                if (!is_array($json) || !isset($json['name']) || !is_string($json['name'])) {
                    throw new \RuntimeException(sprintf('Malformed JSON at path %s.', $filename));
                }

                if ($hasMonorepositoryAsDependency && array_key_exists($json['name'], $composer['replace'])) {
                    continue;
                }

                $projects[] = $json['name'];
                $composerFiles[$json['name']] = $filename;
            }
        }

        return new self(
            projects: $projects,
            exclude: self::toArrayString($extra['pmu']['exclude'] ?? null),
            composerFiles: $composerFiles,
            baseLine: $baseLine ?? []
        );
    }

    public static function create(Composer $composer): static
    {
        /** @var array{extra?: array{pmu?: array{projects?: string[], exclude?: string[]}}} */
        $json = ['extra' => $composer->getPackage()->getExtra()];
        return static::createFromJson($json);
    }

    /**
     * @return string[]
     */
    private static function toArrayString(mixed $a): array
    {
        if (!is_array($a)) {
            return [];
        }

        return array_map(fn ($v) => (string) $v, $a);
    }
}
