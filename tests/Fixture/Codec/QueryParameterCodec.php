<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Codec;

use LogicException;
use MSSTC4PHP\ValueObject\Contract\ValueObject;
use MSSTC4PHP\ValueObject\Exception\InvalidPayloadException;
use MSSTC4PHP\ValueObject\Network\QueryParameter;
use MSSTC4PHP\ValueObject\Payload\PayloadCodec;
use Override;

/**
 * An application codec: QueryParameter has no built-in payload form.
 *
 * @implements PayloadCodec<QueryParameter>
 */
final readonly class QueryParameterCodec implements PayloadCodec
{
    #[Override]
    public function valueObjectClass(): string
    {
        return QueryParameter::class;
    }

    /**
     * @return array{name: string, value: string}
     */
    #[Override]
    public function encode(ValueObject $value): array
    {
        return $value instanceof QueryParameter ? $value->jsonSerialize() : throw new LogicException('Not a query parameter.');
    }

    #[Override]
    public function decode(array $payload): QueryParameter
    {
        $name = $payload['name'] ?? null;
        $value = $payload['value'] ?? null;

        return \is_string($name) && \is_string($value) ? new QueryParameter($name, $value) : throw new InvalidPayloadException('Expected {"name": string, "value": string}.');
    }
}
