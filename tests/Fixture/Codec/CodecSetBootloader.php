<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Codec;

use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use Override;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * An application bootloader rebinding the mapper with a ProvidesCodecs set, as the README's comment line shows.
 */
final class CodecSetBootloader extends Bootloader
{
    #[Override]
    public function defineSingletons(): array
    {
        return [ValueObjectMapper::class => static fn (): ValueObjectMapper => new ValueObjectMapper(AppCodecs::all())];
    }
}
