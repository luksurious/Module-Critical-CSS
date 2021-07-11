<?php

namespace M2Boilerplate\CriticalCss\Console\Command;

use M2Boilerplate\CriticalCss\Logger\Handler\ConsoleHandlerFactory;
use M2Boilerplate\CriticalCss\Service\ProcessManager;
use M2Boilerplate\CriticalCss\Service\ProcessManagerFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateProcessesCommand extends Command
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

    protected function configure()
    {
        $this->setName('m2bp:critical-css:create-processes');
        $this->addArgument('out', InputArgument::REQUIRED, 'File to write configs to');
        $this->addOption('replace-domain', 'd', InputOption::VALUE_OPTIONAL, 'Allows to replace the domain in the '
            . 'generated pages to use a different one (e.g., to be able to generate critical css files on a different server than production)');
        $this->addOption('only-missing', null, InputOption::VALUE_NONE,
            'Only generate critical-css for pages that are not created yet. Implies to keep old files.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

            $consoleHandler = $this->consoleHandlerFactory->create(['output' => $output]);
            $logger = $this->objectManager->create('M2Boilerplate\CriticalCss\Logger\Console', ['handlers' => ['console' => $consoleHandler]]);
            $output->writeln('<info>Generating Critical CSS</info>');

            /** @var ProcessManager $processManager */
            $processManager = $this->processManagerFactory->create(['logger' => $logger]);
            $output->writeln('<info>Gathering URLs...</info>');
            $processConfigs = $processManager->createProcessConfigs(
                $input->getOption('replace-domain'),
                $input->getOption('only-missing')
            );
            $leaves = 0;
            array_walk_recursive($processConfigs, function ($leaf, $key) use (&$leaves) {
                if ($key === 'url') {
                    $leaves++;
                }
            });
            $output->writeln('<info>Generated config for ' . $leaves . ' URLs</info>');

            file_put_contents($input->getArgument('out'), json_encode($processConfigs));
        } catch (\Throwable $e) {
            throw $e;
        }
        return 0;
    }
}
