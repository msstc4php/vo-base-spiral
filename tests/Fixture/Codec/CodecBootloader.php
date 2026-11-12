<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Codec;

use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use Override;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * An application bootloader rebinding the mapper with its own codec, as the README shows.
 */
final class CodecBootloader extends Bootloader
{
    #[Override]
    public function defineSingletons(): array
    {
        return [ValueObjectMapper::class => static fn (): ValueObjectMapper => new ValueObjectMapper([new QueryParameterCodec()])];
    }
}
