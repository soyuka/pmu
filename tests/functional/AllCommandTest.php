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
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

final class AllCommandTest extends TestCase {
    private Application $application;
    public function setUp(): void {
        $this->application = new Application();
        $this->application->add(new AllCommand);
        $this->application->setAutoExit(false);
        chdir(__DIR__ . '/../monorepo');
    }

    public function testAll(): void {
        $nullOutput = new NullOutput;
        $output = new BufferedOutput;
        $this->application->run(new StringInput('all install'), $nullOutput);
        $this->application->run(new StringInput('all echo'), $output);
        $this->assertStringContainsString('Execute "echo" on "test/a"> echoExecute "echo" on "test/b"> echoExecute "echo" on "test/c"> echo', str_replace(array("\n", "\r"), '', $output->fetch()));
    }
}
