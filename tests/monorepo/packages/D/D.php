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

namespace MonoRepo\D;

// not inside require but inside the baseline
use MonoRepo\A\A;

class D {
    public function __construct(public ?A $a = null) {}
}
