<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Unit\Internal;

use ArrayObject;
use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\I18n\CountryCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Ipv4Address;
use MSSTC4PHP\ValueObject\Spiral\Internal\TypeLabel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeLabel::class)]
final class TypeLabelTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function classes(): iterable
    {
        yield 'two words' => [EmailAddress::class, 'email address'];
        yield 'three words' => [GeoCoordinates::class, 'geo coordinates'];
        yield 'digit before upper' => [Ipv4Address::class, 'ipv4 address'];
        yield 'enum' => [CountryCode::class, 'country code'];
        yield 'global namespace' => [ArrayObject::class, 'array object'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('classes')]
    public function testTheLabelIsTheShortNameInLowercaseWords(string $class, string $label): void
    {
        self::assertSame($label, TypeLabel::of($class));
    }
}
