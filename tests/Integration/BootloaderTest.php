<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Integration;

use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Spiral\Bootloader\ValueObjectBootloader;
use MSSTC4PHP\ValueObject\Spiral\Filter\ValueObjectCaster;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Kernel\TestKernel;
use MSSTC4PHP\ValueObject\Spiral\Validation\ValueObjectChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Filters\Model\FilterProviderInterface;
use Spiral\Filters\Model\Mapper\CasterRegistryInterface;
use Spiral\Filters\Model\Mapper\EnumCaster;
use Spiral\Validator\Bootloader\ValidatorBootloader;
use Spiral\Validator\Config\ValidatorConfig;

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

    public function testTheCheckerIsRegisteredUnderVoWhenTheValidatorIsBooted(): void
    {
        $container = TestKernel::boot([ValidatorBootloader::class, ValueObjectBootloader::class]);

        $config = $container->get(ConfiguratorInterface::class);
        self::assertInstanceOf(ConfiguratorInterface::class, $config);
        $checkers = $config->getConfig(ValidatorConfig::CONFIG)['checkers'] ?? null;

        self::assertIsArray($checkers);
        self::assertSame(ValueObjectChecker::class, $checkers['vo'] ?? null);
        self::assertArrayHasKey('type', $checkers, 'The default checkers stay.');
    }

    public function testWithoutTheValidatorBootloaderNoValidatorConfigIsCreated(): void
    {
        $container = TestKernel::boot([ValueObjectBootloader::class]);

        $config = $container->get(ConfiguratorInterface::class);
        self::assertInstanceOf(ConfiguratorInterface::class, $config);

        self::assertFalse($config->exists(ValidatorConfig::CONFIG));
    }
}
