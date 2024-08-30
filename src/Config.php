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
     */
    public function __construct(public readonly array $projects = [], public readonly array $exclude = [], public readonly array $composerFiles = [])
    {
    }

    /**
     * @param array{extra?: array{pmu?: array{projects?: string[], exclude?: string[]}}} $composer
     */
    public static function createFromJson($composer, string $baseDir = '.'): static
    {
        $extra = $composer['extra'] ?? [];
        if (!isset($extra['pmu'])) {
            return new self();
        }

        $files = self::toArrayString($extra['pmu']['projects'] ?? null);
        $projects = [];
        $composerFiles = [];
        foreach ($files as $glob) {
            $filenames = glob(join(DIRECTORY_SEPARATOR, [$baseDir, $glob]));
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

                $projects[] = $json['name'];
                $composerFiles[$json['name']] = $filename;
            }
        }

        return new self(
            projects: $projects,
            exclude: self::toArrayString($extra['pmu']['exclude'] ?? null),
            composerFiles: $composerFiles
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
