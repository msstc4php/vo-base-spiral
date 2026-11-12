<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Bootloader;

use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Spiral\Filter\ValueObjectCaster;
use MSSTC4PHP\ValueObject\Spiral\Validation\ValueObjectChecker;
use Spiral\Boot\AbstractKernel;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\Security\FiltersBootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Filters\Model\Mapper\CasterRegistryInterface;
use Spiral\Framework\Kernel;
use Spiral\Validator\Config\ValidatorConfig;

/**
 * Binds the shared ValueObjectMapper, registers the filter caster and, when spiral/validator's ValidatorBootloader is
 * loaded (in any of the kernel's bootloader lists), the `vo` checker. An application bootloader listed after this one
 * may rebind the mapper to add codecs: Spiral runs every bootloader's init phase before any boot().
 */
final class ValueObjectBootloader extends Bootloader
{
    public const string CHECKER_ALIAS = 'vo';

    protected const array DEPENDENCIES = [FiltersBootloader::class];

    protected const array SINGLETONS = [ValueObjectMapper::class => ValueObjectMapper::class];

    /**
     * @param ConfiguratorInterface<object> $config
     */
    public function boot(CasterRegistryInterface $casters, ValueObjectMapper $mapper, ConfiguratorInterface $config, AbstractKernel $kernel): void
    {
        $casters->register(new ValueObjectCaster($mapper));

        if (!class_exists(ValidatorConfig::class)) {
            return;
        }

        if ($config->exists(ValidatorConfig::CONFIG)) {
            self::registerChecker($config);

            return;
        }

        // ValidatorBootloader may be in another bootload() list (the kernel's app bootloaders); its config defaults
        // exist only once that list is initialized.
        $registerLater = static function () use ($config): void {
            if ($config->exists(ValidatorConfig::CONFIG)) {
                self::registerChecker($config);
            }
        };
        $kernel instanceof Kernel ? $kernel->appBooted($registerLater) : $kernel->booted($registerLater);
    }

    /**
     * @param ConfiguratorInterface<object> $config
     */
    private static function registerChecker(ConfiguratorInterface $config): void
    {
        $config->modify(ValidatorConfig::CONFIG, new Append('checkers', self::CHECKER_ALIAS, ValueObjectChecker::class));
    }
}
