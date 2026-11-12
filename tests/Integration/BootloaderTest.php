<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Integration;

use LogicException;
use MSSTC4PHP\ValueObject\Network\QueryParameter;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Spiral\Bootloader\ValueObjectBootloader;
use MSSTC4PHP\ValueObject\Spiral\Filter\ValueObjectCaster;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Codec\CodecBootloader;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Filter\CastTarget;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Kernel\TestKernel;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Kernel\ValidatorConfigReaderBootloader;
use MSSTC4PHP\ValueObject\Spiral\Validation\ValueObjectChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionNamedType;
use ReflectionProperty;
use Spiral\Boot\Bootloader\BootloaderInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Filters\Model\FilterProviderInterface;
use Spiral\Filters\Model\Mapper\CasterRegistryInterface;
use Spiral\Filters\Model\Mapper\EnumCaster;
use Spiral\Validator\Bootloader\ValidatorBootloader;
use Spiral\Validator\Config\ValidatorConfig;

#[CoversClass(ValueObjectBootloader::class)]
#[UsesClass(ValueObjectCaster::class)]
#[UsesClass(ValueObjectChecker::class)]
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

    #[RequiresMethod(ValidatorBootloader::class, 'addChecker')]
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

        self::assertFalse($config->exists('validator'));
    }

    #[RequiresMethod(ValidatorBootloader::class, 'addChecker')]
    public function testAMapperReboundByALaterBootloaderReachesTheCasterAndTheChecker(): void
    {
        $container = TestKernel::boot([ValidatorBootloader::class, ValueObjectBootloader::class, CodecBootloader::class]);

        $registry = $container->get(CasterRegistryInterface::class);
        self::assertInstanceOf(CasterRegistryInterface::class, $registry);
        $caster = $registry->getCasters()[array_key_last($registry->getCasters())] ?? null;
        $type = new ReflectionProperty(CastTarget::class, 'parameter')->getType();
        $checker = $container->get(ValueObjectChecker::class);

        self::assertInstanceOf(ValueObjectCaster::class, $caster);
        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertTrue($caster->supports($type));
        self::assertInstanceOf(ValueObjectChecker::class, $checker);
        self::assertTrue($checker->valid('{"name":"page","value":"2"}', QueryParameter::class));
    }

    /**
     * @return iterable<string, array{list<class-string<BootloaderInterface>>, list<class-string<BootloaderInterface>>}>
     */
    public static function separateLists(): iterable
    {
        yield 'validator among the app bootloaders' => [[ValueObjectBootloader::class], [ValidatorBootloader::class]];
        yield 'this package among the app bootloaders' => [[ValidatorBootloader::class], [ValueObjectBootloader::class]];
    }

    /**
     * @param list<class-string<BootloaderInterface>> $bootloaders
     * @param list<class-string<BootloaderInterface>> $appBootloaders
     */
    #[RequiresMethod(ValidatorBootloader::class, 'addChecker')]
    #[DataProvider('separateLists')]
    public function testTheCheckerIsRegisteredWhenTheValidatorIsBootedInAnotherList(array $bootloaders, array $appBootloaders): void
    {
        $container = TestKernel::boot($bootloaders, $appBootloaders);

        $config = $container->get(ConfiguratorInterface::class);
        self::assertInstanceOf(ConfiguratorInterface::class, $config);
        $checkers = $config->getConfig('validator')['checkers'] ?? null;

        self::assertIsArray($checkers);
        self::assertSame(ValueObjectChecker::class, $checkers['vo'] ?? null);
    }

    #[RequiresMethod(ValidatorBootloader::class, 'addChecker')]
    public function testAValidatorConfigDeliveredBeforeTheDeferredRegistrationIsALogicExceptionWithTheRemedy(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('List ValueObjectBootloader in the same bootloader list as ValidatorBootloader');

        TestKernel::boot([ValueObjectBootloader::class], [ValidatorBootloader::class, ValidatorConfigReaderBootloader::class]);
    }
}
