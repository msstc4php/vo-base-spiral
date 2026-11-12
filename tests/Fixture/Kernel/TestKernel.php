<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Kernel;

use LogicException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Boot\Bootloader\BootloaderInterface;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Spiral\Framework\Kernel;
use Spiral\Http\Http;

final class TestKernel extends Kernel
{
    /**
     * @var list<class-string<BootloaderInterface>>
     */
    private static array $bootloaders = [];

    /**
     * @var list<class-string<BootloaderInterface>>
     */
    private static array $appBootloaders = [];

    /**
     * A booted application container; nothing is written to disk.
     *
     * @param list<class-string<BootloaderInterface>> $bootloaders defineBootloaders()
     * @param list<class-string<BootloaderInterface>> $appBootloaders defineAppBootloaders(), a separate bootload() call
     */
    public static function boot(array $bootloaders, array $appBootloaders = []): Container
    {
        self::$bootloaders = $bootloaders;
        self::$appBootloaders = $appBootloaders;
        $container = new Container();
        self::create(
            ['root' => sys_get_temp_dir() . '/vo-base-spiral-tests'],
            handleErrors: false,
            exceptionHandler: RethrowingExceptionHandler::class,
            container: $container,
        )->run();

        return $container;
    }

    /**
     * A request through the whole HTTP stack, as the dispatcher runs it.
     */
    public static function handle(Container $container, ServerRequestInterface $request): ResponseInterface
    {
        $response = $container->runScope(new Scope('http'), static fn (Http $http): ResponseInterface => $http->handle($request));

        return $response instanceof ResponseInterface ? $response : throw new LogicException('The HTTP scope returned no response.');
    }

    /**
     * @return list<class-string<BootloaderInterface>>
     */
    #[Override]
    protected function defineBootloaders(): array
    {
        return self::$bootloaders;
    }

    /**
     * @return list<class-string<BootloaderInterface>>
     */
    #[Override]
    protected function defineAppBootloaders(): array
    {
        return self::$appBootloaders;
    }
}
