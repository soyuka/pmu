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
use Composer\Package\BasePackage;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

final class Plugin implements PluginInterface, Capable
{
    public function activate(Composer $composer, IOInterface $io)
    {
        /** @var string[] */
        $projects = $composer->getPackage()->getExtra()['projects'];

        if (!is_array($projects)) {
            throw new \RuntimeException('The node "extra.projects" should list your monorepository components.');
        }

        $package = $composer->getPackage();
        $flags = $package->getStabilityFlags();
        foreach ($projects as $p) {
            $flags[$p] = BasePackage::STABILITY_DEV;
        }

        $package->setStabilityFlags($flags);
        $composer->setPackage($package);
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    public function getCapabilities()
    {
        return array(
            'Composer\Plugin\Capability\CommandProvider' => 'Pmu\Composer\CommandProvider',
        );
    }
}
