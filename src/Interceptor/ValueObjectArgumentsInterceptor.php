<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Interceptor;

use BackedEnum;
use LogicException;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Spiral\Internal\TypeLabel;
use Override;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\Exception\TargetCallException;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;

/**
 * A route parameter becomes the value object the action argument is typed with: `/users/<email>` into
 * `EmailAddress $email`. An invalid value is a TargetCallException::NOT_FOUND, which the router answers with 404.
 *
 * Arguments bind by name, as Spiral's resolver does. Scalar-backed value objects and backed enums (Spiral's resolver
 * refuses a string for an enum argument with a 400; an unknown case is a 404 here): a route parameter is one string.
 * Builtin, union and intersection types are left to Spiral.
 */
final readonly class ValueObjectArgumentsInterceptor implements InterceptorInterface
{
    public function __construct(
        private ValueObjectMapper $mapper,
    ) {
    }

    /**
     * @throws TargetCallException when an argument is not a valid value of its value-object type (NOT_FOUND)
     * @throws LogicException when an argument is typed with a value object a route parameter cannot carry
     */
    #[Override]
    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        $function = $context->getTarget()->getReflection();
        if (!$function instanceof ReflectionFunctionAbstract) {
            return $handler->handle($context);
        }

        $arguments = $context->getArguments();
        foreach ($function->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            if (\array_key_exists($name, $arguments) && $type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $arguments[$name] = $this->convert($function, $parameter, $type, $arguments[$name]);
            }
        }

        return $handler->handle($context->withArguments($arguments));
    }

    private function convert(ReflectionFunctionAbstract $function, ReflectionParameter $parameter, ReflectionNamedType $type, mixed $value): mixed
    {
        $class = $type->getName();
        if ($value === null || $value instanceof $class || !$this->mapper->supports($class)) {
            return $value;
        }

        if (!$this->mapper->supportsUntrusted($class)) {
            throw $this->unbindable($function, $parameter, 'its payload carries its own constraints');
        }

        if (!$this->mapper->isScalarBacked($class) && !is_subclass_of($class, BackedEnum::class)) {
            throw $this->unbindable($function, $parameter, 'a route parameter carries a single value, not a composite');
        }

        if ($value === '' && $type->allowsNull()) {
            return null;
        }

        try {
            return $this->mapper->fromUntrustedPayload($class, $value);
        } catch (InvalidValueObjectException $exception) {
            throw new TargetCallException(\sprintf('The route argument "%s" is not a valid %s.', $parameter->getName(), TypeLabel::of($class)), TargetCallException::NOT_FOUND, $exception);
        }
    }

    private function unbindable(ReflectionFunctionAbstract $function, ReflectionParameter $parameter, string $reason): LogicException
    {
        $where = $function instanceof ReflectionMethod ? $function->class . '::' . $function->getName() : $function->getName();

        return new LogicException(\sprintf('Argument $%s of %s() cannot be bound from a route: %s.', $parameter->getName(), $where, $reason));
    }
}
