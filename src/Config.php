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
     */
    public function __construct(public readonly array $projects, public readonly array $exclude)
    {
    }

    public static function create(Composer $composer): self
    {

        return new self(
            projects: self::toArrayString($composer->getPackage()->getExtra()['projects'] ?? null),
            exclude: self::toArrayString($composer->getPackage()->getExtra()['exclude'] ?? null)
        );
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
