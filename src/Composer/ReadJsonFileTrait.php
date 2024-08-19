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

namespace Pmu\Composer;

/**
 * @phpstan-type ComposerJsonType array{repositories?: list<array{type: string, url: string}>, require?: array<string, string>, require-dev?: array<string, string>, extra?: array{pmu?: array{projects?: string[], exclude?: string[]}}}
 */
trait ReadJsonFileTrait
{
    /**
     * @return ComposerJsonType
     */
    private function readJsonFile(string $composerFile): array {
        $composer = file_get_contents($composerFile);
        if (!$composer) {
            throw new \RuntimeException(sprintf('Composer file "%s" is not readable.', $composerFile));
        }

        /** @var ComposerJsonType */
        return json_decode($composer, true);
    }
}
