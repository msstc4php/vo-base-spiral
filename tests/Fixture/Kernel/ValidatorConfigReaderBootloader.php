<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Kernel;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Validator\Config\ValidatorConfig;

/**
 * An app bootloader that takes the validator config in boot(), which freezes it before appBooted() callbacks run.
 */
final class ValidatorConfigReaderBootloader extends Bootloader
{
    public function boot(ValidatorConfig $config): void
    {
        $config->hasChecker('type');
    }
}
