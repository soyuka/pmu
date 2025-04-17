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
use Pmu\Command\ComposerCommand;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ComposerCommandTest extends TestCase {
    private Application $application;
    public function setUp(): void {
        $this->application = new Application();
        $this->application->add(new ComposerCommand('test/a'));
        $this->application->setAutoExit(false);
        chdir(__DIR__ . '/../monorepo');
    }

    public function testRunA(): void {
        $output = new BufferedOutput;
        $this->application->run(new StringInput('test/a run-script hello'), $output);
        $this->assertEquals("> exit 123\nScript exit 123 handling the hello event returned with error code 123\n", $output->fetch());
    }

    public function testRunPwd(): void {
        $output = new BufferedOutput;
        $this->application->run(new StringInput('test/a --cwd'), $output);
        $this->assertEquals('././packages/A', $output->fetch());
    }
}
