<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Http;

use MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Filter\SignupFilter;

final class FilterController
{
    /**
     * @return array<string, array{type: string, value: mixed}>
     */
    public function signup(SignupFilter $filter): array
    {
        return Described::properties($filter);
    }
}
