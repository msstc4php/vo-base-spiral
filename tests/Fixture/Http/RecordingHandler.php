<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Http;

use Override;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;

/**
 * The end of a pipeline that only remembers the context it was handed.
 */
final class RecordingHandler implements HandlerInterface
{
    public ?CallContextInterface $context = null;

    #[Override]
    public function handle(CallContextInterface $context): string
    {
        $this->context = $context;

        return 'handled';
    }
}
