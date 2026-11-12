<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Bootloader;

use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\Security\FiltersBootloader;

/**
 * Binds the shared ValueObjectMapper; an application bootloader listed after this one rebinds it to add codecs.
 */
final class ValueObjectBootloader extends Bootloader
{
    protected const array DEPENDENCIES = [FiltersBootloader::class];

    protected const array SINGLETONS = [ValueObjectMapper::class => ValueObjectMapper::class];
}
