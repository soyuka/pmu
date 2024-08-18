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
use Composer\Console\Application as BaseApplication;
use Composer\Factory;
use Composer\Json\JsonFile;
use Composer\Package\Locker;
use Composer\Util\Platform;
use Pmu\Config;

/**
 * We create a new Application that will add the mono-repository repositories to projects,
 * the same logic that is applied to the Link function.
 */
final class Application extends BaseApplication
{
    use BaseDirTrait;

    public function __construct(private string $baseDir, private Config $config, string $name = 'Composer', string $version = '')
    {
        parent::__construct($name, $version);
    }

    private function getLocalConfig(): mixed
    {
        // load Composer configuration
        $localConfig = Factory::getComposerFile();
        $file = new JsonFile($localConfig, null, $this->io);
        if (!$file->exists()) {
            if ($localConfig === './composer.json' || $localConfig === 'composer.json') {
                $message = 'Composer could not find a composer.json file in ' . getcwd();
            } else {
                $message = 'Composer could not find the config file: ' . $localConfig;
            }
            throw new \InvalidArgumentException($message);
        }

        return $file->read();
    }

    public function getComposer(bool $required = true, ?bool $disablePlugins = null, ?bool $disableScripts = null): ?Composer
    {
        $config = $this->getLocalConfig();
        if (!is_array($config)) {
            throw new \RuntimeException('Configuration should be an array.');
        }

        foreach (array_keys($config['require'] ?? []) as $name) {
            if (in_array($name, $this->config->projects, true)) {
                $config['require'][$name] = '@dev';
            }
        }

        foreach (array_keys($config['require-dev'] ?? []) as $name) {
            if (in_array($name, $this->config->projects, true)) {
                $config['require-dev'][$name] = '@dev';
            }
        }

        $composer = (new Factory())->createComposer($this->io, $config, true, null, true, $disableScripts ?? false);
        $config = $composer->getConfig();
        $composerFile = Factory::getComposerFile();
        $lockFile = Factory::getLockFile($composerFile);
        if (!$config->get('lock') && file_exists($lockFile)) {
            $this->io->writeError('<warning>' . $lockFile . ' is present but ignored as the "lock" config option is disabled.</warning>');
        }
        
        $composerContent = file_get_contents($composerFile);
        if (!$composerContent) {
            throw new \RuntimeException(sprintf('Could not read %s', $composerFile));
        }

        $locker = new Locker($this->io, new JsonFile($config->get('lock') ? $lockFile : Platform::getDevNull(), null, $this->io), $composer->getInstallationManager(), $composerContent, $composer->getLoop()->getProcessExecutor());
        $composer->setLocker($locker);

        $repositoryManager = $composer->getRepositoryManager();

		foreach ($this->config->composerFiles as $filename) {
            $absoluteRepository = $repositoryManager->createRepository('path', ['url' => join('/', [$this->baseDir, dirname($filename)])]);
            $repositoryManager->prependRepository($absoluteRepository);
        }

        return $composer;
    }
}
