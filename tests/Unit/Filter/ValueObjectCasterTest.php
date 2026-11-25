<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Unit\Filter;

use JsonException;
use LogicException;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Primitive\Percentage;
use MSSTC4PHP\ValueObject\Spiral\Filter\ValueObjectCaster;
use MSSTC4PHP\ValueObject\Spiral\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Code\Code;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Code\LongCode;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Code\ShortCode;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Filter\CastTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionNamedType;
use ReflectionProperty;
use Spiral\Filters\Exception\SetterException;

#[CoversClass(ValueObjectCaster::class)]
#[UsesClass(TypeLabel::class)]
#[UsesClass(Code::class)]
final class ValueObjectCasterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function propertyTypes(): iterable
    {
        yield 'string-backed' => ['email', true];
        yield 'nullable' => ['optionalEmail', true];
        yield 'int-backed' => ['port', true];
        yield 'float-backed' => ['share', true];
        yield 'composite' => ['location', true];
        yield 'abstract, resolves its concrete class' => ['code', true];
        yield 'trusted-source only, claimed to be refused' => ['nickname', true];
        yield 'backed enum, left to EnumCaster' => ['country', false];
        yield 'value object without a payload form' => ['parameter', false];
        yield 'builtin' => ['name', false];
    }

    #[DataProvider('propertyTypes')]
    public function testItClaimsTheTypesTheMapperReadsExceptEnums(string $property, bool $supported): void
    {
        $type = new ReflectionProperty(CastTarget::class, $property)->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $type);

        self::assertSame($supported, new ValueObjectCaster(new ValueObjectMapper())->supports($type));
    }

    public function testAScalarPayloadBecomesTheValueObject(): void
    {
        $target = new CastTarget();

        $this->cast($target, 'email', 'jane@example.com');
        $this->cast($target, 'port', '8080');
        $this->cast($target, 'share', 12.5);

        self::assertEquals(EmailAddress::from('jane@example.com'), $target->email);
        self::assertEquals(Port::from(8080), $target->port);
        self::assertEquals(Percentage::from(12.5), $target->share);
    }

    public function testAnAbstractTypeResolvingItsConcreteClassBecomesTheSubclassTheValueFits(): void
    {
        $short = new CastTarget();
        $long = new CastTarget();

        $this->cast($short, 'code', 'abc');
        $this->cast($long, 'code', 'abcd');

        self::assertEquals(ShortCode::from('abc'), $short->code);
        self::assertEquals(LongCode::from('abcd'), $long->code);
    }

    public function testAnInstanceOfTheTypePassesThroughUntouched(): void
    {
        $target = new CastTarget();
        $email = EmailAddress::from('jane@example.com');

        $this->cast($target, 'email', $email);

        self::assertSame($email, $target->email);
    }

    public function testACompositeIsReadFromJsonTextOrAnArray(): void
    {
        $fromText = new CastTarget();
        $fromArray = new CastTarget();

        $this->cast($fromText, 'location', '{"lat":52.52,"lng":13.405}');
        $this->cast($fromArray, 'location', ['lat' => 52.52, 'lng' => 13.405]);

        self::assertEquals(GeoCoordinates::fromFloats(52.52, 13.405), $fromText->location);
        self::assertEquals(GeoCoordinates::fromFloats(52.52, 13.405), $fromArray->location);
    }

    public function testAnEmptyStringOnANullablePropertyIsNull(): void
    {
        $target = new CastTarget();
        $target->optionalEmail = EmailAddress::from('jane@example.com');

        $this->cast($target, 'optionalEmail', '');

        self::assertNull($target->optionalEmail);
    }

    /**
     * @return iterable<string, array{string, mixed, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'invalid content' => ['email', 'nope', 'The value is not a valid email address.'];
        yield 'empty string on a non-nullable property' => ['email', '', 'The value is not a valid email address.'];
        yield 'array for a string' => ['email', ['jane@example.com'], 'The value is not a valid email address.'];
        yield 'out of range' => ['port', '70000', 'The value is not a valid port.'];
        yield 'non-canonical integer' => ['port', '08080', 'The value is not a valid port.'];
        yield 'composite from a string that is not JSON' => ['location', '{"lat":', 'The value is not a valid geo coordinates.'];
        yield 'composite from JSON that is not an object' => ['location', '"52.52"', 'The value is not a valid geo coordinates.'];
        yield 'composite with a nested array' => ['location', ['lat' => [1], 'lng' => 2], 'The value is not a valid geo coordinates.'];
        yield 'composite out of range' => ['location', ['lat' => 91, 'lng' => 0], 'The value is not a valid geo coordinates.'];
        yield 'abstract type, no subclass fits' => ['code', 'abcdefg', 'The value is not a valid code.'];
    }

    #[DataProvider('invalidInput')]
    public function testInvalidInputIsASetterErrorNamingTheLabel(string $property, mixed $value, string $message): void
    {
        try {
            $this->cast(new CastTarget(), $property, $value);
            self::fail('Expected a SetterException.');
        } catch (SetterException $exception) {
            self::assertSame($message, $exception->getMessage());
            self::assertStringNotContainsString('MSSTC4PHP', $exception->getMessage());
            self::assertThat($exception->getPrevious(), self::logicalOr(
                self::isInstanceOf(InvalidValueObjectException::class),
                self::isInstanceOf(JsonException::class),
            ));
        }
    }

    public function testTheMessageCarriesNoPartOfTheValueWhileTheCauseQuotesIt(): void
    {
        try {
            $this->cast(new CastTarget(), 'email', 'SeCrEt-7f3a');
            self::fail('Expected a SetterException.');
        } catch (SetterException $exception) {
            self::assertSame('The value is not a valid email address.', $exception->getMessage());
            self::assertInstanceOf(InvalidValueObjectException::class, $exception->getPrevious());
            self::assertStringContainsString('SeCrEt-7f3a', $exception->getPrevious()->getMessage(), 'The cause quotes the value, so the check above proves redaction.');
        }
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function trustedSourceOnlyInput(): iterable
    {
        yield 'a payload' => ['nickname', ['value' => 'jane', 'minLength' => 0, 'maxLength' => 4]];
        yield 'an empty string on a nullable property' => ['optionalNickname', ''];
    }

    #[DataProvider('trustedSourceOnlyInput')]
    public function testATrustedSourceOnlyClassIsRefusedWhateverTheInput(string $property, mixed $value): void
    {
        $this->expectException(SetterException::class);
        $this->expectExceptionMessage('A bounded string cannot be read from request input.');

        $this->cast(new CastTarget(), $property, $value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignProperties(): iterable
    {
        yield 'union type' => ['either'];
        yield 'builtin type' => ['name'];
    }

    #[DataProvider('foreignProperties')]
    public function testAPropertyNotTypedWithAClassIsAProgrammerError(string $property): void
    {
        $this->expectException(LogicException::class);

        $this->cast(new CastTarget(), $property, 'x');
    }

    private function cast(CastTarget $target, string $property, mixed $value): void
    {
        new ValueObjectCaster(new ValueObjectMapper())->setValue($target, new ReflectionProperty($target, $property), $value);
    }
}
