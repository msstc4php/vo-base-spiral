<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Validation;

use JsonException;
use LogicException;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Spiral\Internal\TypeLabel;
use Override;
use Spiral\Validator\AbstractChecker;

/**
 * The spiral/validator rule `['vo:valid', EmailAddress::class]`: passes when the input is a valid payload of the
 * value object, or an instance of it. Null and '' pass; emptiness is the job of `required`.
 */
final class ValueObjectChecker extends AbstractChecker
{
    public const array MESSAGES = ['valid' => '[[The value is not a valid {type}.]]'];

    // An absent field still reaches the rule, so a rule naming a class request input cannot build fails on the first
    // request rather than only once the field is sent.
    public const array ALLOW_EMPTY_VALUES = ['valid'];

    public function __construct(
        private readonly ValueObjectMapper $mapper,
    ) {
    }

    /**
     * @throws LogicException when request input cannot build a $class (unsupported, or trusted-source only)
     */
    public function valid(mixed $value, string $class): bool
    {
        if (!$this->mapper->supportsUntrusted($class) || !class_exists($class)) {
            // BoundedString's payload carries its own bounds: request input would choose them.
            throw new LogicException($this->mapper->supports($class) ? \sprintf('vo:valid does not validate %s from request input: its payload carries its own constraints.', $class) : \sprintf('vo:valid cannot validate %s: it is neither a scalar-backed value object, a backed enum nor a class with a payload codec.', $class));
        }

        if ($value === null || $value === '' || $value instanceof $class) {
            return true;
        }

        try {
            $payload = \is_string($value) && $this->mapper->isComposite($class)
                ? json_decode($value, true, flags: \JSON_THROW_ON_ERROR)
                : $value;
            $this->mapper->fromUntrustedPayload($class, $payload);

            return true;
        } catch (InvalidValueObjectException|JsonException) {
            return false;
        }
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    #[Override]
    public function getMessage(string $method, string $field, mixed $value, array $arguments = []): string
    {
        if ($method !== 'valid') {
            return parent::getMessage($method, $field, $value, $arguments);
        }

        $class = reset($arguments);

        return $this->say(self::MESSAGES['valid'], ['type' => \is_string($class) && class_exists($class) ? TypeLabel::of($class) : 'value']);
    }
}
