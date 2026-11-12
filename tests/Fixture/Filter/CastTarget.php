<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Filter;

use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\I18n\CountryCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Network\QueryParameter;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use MSSTC4PHP\ValueObject\Primitive\Percentage;
use Spiral\Filters\Model\Filter;

/**
 * Property types for calling the caster directly, without attributes or input.
 */
final class CastTarget extends Filter
{
    public EmailAddress $email;

    public ?EmailAddress $optionalEmail = null;

    public Port $port;

    public Percentage $share;

    public GeoCoordinates $location;

    public BoundedString $nickname;

    public ?BoundedString $optionalNickname = null;

    public CountryCode $country;

    public QueryParameter $parameter;

    public string $name = '';

    public int|string $either = '';
}
