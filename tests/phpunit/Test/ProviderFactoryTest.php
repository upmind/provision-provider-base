<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test;

use Illuminate\Contracts\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Upmind\ProvisionBase\Provider\DataSet\SystemInfo;
use Upmind\ProvisionBase\ProviderFactory;
use Upmind\ProvisionBase\Registry\Data\CategoryRegister;
use Upmind\ProvisionBase\Registry\Data\ProviderRegister;
use Upmind\ProvisionBase\Registry\Registry;
use Upmind\ProvisionBase\TestStub\TestDataSet;
use Upmind\ProvisionBase\TestStub\TestProvider;
use Upmind\ProvisionBase\TestStub\TestSystemInfo;

class ProviderFactoryTest extends TestCase
{
    private MockObject $registry;
    private MockObject $filesystem;
    private MockObject $logger;
    private ProviderFactory $factory;
    private SystemInfo $systemInfo;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(Registry::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->systemInfo = new TestSystemInfo();

        $this->factory = new ProviderFactory(
            $this->registry,
            $this->filesystem,
            $this->logger,
            $this->systemInfo
        );
    }

    public function testGetAndSetLogger(): void
    {
        $newLogger = $this->createMock(LoggerInterface::class);

        $this->factory->setLogger($newLogger);

        $this->assertSame($newLogger, $this->factory->getLogger());
    }

    public function testGetAndSetFilesystem(): void
    {
        $newFilesystem = $this->createMock(Filesystem::class);

        $this->factory->setFilesystem($newFilesystem);

        $this->assertSame($newFilesystem, $this->factory->getFilesystem());
    }

    public function testCreate(): void
    {
        // Set up
        $category = $this->createMock(CategoryRegister::class);
        $provider = $this->createMock(ProviderRegister::class);
        $dataSet = new TestDataSet([]);

        $this->registry->expects(self::once())->method('getCategory')->willReturn($category);

        $category->expects($this->once())->method('getProvider')->willReturn($provider);

        $provider->expects($this->any())->method('getClass')->willReturn(TestProvider::class);

        // Invoke factory
        $result = $this->factory->create($category, $provider, $dataSet);

        // Check returned object
        $this->assertSame($provider, $result->getRegister());
        $this->assertInstanceOf(TestProvider::class, $result->getInstance());
    }
}
