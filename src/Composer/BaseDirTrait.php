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

use Composer\Config;

trait BaseDirTrait
{
    private function getBaseDir(Config $config): string
    {
        $refl = new \ReflectionClass($config);
        $prop = $refl->getProperty('baseDir');
        $v = $prop->getValue($config);

        if (is_string($v)) {
            return $v;
        }

        throw new \RuntimeException('Base dir is not a string.');
    }
}
