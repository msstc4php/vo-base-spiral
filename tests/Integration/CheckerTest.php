<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Integration;

use LogicException;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\QueryParameter;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use MSSTC4PHP\ValueObject\Spiral\Bootloader\ValueObjectBootloader;
use MSSTC4PHP\ValueObject\Spiral\Filter\ValueObjectCaster;
use MSSTC4PHP\ValueObject\Spiral\Interceptor\ValueObjectArgumentsInterceptor;
use MSSTC4PHP\ValueObject\Spiral\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Http\HttpTestBootloader;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Kernel\TestKernel;
use MSSTC4PHP\ValueObject\Spiral\Validation\ValueObjectChecker;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Spiral\Validation\ValidationInterface;
use Spiral\Validation\ValidationProviderInterface;
use Spiral\Validator\Bootloader\ValidatorBootloader;
use Spiral\Validator\FilterDefinition;

/**
 * The vo:valid rule as an application uses it: a filter definition validated by spiral/validator, through HTTP.
 */
#[CoversClass(ValueObjectChecker::class)]
#[UsesClass(ValueObjectBootloader::class)]
#[UsesClass(ValueObjectCaster::class)]
#[UsesClass(ValueObjectArgumentsInterceptor::class)]
#[UsesClass(TypeLabel::class)]
#[RequiresMethod(ValidatorBootloader::class, 'addChecker')]
final class CheckerTest extends TestCase
{
    public function testValidInputPasses(): void
    {
        $response = $this->post(['email' => 'jane@example.com', 'port' => '8080', 'location' => '{"lat":52.52,"lng":13.405}']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    }

    public function testMissingNullAndEmptyInputPass(): void
    {
        $response = $this->post(['email' => null, 'port' => '']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    }

    public function testInvalidInputIsAFieldErrorWithoutAClassName(): void
    {
        $response = $this->post(['email' => 'nope', 'port' => '70000', 'location' => ['lat' => 91, 'lng' => 0]]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['errors' => [
            'email' => 'The value is not a valid email address.',
            'port' => 'The value is not a valid port.',
            'location' => 'The value is not a valid geo coordinates.',
        ]], json_decode((string) $response->getBody(), true, flags: \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('MSSTC4PHP', (string) $response->getBody());
    }

    public function testAnArrayWhereAStringIsExpectedIsAFieldError(): void
    {
        $response = $this->post(['email' => ['jane@example.com']]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['errors' => ['email' => 'The value is not a valid email address.']], json_decode((string) $response->getBody(), true, flags: \JSON_THROW_ON_ERROR));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int, array<string, mixed>}>
     */
    public static function readmeExample(): iterable
    {
        yield 'valid' => [['email' => 'jane@example.com'], 200, ['email' => ['type' => 'string', 'value' => 'jane@example.com']]];
        yield 'invalid' => [['email' => 'nope'], 422, ['errors' => ['email' => 'The value is not a valid email address.']]];
        yield 'missing, required speaks' => [[], 422, ['errors' => ['email' => 'This value is required.']]];
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $expected
     */
    #[DataProvider('readmeExample')]
    public function testTheReadmeExampleWorksAsDocumented(array $body, int $status, array $expected): void
    {
        $container = TestKernel::boot([ValidatorBootloader::class, ValueObjectBootloader::class, HttpTestBootloader::class]);

        $response = TestKernel::handle($container, new ServerRequest('POST', '/readme-contact')->withParsedBody($body));

        self::assertSame($status, $response->getStatusCode());
        self::assertSame($expected, json_decode((string) $response->getBody(), true, flags: \JSON_THROW_ON_ERROR));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function refusedClasses(): iterable
    {
        yield 'trusted-source only' => [BoundedString::class];
        yield 'without a payload form' => [QueryParameter::class];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('refusedClasses')]
    public function testARuleForAClassRequestInputCannotBuildIsAProgrammerErrorEvenForAnAbsentField(string $class): void
    {
        $validator = $this->validation()->validate([], ['field' => [['vo:valid', $class]]]);

        $this->expectException(LogicException::class);

        $validator->isValid();
    }

    public function testTheRuleCanCarryItsOwnMessage(): void
    {
        $validator = $this->validation()->validate(['email' => 'nope'], ['email' => [['vo:valid', EmailAddress::class, 'error' => 'Enter an email address.']]]);

        self::assertSame(['email' => 'Enter an email address.'], $validator->getErrors());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(array $body): ResponseInterface
    {
        $container = TestKernel::boot([ValidatorBootloader::class, ValueObjectBootloader::class, HttpTestBootloader::class]);

        return TestKernel::handle($container, new ServerRequest('POST', '/contact')->withParsedBody($body));
    }

    private function validation(): ValidationInterface
    {
        $provider = TestKernel::boot([ValidatorBootloader::class, ValueObjectBootloader::class])->get(ValidationProviderInterface::class);
        self::assertInstanceOf(ValidationProviderInterface::class, $provider);

        return $provider->getValidation(FilterDefinition::class);
    }
}
