<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Kernel;

use Override;
use Spiral\Boot\Bootloader\BootloaderInterface;
use Spiral\Core\Container;
use Spiral\Framework\Kernel;

final class TestKernel extends Kernel
{
    /**
     * @var list<class-string<BootloaderInterface>>
     */
    private static array $bootloaders = [];

    /**
     * A booted application container; nothing is written to disk.
     *
     * @param list<class-string<BootloaderInterface>> $bootloaders
     */
    public static function boot(array $bootloaders): Container
    {
        self::$bootloaders = $bootloaders;
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
     * @return list<class-string<BootloaderInterface>>
     */
    #[Override]
    protected function defineBootloaders(): array
    {
        return self::$bootloaders;
    }
}
