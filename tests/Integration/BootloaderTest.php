<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Integration;

use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Spiral\Bootloader\ValueObjectBootloader;
use MSSTC4PHP\ValueObject\Spiral\Filter\ValueObjectCaster;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Kernel\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Spiral\Filters\Model\FilterProviderInterface;
use Spiral\Filters\Model\Mapper\CasterRegistryInterface;
use Spiral\Filters\Model\Mapper\EnumCaster;

#[CoversClass(ValueObjectBootloader::class)]
#[UsesClass(ValueObjectCaster::class)]
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

    public function testTheCasterIsRegisteredAfterSpiralsOwnCasters(): void
    {
        $container = TestKernel::boot([ValueObjectBootloader::class]);

        $registry = $container->get(CasterRegistryInterface::class);
        self::assertInstanceOf(CasterRegistryInterface::class, $registry);
        $casters = $registry->getCasters();

        self::assertInstanceOf(EnumCaster::class, $casters[0] ?? null);
        self::assertInstanceOf(ValueObjectCaster::class, $casters[array_key_last($casters)] ?? null);
    }
}
