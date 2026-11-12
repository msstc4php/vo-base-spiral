<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Filter;

use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use Override;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Filters\Model\Filter;
use Spiral\Filters\Model\FilterDefinitionInterface;
use Spiral\Filters\Model\HasFilterDefinition;
use Spiral\Validator\FilterDefinition;

/**
 * The README's validation example, verbatim.
 */
final class ReadmeContactFilter extends Filter implements HasFilterDefinition
{
    #[Post]
    public string $email = '';

    #[Override]
    public function filterDefinition(): FilterDefinitionInterface
    {
        return new FilterDefinition([
            'email' => ['required', ['vo:valid', EmailAddress::class]],
        ]);
    }
}
