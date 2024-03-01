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
use Composer\Repository\PathRepository;
use Composer\Util\Platform;
use Symfony\Component\Filesystem\Path;

// we create a new Application that will add the mono-repository repositories to projects
final class Application extends BaseApplication
{
    use BaseDirTrait;

    /**
     * @param string[] $projects
     */
    public function __construct(private Composer $monoRepoComposer, private string $baseDir, private array $projects, string $name = 'Composer', string $version = '')
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

        foreach ($config['require'] as $name => $constraint) {
            if (in_array($name, $this->projects, true)) {
                $config['require'][$name] = '@dev || ' . $constraint;
            }
        }

        foreach ($config['require-dev'] as $name => $constraint) {
            if (in_array($name, $this->projects, true)) {
                $config['require-dev'][$name] = '@dev || ' . $constraint;
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
        $packages = [];

        foreach ($this->monoRepoComposer->getRepositoryManager()->getRepositories() as $repository) {
            if (!$repository instanceof PathRepository) {
                continue;
            }

            $config = $repository->getRepoConfig();

            if (is_string($config['url']) && !Path::isAbsolute($config['url'])) {
                $config['url'] = Path::makeAbsolute($config['url'], $this->baseDir);
                // $config['options'] = ['symlink' => false]; // avoid loops when we do classmaps
            }

            $absoluteRepository = $repositoryManager->createRepository('path', $config);
            $package = $absoluteRepository->getPackages()[0];

            // Only add this repository if its package is one of our monorepository projects
            if (in_array($package->getName(), $this->projects, true)) {
                $packages[] = $package->getName();
                $repositoryManager->prependRepository($absoluteRepository);
            }
        }

        return $composer;
    }
}
