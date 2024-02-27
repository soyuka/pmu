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
        $this->application->run(new StringInput('all show'), $output);
        $this->assertEquals('Execute "show" on "test/a"
test/b dev-main
test/c dev-main
Execute "show" on "test/b"
Execute "show" on "test/c"
test/b dev-main
', $output->fetch());
    }
}
