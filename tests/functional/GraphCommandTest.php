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
use Pmu\Command\GraphCommand;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class GraphCommandTest extends TestCase {
    private Application $application;
    public function setUp(): void {
        $this->application = new Application();
        $this->application->add(new GraphCommand);
        $this->application->setAutoExit(false);
        chdir(__DIR__ . '/../monorepo');
    }

    public function testGraph(): void {
        $output = new BufferedOutput;
        $this->application->run(new StringInput('graph'), $output);
        $this->assertEquals('digraph D { 
    "test/a" -> "test/b"
    "test/c" -> "test/b" 
}', $output->fetch());
    }

    public function testGraphWithProject(): void {
        $output = new BufferedOutput;
        $this->application->run(new StringInput('graph test/a'), $output);
        $this->assertEquals('digraph D { 
    "test/a" -> "test/b" 
}', $output->fetch());
    }
}
