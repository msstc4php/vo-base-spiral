<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Unit\Interceptor;

use LogicException;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use MSSTC4PHP\ValueObject\I18n\CountryCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Spiral\Interceptor\ValueObjectArgumentsInterceptor;
use MSSTC4PHP\ValueObject\Spiral\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Code\Code;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Code\LongCode;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Code\ShortCode;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Http\RecordingHandler;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Http\RouteController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\Exception\TargetCallException;

#[CoversClass(ValueObjectArgumentsInterceptor::class)]
#[UsesClass(TypeLabel::class)]
#[UsesClass(Code::class)]
final class ValueObjectArgumentsInterceptorTest extends TestCase
{
    public function testAScalarArgumentBecomesTheValueObject(): void
    {
        self::assertEquals(['email' => EmailAddress::from('jane@example.com')], $this->intercept('email', ['email' => 'jane@example.com']));
        self::assertEquals(['port' => Port::from(8080)], $this->intercept('port', ['port' => '8080']));
    }

    public function testABackedEnumArgumentBecomesTheCase(): void
    {
        self::assertSame(['country' => CountryCode::DE], $this->intercept('country', ['country' => 'DE']));
    }

    public function testAnAbstractTypeResolvingItsConcreteClassBecomesTheSubclassTheValueFits(): void
    {
        self::assertEquals(['code' => ShortCode::from('abc')], $this->intercept('code', ['code' => 'abc']));
        self::assertEquals(['code' => LongCode::from('abcd')], $this->intercept('code', ['code' => 'abcd']));
    }

    public function testTheOtherArgumentsAndAttributesAreKept(): void
    {
        $handler = new RecordingHandler();
        $context = new CallContext(Target::fromPair(RouteController::class, 'email'), ['email' => 'jane@example.com', 'extra' => 'x'], ['attr' => 1]);

        $result = new ValueObjectArgumentsInterceptor(new ValueObjectMapper())->intercept($context, $handler);

        self::assertSame('handled', $result);
        self::assertNotNull($handler->context);
        self::assertSame('x', $handler->context->getArguments()['extra'] ?? null);
        self::assertSame(1, $handler->context->getAttribute('attr'));
    }

    /**
     * @return iterable<string, array{non-empty-string, array<array-key, mixed>}>
     */
    public static function untouched(): iterable
    {
        $email = EmailAddress::from('jane@example.com');

        yield 'already an instance' => ['email', ['email' => $email]];
        yield 'absent' => ['email', []];
        yield 'null' => ['optional', ['email' => null]];
        yield 'union type' => ['union', ['id' => 'nope']];
        yield 'builtin' => ['plain', ['name' => 'jane']];
        yield 'positional, not by name' => ['email', [0 => 'nope']];
        yield 'a class the mapper does not read' => ['parameter', ['parameter' => 'page=2']];
    }

    /**
     * @param non-empty-string $action
     * @param array<array-key, mixed> $arguments
     */
    #[DataProvider('untouched')]
    public function testArgumentsItDoesNotBindAreLeftAlone(string $action, array $arguments): void
    {
        self::assertSame($arguments, $this->intercept($action, $arguments));
    }

    public function testAnEmptyStringForANullableArgumentIsNull(): void
    {
        self::assertSame(['email' => null], $this->intercept('optional', ['email' => '']));
    }

    /**
     * @return iterable<string, array{non-empty-string, array<string, mixed>}>
     */
    public static function invalid(): iterable
    {
        yield 'invalid content' => ['email', ['email' => 'nope']];
        yield 'empty string for a non-nullable argument' => ['email', ['email' => '']];
        yield 'not a canonical integer' => ['port', ['port' => '08080']];
        yield 'unknown enum case' => ['country', ['country' => 'XX']];
        yield 'abstract type, no subclass fits' => ['code', ['code' => 'abcdefg']];
    }

    /**
     * @param non-empty-string $action
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('invalid')]
    public function testAnInvalidArgumentIsNotFound(string $action, array $arguments): void
    {
        try {
            $this->intercept($action, $arguments);
            self::fail('Expected a TargetCallException.');
        } catch (TargetCallException $exception) {
            self::assertSame(TargetCallException::NOT_FOUND, $exception->getCode());
            self::assertStringNotContainsString('MSSTC4PHP', $exception->getMessage());
            self::assertInstanceOf(InvalidValueObjectException::class, $exception->getPrevious());
        }
    }

    public function testTheNotFoundMessageNamesTheArgumentAndTheLabel(): void
    {
        $this->expectExceptionMessage('The route argument "port" is not a valid port.');

        $this->intercept('port', ['port' => 'x']);
    }

    /**
     * @return iterable<string, array{non-empty-string, array<string, string>, string}>
     */
    public static function secrets(): iterable
    {
        yield 'value object' => ['email', ['email' => 'SeCrEt-7f3a'], 'The route argument "email" is not a valid email address.'];
        yield 'backed enum' => ['country', ['country' => 'SeCrEt-7f3a'], 'The route argument "country" is not a valid country code.'];
    }

    /**
     * @param non-empty-string $action
     * @param array<string, string> $arguments
     */
    #[DataProvider('secrets')]
    public function testTheMessageCarriesNoPartOfTheValueWhileTheCauseQuotesIt(string $action, array $arguments, string $message): void
    {
        try {
            $this->intercept($action, $arguments);
            self::fail('Expected a TargetCallException.');
        } catch (TargetCallException $exception) {
            self::assertSame($message, $exception->getMessage());
            self::assertInstanceOf(InvalidValueObjectException::class, $exception->getPrevious());
            self::assertStringContainsString('SeCrEt-7f3a', $exception->getPrevious()->getMessage(), 'The cause quotes the value, so the check above proves redaction.');
        }
    }

    public function testATrustedSourceOnlyArgumentIsAProgrammerError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('Argument $name of %s::bounded() cannot be bound from a route: its payload carries its own constraints.', RouteController::class));

        $this->intercept('bounded', ['name' => 'jane']);
    }

    public function testATrustedSourceOnlyArgumentIsAProgrammerErrorEvenWhenNull(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('Argument $name of %s::optionalBounded() cannot be bound from a route: its payload carries its own constraints.', RouteController::class));

        $this->intercept('optionalBounded', ['name' => null]);
    }

    public function testACompositeArgumentIsAProgrammerError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('Argument $location of %s::location() cannot be bound from a route: a route parameter carries a single value, not a composite.', RouteController::class));

        $this->intercept('location', ['location' => '{"lat":1,"lng":2}']);
    }

    public function testATargetWithoutReflectionIsHandedOn(): void
    {
        $handler = new RecordingHandler();
        $context = new CallContext(Target::fromPathArray(['missing', 'action']), ['email' => 'nope']);

        new ValueObjectArgumentsInterceptor(new ValueObjectMapper())->intercept($context, $handler);

        self::assertSame($context, $handler->context);
    }

    /**
     * @param non-empty-string $action
     * @param array<array-key, mixed> $arguments
     *
     * @return array<array-key, mixed>
     */
    private function intercept(string $action, array $arguments): array
    {
        $handler = new RecordingHandler();
        new ValueObjectArgumentsInterceptor(new ValueObjectMapper())->intercept(new CallContext(Target::fromPair(RouteController::class, $action), $arguments), $handler);

        return $handler->context?->getArguments() ?? self::fail('The handler was not called.');
    }
}
