<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Filter;

use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\I18n\CountryCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use MSSTC4PHP\ValueObject\Primitive\Percentage;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Filters\Model\Filter;

final class SignupFilter extends Filter
{
    #[Post]
    public EmailAddress $email;

    #[Post]
    public ?Port $port = null;

    #[Post]
    public ?Percentage $share = null;

    #[Post]
    public ?GeoCoordinates $location = null;

    #[Post]
    public ?CountryCode $country = null;

    #[Post]
    public ?BoundedString $nickname = null;
}
