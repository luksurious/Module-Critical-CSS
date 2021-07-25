<?php

namespace M2Boilerplate\CriticalCss\Model;

use M2Boilerplate\CriticalCss\Service\Identifier;
use Symfony\Component\Process\Process;

class ProcessContext
{
    /**
     * @var Process
     */
    protected $process;
    /**
     * @var string
     */
    protected $identifier;
    /**
     * @var Identifier
     */
    protected $identifierService;
    /**
     * @var string
     */
    protected $providerName;
    /**
     * @var string
     */
    protected $storeCode;

    public function __construct(
        Process $process,
        string $providerName,
        string $storeCode,
        Identifier $identifierService,
        string $identifier
    ) {
        $this->process = $process;
        $this->identifier = $identifier;
        $this->identifierService = $identifierService;
        $this->storeCode = $storeCode;
        $this->providerName = $providerName;
    }

    /**
     * @return string
     */
    public function getStoreCode()
    {
        return $this->storeCode;
    }

    /**
     * @return string
     */
    public function getProviderName()
    {
        return $this->providerName;
    }

    /**
     * @return Process
     */
    public function getProcess()
    {
        return $this->process;
    }

    public function getOrigIdentifier()
    {
        return $this->identifier;
    }

    public function getIdentifier()
    {
        return $this->identifierService->generateIdentifierFromConfig(
            $this->providerName,
            $this->storeCode,
            $this->identifier
        );
    }

}