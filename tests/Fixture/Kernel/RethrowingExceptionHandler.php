<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Kernel;

use Override;
use Spiral\Exceptions\ExceptionHandler;
use Throwable;

/**
 * The default handler prints a boot failure to STDERR and lets run() return null; a test wants the exception.
 */
final class RethrowingExceptionHandler extends ExceptionHandler
{
    #[Override]
    public function handleGlobalException(Throwable $e): void
    {
        throw $e;
    }
}
