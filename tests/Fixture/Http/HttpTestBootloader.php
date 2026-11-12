<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Http;

use MSSTC4PHP\ValueObject\Spiral\Interceptor\ValueObjectArgumentsInterceptor;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\Attributes\AttributesBootloader;
use Spiral\Bootloader\Http\ErrorHandlerBootloader;
use Spiral\Bootloader\Http\HttpBootloader;
use Spiral\Bootloader\Http\RouterBootloader;
use Spiral\Filter\ValidationHandlerMiddleware;
use Spiral\Http\Middleware\ErrorHandlerMiddleware;
use Spiral\Interceptors\Handler\AutowireHandler;
use Spiral\Interceptors\PipelineBuilder;
use Spiral\Router\Route;
use Spiral\Router\RouterInterface;
use Spiral\Router\Target\Action;

/**
 * The HTTP stack of a Spiral application: PSR-17 factories, the error and validation middleware, the test routes.
 */
final class HttpTestBootloader extends Bootloader
{
    protected const array DEPENDENCIES = [
        AttributesBootloader::class,
        HttpBootloader::class,
        RouterBootloader::class,
        ErrorHandlerBootloader::class,
    ];

    protected const array SINGLETONS = [
        ResponseFactoryInterface::class => Psr17Factory::class,
        ServerRequestFactoryInterface::class => Psr17Factory::class,
        StreamFactoryInterface::class => Psr17Factory::class,
        UploadedFileFactoryInterface::class => Psr17Factory::class,
        UriFactoryInterface::class => Psr17Factory::class,
    ];

    public function boot(
        HttpBootloader $http,
        RouterInterface $router,
        ContainerInterface $container,
        ValueObjectArgumentsInterceptor $interceptor,
    ): void {
        $http->addMiddleware(ErrorHandlerMiddleware::class);
        $http->addMiddleware(ValidationHandlerMiddleware::class);

        $router->setRoute('signup', new Route('/signup', new Action(FilterController::class, 'signup')));

        foreach ([
            'email' => '/users/<email>',
            'port' => '/ports/<port>',
            'optional' => '/optional[/<email>]',
            'union' => '/union/<id>',
            'country' => '/countries/<country>',
            'plain' => '/plain/<name>',
            'bounded' => '/bounded/<name>',
            'location' => '/locations/<location>',
        ] as $action => $pattern) {
            $router->setRoute($action, new Route($pattern, new Action(RouteController::class, $action)));
        }

        $pipeline = new PipelineBuilder()->withInterceptors($interceptor)->build(new AutowireHandler($container));
        $router->setRoute('per-route', new Route('/per-route/users/<email>', new Action(RouteController::class, 'email')->withHandler($pipeline)));
    }
}
