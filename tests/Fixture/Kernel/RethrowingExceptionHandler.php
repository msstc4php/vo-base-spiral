<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Kernel;

use Override;
use Spiral\Exceptions\ExceptionHandler;
use Throwable;

/**
 * The default handler prints a boot failure to STDERR and lets run() return null; a test wants the exception. It also
 * keeps what ErrorHandlerMiddleware reports, so a test can see the exception behind a 500.
 */
final class RethrowingExceptionHandler extends ExceptionHandler
{
    /**
     * @var list<Throwable>
     */
    public array $reported = [];

    #[Override]
    public function report(Throwable $exception): void
    {
        $this->reported[] = $exception;
        parent::report($exception);
    }

    #[Override]
    public function handleGlobalException(Throwable $e): void
    {
        throw $e;
    }
}
