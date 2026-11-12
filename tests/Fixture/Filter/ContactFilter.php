<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Filter;

use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use Override;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Filters\Model\Filter;
use Spiral\Filters\Model\FilterDefinitionInterface;
use Spiral\Filters\Model\HasFilterDefinition;
use Spiral\Validator\FilterDefinition;

/**
 * Raw input validated by the vo:valid rule; the properties stay untyped so the checker sees what the client sent.
 */
final class ContactFilter extends Filter implements HasFilterDefinition
{
    #[Post]
    public mixed $email = null;

    #[Post]
    public mixed $port = null;

    #[Post]
    public mixed $location = null;

    #[Override]
    public function filterDefinition(): FilterDefinitionInterface
    {
        return new FilterDefinition([
            'email' => [['vo:valid', EmailAddress::class]],
            'port' => [['vo:valid', Port::class]],
            'location' => [['vo:valid', GeoCoordinates::class]],
        ]);
    }
}
