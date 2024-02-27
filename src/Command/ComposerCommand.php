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

namespace Pmu\Command;

use Composer\Command\BaseCommand;
use Composer\Console\Input\InputArgument;
use Composer\Package\Package;
use Pmu\Composer\Application;
use Pmu\Composer\BaseDirTrait;
use Pmu\Config;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

final class ComposerCommand extends BaseCommand
{
    use BaseDirTrait;

    public function __construct(private readonly string $package)
    {
        parent::__construct($package);
    }

    protected function configure(): void
    {
        $this
            ->setDescription(sprintf('Allows running commands in the "%s" package dir.', $this->package))
            ->setDefinition([
                new InputArgument('command-name', InputArgument::REQUIRED, ''),
                new InputArgument('args', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, ''),
            ])
            ->setHelp(
                <<<EOT
Use this command as a wrapper to run other Composer commands.
EOT
            )
        ;
    }

    /**
     * The idea here is to add back the mono-repository configured "repositories" to the component we run a command on.
     * This way we don't have to add the repositories in each components, we just configure them on the root composer.
     * We then change the current working dir and run the Application again with the provided arguments.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();
        $config = Config::create($composer);
        $localRepo = $composer->getRepositoryManager()->getLocalRepository();

        // Not much optimized but safe
        $command = explode(' ', $input->__toString());
        $key = array_search($this->package, $command, true);
        unset($command[$key]);
        $input = new StringInput(implode(' ', $command));

        $commandPackage = $localRepo->findPackage($this->package, '*');
        if (!$commandPackage || !$commandPackage instanceof Package) {
            $output->writeln(sprintf('Package "%s" could not be found.', $this->package));
            return 1;
        }

        $dir = $commandPackage->getDistUrl();

        if (!is_string($dir) || !is_dir($dir)) {
            $output->writeln(sprintf('Package "%s" could not be found at path "%s".', $this->package, $dir));
            return 1;
        }

        // Change cwd and run
        chdir($dir);
        $application = new Application($composer, $this->getBaseDir($composer->getConfig()), $config->projects);
        $application->setAutoExit(false);
        return $application->run($input, $output);
    }
}
