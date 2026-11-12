<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Integration;

use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Spiral\Bootloader\ValueObjectBootloader;
use MSSTC4PHP\ValueObject\Spiral\Filter\ValueObjectCaster;
use MSSTC4PHP\ValueObject\Spiral\Interceptor\ValueObjectArgumentsInterceptor;
use MSSTC4PHP\ValueObject\Spiral\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Http\GlobalInterceptorBootloader;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Http\HttpTestBootloader;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Kernel\TestKernel;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Route arguments through the real router and domain core, with the interceptor registered globally or per route.
 */
#[CoversClass(ValueObjectArgumentsInterceptor::class)]
#[UsesClass(ValueObjectBootloader::class)]
#[UsesClass(ValueObjectCaster::class)]
#[UsesClass(TypeLabel::class)]
final class InterceptorTest extends TestCase
{
    public function testARouteParameterBecomesTheValueObject(): void
    {
        self::assertSame(['type' => EmailAddress::class, 'value' => 'jane@example.com'], $this->json($this->global('/users/jane@example.com')));
        self::assertSame(['type' => Port::class, 'value' => 8080], $this->json($this->global('/ports/8080')));
    }

    public function testAnInvalidRouteParameterIsNotFound(): void
    {
        $response = $this->global('/users/nope');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringNotContainsString('MSSTC4PHP', (string) $response->getBody());
    }

    public function testAnOptionalParameterThatIsAbsentIsNull(): void
    {
        self::assertSame(['type' => 'null', 'value' => null], $this->json($this->global('/optional')));
        self::assertSame(['type' => EmailAddress::class, 'value' => 'jane@example.com'], $this->json($this->global('/optional/jane@example.com')));
    }

    public function testAUnionTypedParameterIsLeftAlone(): void
    {
        self::assertSame(['type' => 'string', 'value' => 'nope'], $this->json($this->global('/union/nope')));
    }

    public function testABuiltinParameterIsLeftAlone(): void
    {
        self::assertSame(['type' => 'string', 'value' => 'jane'], $this->json($this->global('/plain/jane')));
    }

    public function testAnEnumParameterIsLeftToSpiral(): void
    {
        // Spiral itself does not convert a string into a backed enum argument: its resolver rejects it (400).
        self::assertSame(400, $this->global('/countries/DE')->getStatusCode());
    }

    public function testAnUnbindableParameterIsAServerSideError(): void
    {
        self::assertSame(500, $this->global('/bounded/jane')->getStatusCode());
        self::assertSame(500, $this->global('/locations/52.5,13.4')->getStatusCode());
    }

    public function testTheInterceptorCanBeRegisteredForOneRoute(): void
    {
        $container = TestKernel::boot([ValueObjectBootloader::class, HttpTestBootloader::class]);

        $converted = TestKernel::handle($container, new ServerRequest('GET', '/per-route/users/jane@example.com'));
        $invalid = TestKernel::handle($container, new ServerRequest('GET', '/per-route/users/nope'));
        $elsewhere = TestKernel::handle($container, new ServerRequest('GET', '/users/jane@example.com'));

        self::assertSame(['type' => EmailAddress::class, 'value' => 'jane@example.com'], $this->json($converted));
        self::assertSame(404, $invalid->getStatusCode());
        self::assertSame(400, $elsewhere->getStatusCode(), 'Without the interceptor Spiral refuses the string for the typed argument.');
    }

    private function global(string $path): ResponseInterface
    {
        $container = TestKernel::boot([ValueObjectBootloader::class, HttpTestBootloader::class, GlobalInterceptorBootloader::class]);

        return TestKernel::handle($container, new ServerRequest('GET', $path));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(ResponseInterface $response): array
    {
        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $decoded = json_decode((string) $response->getBody(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
