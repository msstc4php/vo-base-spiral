<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Http;

use MSSTC4PHP\ValueObject\Spiral\Interceptor\ValueObjectArgumentsInterceptor;
use Spiral\Bootloader\DomainBootloader;
use Spiral\Core\CoreInterface;

/**
 * The interceptor on every route, the way a Spiral application configures its domain core.
 */
final class GlobalInterceptorBootloader extends DomainBootloader
{
    protected const array SINGLETONS = [CoreInterface::class => [self::class, 'domainCore']];

    protected const array INTERCEPTORS = [ValueObjectArgumentsInterceptor::class];
}
