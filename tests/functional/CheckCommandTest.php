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

namespace Pmu\Tests\Functional;

use Composer\Console\Application;
use PHPUnit\Framework\TestCase;
use Pmu\Command\CheckCommand;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class CheckCommandTest extends TestCase {
    private Application $application;
    public function setUp(): void {
        $this->application = new Application();
        $this->application->add(new CheckCommand);
        $this->application->setAutoExit(false);
        chdir(__DIR__ . '/../monorepo');
    }

    public function testCheck(): void {
        $output = new BufferedOutput;
        $this->application->run(new StringInput('check-dependencies'), $output);
        $this->assertEquals('Class "MonoRepo\C\C" uses "MonoRepo\A\A" but it is not declared as dependency.', trim($output->fetch()));
    }
}
