<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Integration;

use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\I18n\CountryCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Primitive\Percentage;
use MSSTC4PHP\ValueObject\Spiral\Bootloader\ValueObjectBootloader;
use MSSTC4PHP\ValueObject\Spiral\Filter\ValueObjectCaster;
use MSSTC4PHP\ValueObject\Spiral\Interceptor\ValueObjectArgumentsInterceptor;
use MSSTC4PHP\ValueObject\Spiral\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Code\LongCode;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Http\HttpTestBootloader;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Kernel\TestKernel;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * A filter property typed with a value object, through the whole HTTP stack: input → filter → 422 or the action.
 */
#[CoversClass(ValueObjectCaster::class)]
#[UsesClass(ValueObjectArgumentsInterceptor::class)]
#[UsesClass(ValueObjectBootloader::class)]
#[UsesClass(TypeLabel::class)]
final class FilterTest extends TestCase
{
    public function testAValidValueBecomesTheValueObject(): void
    {
        $response = $this->post(['email' => 'Jane@Example.com', 'port' => '8080', 'share' => '12.5']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'email' => ['type' => EmailAddress::class, 'value' => 'Jane@Example.com'],
            'port' => ['type' => Port::class, 'value' => 8080],
            'share' => ['type' => Percentage::class, 'value' => 12.5],
            'location' => ['type' => 'null', 'value' => null],
            'country' => ['type' => 'null', 'value' => null],
            'nickname' => ['type' => 'null', 'value' => null],
            'code' => ['type' => 'null', 'value' => null],
        ], $this->json($response));
    }

    public function testAnInvalidValueIsAFieldErrorWithoutAClassName(): void
    {
        $response = $this->post(['email' => 'nope']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['errors' => ['email' => 'The value is not a valid email address.']], $this->json($response));
        self::assertStringNotContainsString('MSSTC4PHP', (string) $response->getBody());
    }

    public function testAnArrayWhereAStringIsExpectedIsAFieldError(): void
    {
        $response = $this->post(['email' => ['jane@example.com']]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['errors' => ['email' => 'The value is not a valid email address.']], $this->json($response));
    }

    public function testErrorsOfSeveralFieldsAreReportedTogether(): void
    {
        $response = $this->post(['email' => 'nope', 'port' => '70000', 'share' => 'much']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['errors' => [
            'email' => 'The value is not a valid email address.',
            'port' => 'The value is not a valid port.',
            'share' => 'The value is not a valid percentage.',
        ]], $this->json($response));
    }

    public function testACompositeIsReadFromJsonTextOrAnArray(): void
    {
        $fromText = $this->json($this->post(['email' => 'jane@example.com', 'location' => '{"lat":52.52,"lng":13.405}']));
        $fromArray = $this->json($this->post(['email' => 'jane@example.com', 'location' => ['lat' => 52.52, 'lng' => 13.405]]));

        $expected = ['type' => GeoCoordinates::class, 'value' => ['lat' => 52.52, 'lng' => 13.405]];
        self::assertSame($expected, $fromText['location'] ?? null);
        self::assertSame($expected, $fromArray['location'] ?? null);
    }

    public function testAnAbstractTypeResolvingItsConcreteClassBecomesTheSubclassTheValueFits(): void
    {
        $valid = $this->post(['email' => 'jane@example.com', 'code' => 'abcd']);
        $invalid = $this->post(['email' => 'jane@example.com', 'code' => 'abcdefg']);

        self::assertSame(['type' => LongCode::class, 'value' => 'abcd'], $this->json($valid)['code'] ?? null);
        self::assertSame(422, $invalid->getStatusCode());
        self::assertSame(['errors' => ['code' => 'The value is not a valid code.']], $this->json($invalid));
    }

    public function testMalformedCompositeJsonIsAFieldError(): void
    {
        $response = $this->post(['email' => 'jane@example.com', 'location' => '{"lat":']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['errors' => ['location' => 'The value is not a valid geo coordinates.']], $this->json($response));
    }

    public function testATrustedSourceOnlyPropertyIsAlwaysAFieldError(): void
    {
        $response = $this->post(['email' => 'jane@example.com', 'nickname' => '{"value":"jane","minLength":0,"maxLength":4}']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['errors' => ['nickname' => 'A bounded string cannot be read from request input.']], $this->json($response));
    }

    public function testATrustedSourceOnlyPropertyRefusesAnEmptyStringToo(): void
    {
        $response = $this->post(['email' => 'jane@example.com', 'nickname' => '']);

        self::assertSame(['errors' => ['nickname' => 'A bounded string cannot be read from request input.']], $this->json($response));
    }

    public function testAnEnumPropertyIsStillSpiralsEnumCastersJob(): void
    {
        $valid = $this->post(['email' => 'jane@example.com', 'country' => 'DE']);
        $invalid = $this->post(['email' => 'jane@example.com', 'country' => 'XX']);

        self::assertSame(['type' => CountryCode::class, 'value' => 'DE'], $this->json($valid)['country'] ?? null);
        self::assertSame(422, $invalid->getStatusCode());
        $errors = $this->json($invalid)['errors'] ?? null;
        self::assertIsArray($errors);
        self::assertIsString($errors['country'] ?? null);
        self::assertStringStartsWith('Unable to set enum value.', $errors['country']);
    }

    public function testNullOrAnEmptyStringLeavesANullablePropertyNull(): void
    {
        $response = $this->post(['email' => 'jane@example.com', 'port' => null, 'share' => '']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['type' => 'null', 'value' => null], $this->json($response)['port'] ?? null);
        self::assertSame(['type' => 'null', 'value' => null], $this->json($response)['share'] ?? null);
    }

    public function testAnEmptyStringOnANonNullablePropertyIsAFieldError(): void
    {
        $response = $this->post(['email' => '']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['errors' => ['email' => 'The value is not a valid email address.']], $this->json($response));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(array $body): ResponseInterface
    {
        $container = TestKernel::boot([ValueObjectBootloader::class, HttpTestBootloader::class]);

        return TestKernel::handle($container, new ServerRequest('POST', '/signup')->withParsedBody($body));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
