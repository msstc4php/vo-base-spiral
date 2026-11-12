<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Http;

use ReflectionObject;
use ReflectionProperty;

/**
 * What an action received, as JSON an HTTP test can assert on: each initialized public property with its PHP type.
 */
final class Described
{
    /**
     * @return array<string, array{type: string, value: mixed}>
     */
    public static function properties(object $object): array
    {
        $described = [];
        foreach (new ReflectionObject($object)->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isInitialized($object)) {
                $described[$property->getName()] = self::value($property->getValue($object));
            }
        }

        return $described;
    }

    /**
     * @return array{type: string, value: mixed}
     */
    public static function value(mixed $value): array
    {
        return ['type' => get_debug_type($value), 'value' => $value];
    }
}
