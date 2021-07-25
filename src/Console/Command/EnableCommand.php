<?php

namespace M2Boilerplate\CriticalCss\Console\Command;

use M2Boilerplate\CriticalCss\Config\Config;
use M2Boilerplate\CriticalCss\Logger\Handler\ConsoleHandlerFactory;
use M2Boilerplate\CriticalCss\Service\ProcessManagerFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\State;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnableCommand extends BaseCriticalCssCommand
{
    /**
     * @var Config
     */
    protected $config;

    public function __construct(
        Config $config,
        ObjectManagerInterface $objectManager,
        ConsoleHandlerFactory $consoleHandlerFactory,
        ProcessManagerFactory $processManagerFactory,
        State $state,
        ?string $name = null
    )
    {
        parent::__construct($objectManager, $consoleHandlerFactory, $processManagerFactory, $state, $name);
        $this->config = $config;
    }


    protected function configure()
    {
        $this->setName('m2bp:critical-css:enable');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->finishProcessExecutions($output);
        return 0;
    }
}
