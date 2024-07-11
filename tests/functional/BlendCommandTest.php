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
use Pmu\Command\BlendCommand;
use RuntimeException;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class BlendCommandTest extends TestCase {
    private Application $application;
    /**
     * @var string[]
     */
    private array $files;
    /**
     * @var array<int, string|false>
     */
    private array $backups;
    public function setUp(): void {
        $this->application = new Application();
        $this->application->add(new BlendCommand);
        $this->application->setAutoExit(false);
        chdir(__DIR__ . '/../monorepo');
    }

    public function testBlend(): void {
        $this->files = [__DIR__ . '/../monorepo/packages/A/composer.json'];
        $this->backups = [file_get_contents($this->files[0])];
        $output = new BufferedOutput;
        $this->application->run(new StringInput('blend'), $output);
        $json = file_get_contents($this->files[0]) ?: throw new \RuntimeException;

        /** @var array{require: array<string, string>, 'require-dev': array<string, string>} */
        $new = json_decode($json, true);
        $this->assertEquals($new['require']['soyuka/contexts'], '^3.0.0');
        $this->assertEquals("", $output->fetch());
    }

    public function testBlendDev(): void {
        $this->files = [__DIR__ . '/../monorepo/packages/A/composer.json'];
        $this->backups = [file_get_contents($this->files[0])];
        $output = new BufferedOutput;
        $this->application->run(new StringInput('blend --dev'), $output);
        $json = file_get_contents($this->files[0]) ?: throw new \RuntimeException;

        /** @var array{require: array<string, string>, 'require-dev': array<string, string>} */
        $new = json_decode($json, true);
        $this->assertEquals($new['require-dev']['symfony/contracts'], '^2.0.0');
        $this->assertEquals("", $output->fetch());
    }

    public function testBlendWithProject(): void {
        $this->files = [__DIR__ . '/../monorepo/packages/A/composer.json'];
        $this->backups = [file_get_contents($this->files[0])];
        $output = new BufferedOutput;
        $this->application->run(new StringInput('blend test/b'), $output);
        $json = file_get_contents($this->files[0]) ?: throw new \RuntimeException;

        /** @var array{require: array<string, string>, 'require-dev': array<string, string>} */
        $new = json_decode($json, true);
        $this->assertEquals($new['require']['soyuka/contexts'], '^2.0.0');
        $this->assertEquals("", $output->fetch());
    }

    public function testBlendJsonPath(): void {
        $this->files = [__DIR__ . '/../monorepo/packages/A/composer.json', __DIR__ . '/../monorepo/packages/B/composer.json', __DIR__ . '/../monorepo/packages/C/composer.json'];
        $this->backups = array_map('file_get_contents', $this->files);
        $output = new BufferedOutput;
        $this->application->run(new StringInput('blend --json-path=extra.branch-alias.dev-main --force'), $output);

        foreach ($this->files as $f) {
            $json = file_get_contents($f) ?: throw new RuntimeException;
            /** @var array{extra?: array{branch-alias?: array<string, string>}} */
            $new = json_decode($json, true);
            $this->assertEquals($new['extra']['branch-alias']['dev-main'] ?? null, '3.3.x-dev');
        }
        $this->assertEquals("", $output->fetch());
    }

    protected function tearDown(): void {
        while ($file = array_shift($this->files)) {
            if ($b = array_shift($this->backups)) {
                file_put_contents($file, $b);
            }
        }
    }
}
