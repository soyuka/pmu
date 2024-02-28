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
use Composer\Repository\PathRepository;
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

    public function getComposer(bool $required = true, ?bool $disablePlugins = null, ?bool $disableScripts = null): ?Composer
    {
        /** @var Composer */
        $composer = parent::getComposer();
        $repositoryManager = $composer->getRepositoryManager();

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
                $repositoryManager->prependRepository($absoluteRepository);
            }
        }

        return $composer;
    }
}
