<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test;

use Illuminate\Contracts\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\Stub;
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
    private Registry&Stub $registry;
    private Filesystem&Stub $filesystem;
    private LoggerInterface&Stub $logger;
    private ProviderFactory $factory;
    private SystemInfo $systemInfo;

    protected function setUp(): void
    {
        $this->registry = $this->createStub(Registry::class);
        $this->filesystem = $this->createStub(Filesystem::class);
        $this->logger = $this->createStub(LoggerInterface::class);
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
        $newLogger = $this->createStub(LoggerInterface::class);

        $this->factory->setLogger($newLogger);

        $this->assertSame($newLogger, $this->factory->getLogger());
    }

    public function testGetAndSetFilesystem(): void
    {
        $newFilesystem = $this->createStub(Filesystem::class);

        $this->factory->setFilesystem($newFilesystem);

        $this->assertSame($newFilesystem, $this->factory->getFilesystem());
    }

    public function testCreate(): void
    {
        // Set up
        $registry = $this->createMock(Registry::class);
        $category = $this->createMock(CategoryRegister::class);
        $provider = $this->createStub(ProviderRegister::class);
        $dataSet = new TestDataSet([]);
        $factory = new ProviderFactory($registry, $this->filesystem, $this->logger, $this->systemInfo);

        $registry->expects(self::once())->method('getCategory')->willReturn($category);

        $category->expects($this->once())->method('getProvider')->willReturn($provider);

        $provider->method('getClass')->willReturn(TestProvider::class);

        // Invoke factory
        $result = $factory->create($category, $provider, $dataSet);

        // Check returned object
        $this->assertSame($provider, $result->getRegister());
        $this->assertInstanceOf(TestProvider::class, $result->getInstance());
    }
}
