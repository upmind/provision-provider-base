<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Provider;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Upmind\ProvisionBase\Provider\BaseCategory;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;

class BaseCategoryTest extends TestCase
{
    public function testGetLoggerReturnsNullLoggerWhenNotSet(): void
    {
        $this->assertInstanceOf(NullLogger::class, $this->makeCategory()->getLogger());
    }

    public function testGetLoggerReturnsSetLogger(): void
    {
        $logger = new NullLogger();
        $category = $this->makeCategory();
        $category->setLogger($logger);

        $this->assertSame($logger, $category->getLogger());
    }

    private function makeCategory(): BaseCategory
    {
        return new class extends BaseCategory {
            public static function aboutCategory(): AboutData
            {
                return new AboutData();
            }
        };
    }
}
