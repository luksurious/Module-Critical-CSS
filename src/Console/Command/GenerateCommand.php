<?php

namespace M2Boilerplate\CriticalCss\Console\Command;

use M2Boilerplate\CriticalCss\Config\Config;
use M2Boilerplate\CriticalCss\Logger\Handler\ConsoleHandlerFactory;
use M2Boilerplate\CriticalCss\Service\CriticalCss;
use M2Boilerplate\CriticalCss\Service\ProcessManager;
use M2Boilerplate\CriticalCss\Service\ProcessManagerFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
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
     * @var Config
     */
    protected $config;
    /**
     * @var CriticalCss
     */
    protected $criticalCssService;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

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
        parent::__construct($name);
        $this->processManagerFactory = $processManagerFactory;
        $this->consoleHandlerFactory = $consoleHandlerFactory;
        $this->objectManager = $objectManager;
        $this->config = $config;
        $this->criticalCssService = $criticalCssService;
        $this->state = $state;
    }


    protected function configure()
    {
        $this->setName('m2bp:critical-css:generate');
        $this->addOption('replace-domain', 'd', InputOption::VALUE_OPTIONAL, 'Allows to replace the domain in the '
            . 'generated pages to use a different one (e.g., to be able to generate critical css files on a different server than production)');
        $this->addOption('no-domain-postprocessing', 's', InputOption::VALUE_NONE,
            'Don\'t add the base domain during post processing');
        $this->addOption('keep-old-files', null, InputOption::VALUE_NONE,
            'Don\'t delete old files');
        $this->addOption('only-missing', null, InputOption::VALUE_NONE,
            'Only generate critical-css for pages that are not created yet. Implies to keep old files.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

            $output->writeln('<info>Disabling ' . Config::CONFIG_PATH_ENABLED . ' while collecting css...</info>');
            $this->getApplication()->find("config:set")->run(new ArrayInput(["path" => Config::CONFIG_PATH_ENABLED, "value" => "0", "--lock-env" => 1]), $output);
            $this->getApplication()->find("app:config:import")->run(new ArrayInput([]), $output);
            $this->getApplication()->find("cache:flush")->run(new ArrayInput([]), $output);

            $this->criticalCssService->test($this->config->getCriticalBinary());
            $consoleHandler = $this->consoleHandlerFactory->create(['output' => $output]);
            $logger = $this->objectManager->create('M2Boilerplate\CriticalCss\Logger\Console', ['handlers' => ['console' => $consoleHandler]]);
            $output->writeln('<info>Generating Critical CSS</info>');

            /** @var ProcessManager $processManager */
            $processManager = $this->processManagerFactory->create(['logger' => $logger]);
            $output->writeln('<info>Gathering URLs...</info>');
            $processes = $processManager->createProcesses(
                $input->getOption('replace-domain'),
                $input->getOption('only-missing')
            );
            $output->writeln('<info>Generating Critical CSS for ' . count($processes) . ' URLs...</info>');

            $processManager->executeProcesses(
                $processes,
                !$input->getOption('keep-old-files') && !$input->getOption('only-missing'),
                $input->getOption('no-domain-postprocessing')
            );

            $output->writeln('<info>Enabling ' . Config::CONFIG_PATH_ENABLED . '...</info>');
            $this->getApplication()->find("config:set")->run(new ArrayInput(["path" => Config::CONFIG_PATH_ENABLED, "value" => "1", "--lock-env" => 1]), $output);
            $this->getApplication()->find("app:config:import")->run(new ArrayInput([]), $output);
            $this->getApplication()->find("cache:flush")->run(new ArrayInput([]), $output);

        } catch (\Throwable $e) {
            throw $e;
        }
        return 0;
    }


}
