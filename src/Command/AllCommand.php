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

final class AllCommand extends BaseCommand
{
    use BaseDirTrait;

    protected function configure(): void
    {
        $this->setName('all')
            ->setDescription('Executes a composer command on every packages of your mono repository.')
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();
        $config = Config::create($composer);
        $localRepo = $composer->getRepositoryManager()->getLocalRepository();

        // Not much optimized but safe
        $command = explode(' ', $input->__toString());
        $key = array_search('all', $command, true);
        unset($command[$key]);
        $argv = implode(' ', $command);

        $baseDir = $this->getBaseDir($composer->getConfig());
        $exitCode = 0;

        foreach ($config->projects as $project) {
            $commandPackage = $localRepo->findPackage($project, '*');
            if (!$commandPackage || !$commandPackage instanceof Package) {
                $output->writeln(sprintf('Package "%s" could not be found.', $project));
                continue;
            }

            $dir = $commandPackage->getDistUrl();

            if (!is_string($dir) || !is_dir($dir)) {
                $output->writeln(sprintf('Package "%s" could not be found at path "%s".', $project, $dir));
                return 1;
            }

            // Change cwd and run
            chdir($dir);
            $output->writeln(sprintf('Execute "%s" on "%s"', $argv, $project));
            $application = new Application($composer, $baseDir, $config->projects);
            $application->setAutoExit(false);
            $c = $application->run(new StringInput($argv), $output);
            if ($exitCode === 0 && $c !== 0) {
                $exitCode = $c;
            }
            chdir($baseDir);
        }

        return $exitCode;
    }
}
