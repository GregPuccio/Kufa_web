<?php


namespace App\Service;

use Base;
use Exception;
use GeoIp2\Database\Reader;


class GeoIp
{
    /**
     * @param $ip
     * @return string|null
     */
    public static function lookup($ip): ?string
    {

        // This creates the Reader object, which should be reused across
        // lookups.
        try {
            $reader = new Reader('/usr/local/share/GeoIP/GeoLite2-Country.mmdb');

            // Replace "city" with the appropriate method for your database, e.g.,
            // "country".
            return $reader->country($ip)->country->isoCode;
        } catch (Exception $e) {
            Logger::instance()->error($e);
            return null;
        }
    }

    /**
     * @return string|null
     */
    public static function currentCountry(): ?string
    {
        return self::lookup(Base::instance()->get('IP'));
    }
}