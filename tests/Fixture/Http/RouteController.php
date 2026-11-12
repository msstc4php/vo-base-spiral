<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Spiral\Tests\Fixture\Http;

use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\I18n\CountryCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;

/**
 * Actions typed the ways a route argument can be; each answers what it received.
 */
final class RouteController
{
    /**
     * @return array{type: string, value: mixed}
     */
    public function email(EmailAddress $email): array
    {
        return Described::value($email);
    }

    /**
     * @return array{type: string, value: mixed}
     */
    public function port(Port $port): array
    {
        return Described::value($port);
    }

    /**
     * @return array{type: string, value: mixed}
     */
    public function optional(?EmailAddress $email = null): array
    {
        return Described::value($email);
    }

    /**
     * @return array{type: string, value: mixed}
     */
    public function union(EmailAddress|string $id): array
    {
        return Described::value($id);
    }

    /**
     * @return array{type: string, value: mixed}
     */
    public function country(CountryCode $country): array
    {
        return Described::value($country);
    }

    /**
     * @return array{type: string, value: mixed}
     */
    public function plain(string $name): array
    {
        return Described::value($name);
    }

    /**
     * @return array{type: string, value: mixed}
     */
    public function bounded(BoundedString $name): array
    {
        return Described::value($name);
    }

    /**
     * @return array{type: string, value: mixed}
     */
    public function location(GeoCoordinates $location): array
    {
        return Described::value($location);
    }

    /**
     * @return array{type: string, value: mixed}
     */
    public function optionalBounded(?BoundedString $name = null): array
    {
        return Described::value($name);
    }
}
