<?php

namespace M2Boilerplate\CriticalCss\Console\Command;

use M2Boilerplate\CriticalCss\Config\Config;
use M2Boilerplate\CriticalCss\Logger\Handler\ConsoleHandlerFactory;
use M2Boilerplate\CriticalCss\Service\CriticalCss;
use M2Boilerplate\CriticalCss\Service\ProcessManagerFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\State;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunFromConfigCommand extends BaseCriticalCssCommand
{
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var CriticalCss
     */
    protected $criticalCssService;

    public function __construct(
        Config $config,
        CriticalCss $criticalCssService,
        ObjectManagerInterface $objectManager,
        ConsoleHandlerFactory $consoleHandlerFactory,
        ProcessManagerFactory $processManagerFactory,
        State $state,
        ?string $name = null
    )
    {
        parent::__construct($objectManager, $consoleHandlerFactory, $processManagerFactory, $state, $name);
        $this->config = $config;
        $this->criticalCssService = $criticalCssService;
    }


    protected function configure()
    {
        $this->setName('m2bp:critical-css:run-from-config');
        $this->addArgument('config', InputArgument::REQUIRED, 'Path to config file');
        $this->addExecutionOptions();

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->criticalCssService->test($this->config->getCriticalBinary());

        $this->prepareProcessExecutions($output);
        $logger = $this->createLogger($output);

        $output->writeln('<info>Running Critical CSS generation from Config JSON</info>');

        // load config JSON
        $configList = json_decode(file_get_contents($input->getArgument('config')), true);

        $processManager = $this->createProcessManager($logger);
        $processes = $processManager->createProcessesFromConfig($configList);

        $output->writeln('<info>Generating Critical CSS for ' . count($processes) . ' URLs...</info>');

        $this->runProcesses($processManager, $input, $processes);

        $this->finishProcessExecutions($output);

        return 0;
    }
}
