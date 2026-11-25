<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Unit\Validation;

use LogicException;
use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\I18n\CountryCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Network\QueryParameter;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use MSSTC4PHP\ValueObject\Primitive\PositiveInt;
use MSSTC4PHP\ValueObject\Spiral\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Code\Code;
use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Code\LongCode;
use MSSTC4PHP\ValueObject\Spiral\Validation\ValueObjectChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Spiral\Validator\Bootloader\ValidatorBootloader;

#[CoversClass(ValueObjectChecker::class)]
#[UsesClass(TypeLabel::class)]
#[UsesClass(Code::class)]
#[RequiresMethod(ValidatorBootloader::class, 'addChecker')]
final class ValueObjectCheckerTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string, bool}>
     */
    public static function values(): iterable
    {
        yield 'valid string-backed' => ['jane@example.com', EmailAddress::class, true];
        yield 'invalid string-backed' => ['nope', EmailAddress::class, false];
        yield 'array for a string' => [['jane@example.com'], EmailAddress::class, false];
        yield 'null passes' => [null, EmailAddress::class, true];
        yield 'empty string passes' => ['', EmailAddress::class, true];
        yield 'zero is checked, not skipped as empty' => ['0', PositiveInt::class, false];
        yield 'an instance passes' => [EmailAddress::from('jane@example.com'), EmailAddress::class, true];
        yield 'valid int-backed' => ['8080', Port::class, true];
        yield 'enum code' => ['DE', CountryCode::class, true];
        yield 'unknown enum code' => ['XX', CountryCode::class, false];
        yield 'composite as JSON text' => ['{"lat":52.52,"lng":13.405}', GeoCoordinates::class, true];
        yield 'composite as an array' => [['lat' => 52.52, 'lng' => 13.405], GeoCoordinates::class, true];
        yield 'composite, malformed JSON' => ['{"lat":', GeoCoordinates::class, false];
        yield 'composite out of range' => [['lat' => 91, 'lng' => 0], GeoCoordinates::class, false];
        yield 'abstract type, short subclass' => ['abc', Code::class, true];
        yield 'abstract type, long subclass' => ['abcd', Code::class, true];
        yield 'abstract type, no subclass fits' => ['abcdefg', Code::class, false];
        yield 'abstract type, an instance of a subclass' => [LongCode::from('abcd'), Code::class, true];
    }

    #[DataProvider('values')]
    public function testValid(mixed $value, string $class, bool $valid): void
    {
        self::assertSame($valid, $this->checker()->valid($value, $class));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedClasses(): iterable
    {
        yield 'trusted-source only' => [BoundedString::class, 'its payload carries its own constraints'];
        yield 'without a payload form' => [QueryParameter::class, 'it is neither a scalar-backed value object'];
        yield 'not a class' => ['email', 'it is neither a scalar-backed value object'];
    }

    #[DataProvider('refusedClasses')]
    public function testAClassRequestInputCannotBuildIsAProgrammerErrorEvenForEmptyInput(string $class, string $reason): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($reason);

        $this->checker()->valid(null, $class);
    }

    public function testAnAbsentFieldStillReachesTheRule(): void
    {
        self::assertFalse($this->checker()->ignoreEmpty('valid', null, []));
    }

    public function testTheMessageNamesTheLabelNotTheClass(): void
    {
        // spiral/validator keeps the rule's own index: the class is argument 1, not 0.
        self::assertSame('The value is not a valid email address.', $this->checker()->getMessage('valid', 'email', 'nope', [1 => EmailAddress::class]));
    }

    public function testTheMessageCarriesNoPartOfTheValue(): void
    {
        self::assertSame('The value is not a valid email address.', $this->checker()->getMessage('valid', 'email', 'SeCrEt-7f3a', [1 => EmailAddress::class]));
    }

    public function testAnotherMethodKeepsTheDefaultMessage(): void
    {
        self::assertSame('The condition `other` was not met.', $this->checker()->getMessage('other', 'email', 'nope', [1 => EmailAddress::class]));
    }

    /**
     * valid() refuses such a rule before any message is asked for; the message still names no class.
     *
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function noClass(): iterable
    {
        yield 'no class argument' => [[]];
        yield 'a class argument that is no class' => [[1 => 'Email']];
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    #[DataProvider('noClass')]
    public function testWithoutAClassTheMessageIsGeneric(array $arguments): void
    {
        self::assertSame('The value is not a valid value.', $this->checker()->getMessage('valid', 'email', 'nope', $arguments));
    }

    private function checker(): ValueObjectChecker
    {
        return new ValueObjectChecker(new ValueObjectMapper());
    }
}
