<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Internal;

/**
 * @internal
 */
final class TypeLabel
{
    /**
     * EmailAddress becomes "email address": client-facing messages never name a class.
     *
     * @param class-string $class
     */
    public static function of(string $class): string
    {
        $separator = strrpos($class, '\\');
        $short = $separator === false ? $class : substr($class, $separator + 1);

        return strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $short));
    }
}
