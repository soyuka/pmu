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

namespace MonoRepo\C;

use MonoRepo\A\A;
// not inside require should make check-dependencies fail
use MonoRepo\B\B;

class C {
    public function __construct(public A $a, public ?B $b = null) {}
}

