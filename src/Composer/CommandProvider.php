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

namespace Pmu\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Pmu\Command\AllCommand;
use Pmu\Command\BlendCommand;
use Pmu\Command\CheckCommand;
use Pmu\Command\ComposerCommand;
use Pmu\Command\GraphCommand;
use Pmu\Command\LinkCommand;
use Pmu\Config;

final class CommandProvider implements CommandProviderCapability
{
    private Composer $composer;

    /**
     * @param array{composer: Composer, io: IOInterface} $ctorArgs
     */
    public function __construct(array $ctorArgs)
    {
        $this->composer = $ctorArgs['composer'];
    }

    public function getCommands(): array
    {
        $config = Config::create($this->composer);
        $commands = [new GraphCommand(), new AllCommand(), new CheckCommand(), new BlendCommand(), new LinkCommand()];
        foreach ($config->projects as $project) {
            $commands[] = new ComposerCommand($project);
        }

        return $commands;
    }
}
