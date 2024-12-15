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

	protected function load_dependencies($name): array {

		if( ! key_exists($name, $this->config->composerFiles)) {
			return [];
		}

		$localConfig = $this->fetch_link_to_base_dir() . $this->config->composerFiles[$name];
		$file = new JsonFile($localConfig, null, $this->io);
		if (!$file->exists()) {
			if ($localConfig === './composer.json' || $localConfig === 'composer.json') {
				$message = 'Composer could not find a composer.json file in ' . getcwd();
			} else {
				$message = 'Composer could not find the config file: ' . $localConfig;
			}
			throw new \InvalidArgumentException($message);
		}

		$config = $file->read();

		$dependencies_require = array_keys($config['require'] ?? []);
		$dependencies_require_dev = array_keys($config['require-dev'] ?? []);

		return array_merge($dependencies_require, $dependencies_require_dev);
	}

	protected function fetch_link_to_base_dir(): string {
		$config = $this->getLocalConfig();
		if(! $config['name']) {
			return '';
		}

		$name = $config['name'];

		if(! key_exists($name, $this->config->composerFiles)) {
			return '';
		}

		$path = $this->config->composerFiles[$name];
		$levels = count(explode(DIRECTORY_SEPARATOR, $path)) - 1;
		$base_dir_path = '';
		for ($level = 0; $level < $levels - 1; $level ++) {
			$base_dir_path .= '..' . DIRECTORY_SEPARATOR;
		}
		return $base_dir_path;
	}

	public function getComposer(bool $required = true, ?bool $disablePlugins = null, ?bool $disableScripts = null): ?Composer
	{
		$config = $this->getLocalConfig();
		if (!is_array($config)) {
			throw new \RuntimeException('Configuration should be an array.');
		}

		$dependencies_checked = [];

		$dependencies_require = array_keys($config['require'] ?? []);
		$dependencies_require_dev = array_keys($config['require-dev'] ?? []);

		$dependencies = array_merge($dependencies_require, $dependencies_require_dev);

		while(count($dependencies) > 0) {
			$dependencies_to_scan = [];
			foreach ($dependencies as $name) {
				if (in_array($name, $this->config->projects, true)) {
					if(key_exists('require', $config)) {
						$config['require'][$name] = '@dev';
					}
					if(key_exists('require-dev', $config)) {
						$config['require-dev'][$name] = '@dev';
					}

					$dependencies_library = $this->load_dependencies($name);

					foreach ($dependencies_library as $sub_dependency) {
						if (in_array($name, $this->config->projects, true)) {
							$dependencies_to_scan[] = $sub_dependency;
						}
					}
				}
				$dependencies_checked[] = $name;
			}
			$dependencies = $dependencies_to_scan;
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
			$absoluteRepository = $repositoryManager->createRepository('path', ['url' => join(DIRECTORY_SEPARATOR, [$this->baseDir, dirname($filename)])]);
			$repositoryManager->prependRepository($absoluteRepository);
		}

		return $composer;
	}
}
