<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Bootloader;

use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Spiral\Filter\ValueObjectCaster;
use MSSTC4PHP\ValueObject\Spiral\Validation\ValueObjectChecker;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\Security\FiltersBootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Filters\Model\Mapper\CasterRegistryInterface;
use Spiral\Validator\Config\ValidatorConfig;

/**
 * Binds the shared ValueObjectMapper, registers the filter caster and, when spiral/validator's ValidatorBootloader is
 * loaded, the `vo` checker. An application bootloader listed after this one may rebind the mapper to add codecs:
 * Spiral runs every bootloader's init phase before any boot().
 */
final class ValueObjectBootloader extends Bootloader
{
    public const string CHECKER_ALIAS = 'vo';

    protected const array DEPENDENCIES = [FiltersBootloader::class];

    protected const array SINGLETONS = [ValueObjectMapper::class => ValueObjectMapper::class];

    /**
     * @param ConfiguratorInterface<object> $config
     */
    public function boot(CasterRegistryInterface $casters, ValueObjectMapper $mapper, ConfiguratorInterface $config): void
    {
        $casters->register(new ValueObjectCaster($mapper));

        // The validator config exists once ValidatorBootloader's init() has set its defaults; without it (another
        // validation library, or spiral/validator not installed) there is no checker to add.
        if (class_exists(ValidatorConfig::class) && $config->exists(ValidatorConfig::CONFIG)) {
            $config->modify(ValidatorConfig::CONFIG, new Append('checkers', self::CHECKER_ALIAS, ValueObjectChecker::class));
        }
    }
}
