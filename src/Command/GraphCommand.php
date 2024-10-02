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
use Pmu\Config;
use Pmu\Dependencies;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class GraphCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('graph')
            ->setDefinition([
                new InputArgument('projects', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Projects to generate the graph for.'),
            ])
              ->setDescription('Outputs the graph of dependencies in the Dot format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();
        $config = Config::create($composer);
        $repo = $composer->getRepositoryManager();

        $projects = null;
        if (is_array($p = $input->getArgument('projects')) && $p) {
            $projects = array_map('strval', $p);
        }

        [
            'dependenciesByProjects' => $dependenciesByProjects,
        ] = Dependencies::collectProjectsData($config, $repo, projects: $projects);

        $dependencies = '';
        foreach ($dependenciesByProjects as $p => $deps) {
            foreach ($deps as $d) {
                $dependencies .= PHP_EOL . "    \"$p\" -> \"$d\"";
            }
        }

        $dot = <<<DOT
digraph D { $dependencies 
}
DOT;
        $output->write($dot);

        return 0;
    }
}
