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
use Pmu\Command\AllCommand;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

final class LinkCommandTest extends TestCase {
    private Application $application;
    public function setUp(): void {
        $this->application = new Application();
        $this->application->add(new AllCommand);
        $this->application->setAutoExit(false);
        chdir(__DIR__ . '/../monorepo');
    }

    public function testLink(): void {
        $nullOutput = new NullOutput;
        $this->application->run(new StringInput('link'), $nullOutput);
        $this->assertTrue(is_link("./tests/monorepo/vendor/test/a"));
        $this->assertTrue(is_link("./tests/monorepo/vendor/test/b"));
        $this->assertTrue(is_link("./tests/monorepo/vendor/test/c"));
    }
}
