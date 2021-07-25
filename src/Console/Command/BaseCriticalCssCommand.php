<?php

namespace M2Boilerplate\CriticalCss\Console\Command;

use M2Boilerplate\CriticalCss\Config\Config;
use M2Boilerplate\CriticalCss\Logger\Handler\ConsoleHandlerFactory;
use M2Boilerplate\CriticalCss\Service\ProcessManager;
use M2Boilerplate\CriticalCss\Service\ProcessManagerFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCriticalCssCommand extends Command
{
    /**
     * @var ProcessManagerFactory
     */
    protected $processManagerFactory;
    /**
     * @var ConsoleHandlerFactory
     */
    protected $consoleHandlerFactory;
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;
    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

    public function __construct(
        ObjectManagerInterface $objectManager,
        ConsoleHandlerFactory $consoleHandlerFactory,
        ProcessManagerFactory $processManagerFactory,
        State $state,
        ?string $name = null
    )
    {
        parent::__construct($name);
        $this->processManagerFactory = $processManagerFactory;
        $this->consoleHandlerFactory = $consoleHandlerFactory;
        $this->objectManager = $objectManager;
        $this->state = $state;
    }

    protected function addExecutionOptions()
    {
        $this->addOption('no-baseurl', null, InputOption::VALUE_NONE,
            'Don\'t add the base domain during post processing');
        $this->addOption('keep-files', null, InputOption::VALUE_NONE,
            'Don\'t delete old/previously generated files');
    }

    protected function addGenerationOptions()
    {
        $this->addOption('replace-domain', null, InputOption::VALUE_OPTIONAL, 'Allows to replace the domain in the '
            . 'generated pages to use a different one (e.g., to be able to generate critical css files on a different machine)');
        $this->addOption('only-missing', null, InputOption::VALUE_NONE,
            'Only generate critical-css for pages that have not been created yet. Implies to keep old files.');
    }

    protected function shouldKeepFiles(InputInterface $input)
    {
        $keep = false;
        if ($input->hasOption('keep-files')) {
            $keep = $input->getOption('keep-files');
        }
        if ($input->hasOption('only-missing')) {
            $keep = $keep || $input->getOption('only-missing');
        }
        return $keep;
    }

    protected function runProcesses(ProcessManager $processManager, InputInterface $input, array $processes)
    {
        $processManager->executeProcesses(
            $processes,
            !$this->shouldKeepFiles($input),
            $input->getOption('no-baseurl')
        );
    }

    /**
     * @param OutputInterface $output
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function prepareProcessExecutions(OutputInterface $output): void
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

        $output->writeln('<info>Disabling ' . Config::CONFIG_PATH_ENABLED . ' while collecting css...</info>');
        $this->getApplication()->find("config:set")->run(new ArrayInput(["path" => Config::CONFIG_PATH_ENABLED, "value" => "0", "--lock-env" => 1]), $output);
        $this->getApplication()->find("app:config:import")->run(new ArrayInput([]), $output);
        $this->getApplication()->find("cache:flush")->run(new ArrayInput([]), $output);
    }

    /**
     * @param OutputInterface $output
     * @return mixed
     */
    protected function createLogger(OutputInterface $output)
    {
        $consoleHandler = $this->consoleHandlerFactory->create(['output' => $output]);
        $logger = $this->objectManager->create(
            'M2Boilerplate\CriticalCss\Logger\Console',
            ['handlers' => ['console' => $consoleHandler]]
        );
        return $logger;
    }

    /**
     * @param OutputInterface $output
     * @throws \Exception
     */
    protected function finishProcessExecutions(OutputInterface $output): void
    {
        $output->writeln('<info>Enabling ' . Config::CONFIG_PATH_ENABLED . '...</info>');
        $this->getApplication()->find("config:set")->run(new ArrayInput(["path" => Config::CONFIG_PATH_ENABLED, "value" => "1", "--lock-env" => 1]), $output);
        $this->getApplication()->find("app:config:import")->run(new ArrayInput([]), $output);
        $this->getApplication()->find("cache:flush")->run(new ArrayInput([]), $output);
    }

    /**
     * @param $logger
     * @return ProcessManager
     */
    protected function createProcessManager($logger): ProcessManager
    {
        /** @var ProcessManager $processManager */
        $processManager = $this->processManagerFactory->create(['logger' => $logger]);
        return $processManager;
    }
}
