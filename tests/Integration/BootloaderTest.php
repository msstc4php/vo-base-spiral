<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Integration;

use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Spiral\Bootloader\ValueObjectBootloader;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Kernel\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Spiral\Filters\Model\FilterProviderInterface;

#[CoversClass(ValueObjectBootloader::class)]
final class BootloaderTest extends TestCase
{
    public function testTheMapperIsAContainerSingleton(): void
    {
        $container = TestKernel::boot([ValueObjectBootloader::class]);

        $mapper = $container->get(ValueObjectMapper::class);

        self::assertInstanceOf(ValueObjectMapper::class, $mapper);
        self::assertSame($mapper, $container->get(ValueObjectMapper::class));
    }

    public function testItLoadsTheFiltersBootloader(): void
    {
        $container = TestKernel::boot([ValueObjectBootloader::class]);

        self::assertTrue($container->has(FilterProviderInterface::class));
    }
}
