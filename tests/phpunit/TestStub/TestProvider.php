<?php

namespace Upmind\ProvisionBase\TestStub;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Upmind\ProvisionBase\Provider\Contract\ProviderInterface;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;
use Upmind\ProvisionBase\Provider\DataSet\SystemInfo;

class TestProvider extends AbstractTestProvider implements ProviderInterface
{
    use LoggerAwareTrait;

    public function __construct(TestDataSet $configuration)
    {
    }

    public static function aboutCategory(): AboutData
    {
        return new AboutData([
            'name' => 'Test Category',
            'description' => 'A test category'
        ]);
    }

    public static function aboutProvider(): AboutData
    {
        return new AboutData([
            'name' => 'Test Provider',
            'description' => 'A test provider'
        ]);
    }

    public function setSystemInfo(SystemInfo $systemInfo): void
    {
    }

    public function getSystemInfo(): SystemInfo
    {
        return new SystemInfo();
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
