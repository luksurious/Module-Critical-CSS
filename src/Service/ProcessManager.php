<?php

namespace M2Boilerplate\CriticalCss\Service;

use M2Boilerplate\CriticalCss\Model\ProcessContextFactory;
use M2Boilerplate\CriticalCss\Config\Config;
use M2Boilerplate\CriticalCss\Model\ProcessContext;
use M2Boilerplate\CriticalCss\Provider\Container;
use M2Boilerplate\CriticalCss\Provider\ProviderInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ProcessManager
{
    /**
     * @var Emulation
     */
    protected $emulation;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var CriticalCss
     */
    protected $criticalCssService;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ProcessContextFactory
     */
    protected $contextFactory;

    /**
     * @var Storage
     */
    protected $storage;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CssProcessor
     */
    protected $cssProcessor;

    public function __construct(
        LoggerInterface $logger,
        Storage $storage,
        ProcessContextFactory $contextFactory,
        Config $config,
        CriticalCss $criticalCssService,
        Emulation $emulation,
        StoreManagerInterface $storeManager,
        Container $container,
        CssProcessor $cssProcessor
    )
    {
        $this->emulation = $emulation;
        $this->storeManager = $storeManager;
        $this->container = $container;
        $this->criticalCssService = $criticalCssService;
        $this->config = $config;
        $this->contextFactory = $contextFactory;
        $this->storage = $storage;
        $this->logger = $logger;
        $this->cssProcessor = $cssProcessor;
    }

    /**
     * @param ProcessContext[] $processList
     * @param bool $deleteOldFiles
     * @param bool $postProcessingNoDomain
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function executeProcesses(
        array $processList,
        bool $deleteOldFiles = false,
        bool $postProcessingNoDomain = false
    ): void
    {

        if ($deleteOldFiles) {
            $this->storage->clean();
        }

        /** @var ProcessContext[] $batch */
        $batch = array_splice($processList, 0, $this->config->getNumberOfParallelProcesses());
        foreach ($batch as $context) {
            $context->getProcess()->start();
            $this->logger->debug(sprintf(
                '[%s|%s] > %s',
                $context->getProviderName(),
                $context->getOrigIdentifier(),
                $context->getProcess()->getCommandLine()
            ));
        }

        while (count($processList) > 0 || count($batch) > 0) {
            foreach ($batch as $key => $context) {
                if (!$context->getProcess()->isRunning()) {
                    try {
                        $this->handleEndedProcess($context, $postProcessingNoDomain);
                    } catch (ProcessFailedException $e) {
                        $this->logger->error($e);
                    }
                    unset($batch[$key]);
                    if (count($processList) > 0) {
                        $newProcess = array_shift($processList);
                        $newProcess->getProcess()->start();
                        $this->logger->debug(sprintf(
                            '[%s|%s] - %s',
                            $context->getProviderName(),
                            $context->getOrigIdentifier(),
                            $context->getProcess()->getCommandLine()
                        ));
                        $batch[] = $newProcess;
                    }
                }
            }
            usleep(500); // wait for processes to finish
        }

    }

    public function createProcesses(string $customDomain = null, bool $onlyMissing = true): array
    {
        $existingFiles = $this->storage->getFileList();

        $processList = [];
        foreach ($this->storeManager->getStores() as $storeId => $store) {
            $this->emulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);
            $this->storeManager->setCurrentStore($storeId);

            foreach ($this->container->getProviders() as $provider) {
                $configList = $this->generateProcessConfigsForProvider($provider, $store, $customDomain);

                $providerProcesses = $this->createProcessesForProvider($configList);

                if ($onlyMissing) {
                    $providerProcesses = array_filter($providerProcesses, function (ProcessContext $item) use ($existingFiles) {
                        return !array_search($item->getIdentifier() . '.css', $existingFiles);
                    });
                }

                $processList = array_merge($processList, $providerProcesses);
            }
            $this->emulation->stopEnvironmentEmulation();
        }

        return $processList;
    }

    public function createProcessesForProvider(array $configList): array
    {
        $processList = [];
        foreach ($configList as $config) {
            $this->logger->info(sprintf(
                '[%s:%s|%s] - %s',
                $config['storeCode'],
                $config['providerName'],
                $config['identifier'],
                $config['url']
            ));

            $process = $this->criticalCssService->createCriticalCssProcess(
                $config['url'],
                $config['dimensions'],
                $this->config->getCriticalBinary(),
                $config['username'],
                $config['password']
            );
            $context = $this->contextFactory->create([
                'process' => $process,
                'storeCode' => $config['storeCode'],
                'providerName' => $config['providerName'],
                'identifier' => $config['identifier']
            ]);
            $processList[] = $context;
        }
        return $processList;
    }

    public function createProcessesFromConfig(array $configList): array
    {
        $processList = [];
        foreach ($configList as $storeId => $storeConfig) {
            $storeConfig = $configList[$storeId];

            foreach ($storeConfig as $providerName => $providerConfig) {
                $providerProcesses = $this->createProcessesForProvider($providerConfig);

                $processList = array_merge($processList, $providerProcesses);
            }
        }

        return $processList;
    }

    public function createProcessConfigs(string $customDomain = null, bool $onlyMissing = true): array
    {
        $existingFiles = $this->storage->getFileList();

        $configList = [];
        foreach ($this->storeManager->getStores() as $storeId => $store) {
            $this->emulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);
            $this->storeManager->setCurrentStore($storeId);

            $providerConfigs = [];
            foreach ($this->container->getProviders() as $provider) {
                $configs = $this->generateProcessConfigsForProvider($provider, $store, $customDomain);

                if ($onlyMissing) {
                    $configs = array_filter($configs, function (array $item) use ($existingFiles) {
                        return !array_search($item['identifier'] . '.css', $existingFiles);
                    });
                }

                $providerConfigs[$provider->getName()] = $configs;
            }

            $configList[$storeId] = $providerConfigs;

            $this->emulation->stopEnvironmentEmulation();
        }

        return $configList;
    }

    protected function generateProcessConfigsForProvider(
        ProviderInterface $provider,
        StoreInterface $store,
        string $customDomain = null
    ): array
    {
        $configList = [];
        $urls = $provider->getUrls($store);
        foreach ($urls as $identifier => $url) {
            if ($customDomain) {
                $url = preg_replace("/(https?:\/\/)[^\/]+/", "$1$customDomain", $url);
            }

            $config = [
                'url' => $url,
                'dimensions' => $this->config->getDimensions(),
                'username' => $this->config->getUsername(),
                'password' => $this->config->getPassword(),
                'storeId' => $store->getId(),
                'storeCode' => $store->getCode(),
                'providerName' => $provider->getName(),
                'identifier' => $identifier
            ];
            $configList[] = $config;
        }
        return $configList;
    }

    protected function handleEndedProcess(ProcessContext $context, bool $postProcessingNoDomain = false)
    {
        $process = $context->getProcess();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $criticalCss = $process->getOutput();
        $criticalCss = $this->cssProcessor->process($criticalCss, $postProcessingNoDomain);
        $this->storage->saveCriticalCss($context->getIdentifier(), $criticalCss);
        $size = $this->storage->getFileSize($context->getIdentifier());
        if (!$size) {
            $size = '?';
        }
        $this->logger->info(
            sprintf('[%s:%s|%s] Finished: %s.css (%s bytes)',
                $context->getStoreCode(),
                $context->getProviderName(),
                $context->getOrigIdentifier(),
                $context->getIdentifier(),
                $size
            )
        );
    }

}
