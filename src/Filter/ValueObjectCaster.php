<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Filter;

use JsonException;
use LogicException;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Spiral\Internal\TypeLabel;
use Override;
use ReflectionNamedType;
use ReflectionProperty;
use Spiral\Filters\Exception\SetterException;
use Spiral\Filters\Model\FilterInterface;
use Spiral\Filters\Model\Mapper\CasterInterface;

/**
 * Fills a filter property typed with a value object from request input. Invalid input is a SetterException, which
 * Spiral records as that property's error (422); its message names the type label, never the class.
 *
 * Trusted-source-only classes (BoundedString) are claimed so that they are always refused: declined, they would go
 * to the next caster. Backed enums are left to Spiral's EnumCaster. Spiral never calls a caster for a null value.
 */
final readonly class ValueObjectCaster implements CasterInterface
{
    public function __construct(
        private ValueObjectMapper $mapper,
    ) {
    }

    #[Override]
    public function supports(ReflectionNamedType $type): bool
    {
        return !$type->isBuiltin() && !enum_exists($type->getName()) && $this->mapper->supports($type->getName());
    }

    /**
     * @throws SetterException when the value is not a valid payload, or the class may not come from request input
     * @throws LogicException when the property is not typed with a single class
     */
    #[Override]
    public function setValue(FilterInterface $filter, ReflectionProperty $property, mixed $value): void
    {
        $type = $property->getType();
        $class = $type instanceof ReflectionNamedType ? $type->getName() : '';
        if (!$type instanceof ReflectionNamedType || !class_exists($class)) {
            throw new LogicException(\sprintf('%s only sets a property typed with a single value object class, %s::$%s is not.', self::class, $property->class, $property->name));
        }

        if ($value instanceof $class) {
            $property->setValue($filter, $value);

            return;
        }

        $label = TypeLabel::of($class);
        if (!$this->mapper->supportsUntrusted($class)) {
            throw new SetterException(message: \sprintf('A %s cannot be read from request input.', $label));
        }

        if ($value === '' && $type->allowsNull()) {
            $property->setValue($filter, null);

            return;
        }

        try {
            // A composite arrives as JSON text from a form field or a query string, as an array from a JSON body.
            $payload = \is_string($value) && $this->mapper->isComposite($class)
                ? json_decode($value, true, flags: \JSON_THROW_ON_ERROR)
                : $value;
            $property->setValue($filter, $this->mapper->fromUntrustedPayload($class, $payload));
        } catch (InvalidValueObjectException|JsonException $exception) {
            throw new SetterException($exception, \sprintf('The value is not a valid %s.', $label));
        }
    }
}
