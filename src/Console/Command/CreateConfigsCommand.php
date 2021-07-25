<?php

namespace M2Boilerplate\CriticalCss\Console\Command;

use M2Boilerplate\CriticalCss\Logger\Handler\ConsoleHandlerFactory;
use M2Boilerplate\CriticalCss\Service\ProcessManagerFactory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateConfigsCommand extends BaseCriticalCssCommand
{
    protected function configure()
    {
        $this->setName('m2bp:critical-css:create-configs');
        $this->addArgument('out', InputArgument::REQUIRED, 'File to write configs to');
        $this->addGenerationOptions();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->prepareProcessExecutions($output);

        $logger = $this->createLogger($output);

        $output->writeln('<info>Generating Critical CSS configuration file</info>');

        $output->writeln('<info>Gathering URLs...</info>');

        $processManager = $this->createProcessManager($logger);
        $processConfigs = $processManager->createProcessConfigs(
            $input->getOption('replace-domain'),
            $input->getOption('only-missing')
        );

        $totalConfigs = $this->calcConfigCount($processConfigs);
        $output->writeln('<info>Generated config for ' . $totalConfigs . ' URLs</info>');

        file_put_contents($input->getArgument('out'), json_encode($processConfigs));

        return 0;
    }

    /**
     * @param array $processConfigs
     * @return int
     */
    protected function calcConfigCount(array $processConfigs): int
    {
        // calc total number of configuration items
        $leaves = 0;
        array_walk_recursive($processConfigs, function ($leaf, $key) use (&$leaves) {
            if ($key === 'url') {
                $leaves++;
            }
        });
        return $leaves;
    }
}
