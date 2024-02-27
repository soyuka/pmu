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

namespace MonoRepo\A;

use MonoRepo\B\B;
use MonoRepo\C\C;

class A {
    public function __construct(public B $b, public ?C $c = null) {}
}
