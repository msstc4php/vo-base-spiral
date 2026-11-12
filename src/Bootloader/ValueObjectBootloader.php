<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Bootloader;

use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Spiral\Filter\ValueObjectCaster;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\Security\FiltersBootloader;
use Spiral\Filters\Model\Mapper\CasterRegistryInterface;

/**
 * Binds the shared ValueObjectMapper and registers the filter caster. An application bootloader listed after this
 * one may rebind the mapper to add codecs: Spiral runs every bootloader's init phase before any boot().
 */
final class ValueObjectBootloader extends Bootloader
{
    protected const array DEPENDENCIES = [FiltersBootloader::class];

    protected const array SINGLETONS = [ValueObjectMapper::class => ValueObjectMapper::class];

    public function boot(CasterRegistryInterface $casters, ValueObjectMapper $mapper): void
    {
        $casters->register(new ValueObjectCaster($mapper));
    }
}
