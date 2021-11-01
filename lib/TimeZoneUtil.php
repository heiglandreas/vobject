<?php

namespace Sabre\VObject;

use Sabre\VObject\TimezoneGuesser\GuessFromLicEntry;
use Sabre\VObject\TimezoneGuesser\GuessFromMsTzId;
use Sabre\VObject\TimezoneGuesser\TimezoneGuesser;

/**
 * Time zone name translation.
 *
 * This file translates well-known time zone names into "Olson database" time zone names.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Frank Edelhaeuser (fedel@users.sourceforge.net)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class TimeZoneUtil
{
    public static $map = null;

    private static $timezoneGuessers = [
    ];

    public static function addTimezoneGuesser(TimezoneGuesser $guesser): void
    {
        $key = array_search($guesser, self::$timezoneGuessers);
        if (false !== $key) {
            unset(self::$timezoneGuessers[$key]);
        }
        self::$timezoneGuessers[] = $guesser;
    }

    /**
     * This method will try to find out the correct timezone for an iCalendar
     * date-time value.
     *
     * You must pass the contents of the TZID parameter, as well as the full
     * calendar.
     *
     * If the lookup fails, this method will return the default PHP timezone
     * (as configured using date_default_timezone_set, or the date.timezone ini
     * setting).
     *
     * Alternatively, if $failIfUncertain is set to true, it will throw an
     * exception if we cannot accurately determine the timezone.
     *
     * @param string                  $tzid
     * @param Sabre\VObject\Component $vcalendar
     *
     * @return \DateTimeZone
     */
    public static function getTimeZone($tzid, Component $vcalendar = null, $failIfUncertain = false)
    {
        // First we will just see if the tzid is a support timezone identifier.
        //
        // The only exception is if the timezone starts with (. This is to
        // handle cases where certain microsoft products generate timezone
        // identifiers that for instance look like:
        //
        // (GMT+01.00) Sarajevo/Warsaw/Zagreb
        //
        // Since PHP 5.5.10, the first bit will be used as the timezone and
        // this method will return just GMT+01:00. This is wrong, because it
        // doesn't take DST into account.
        if (isset($tzid[0]) && '(' !== $tzid[0]) {
            // PHP has a bug that logs PHP warnings even it shouldn't:
            // https://bugs.php.net/bug.php?id=67881
            //
            // That's why we're checking if we'll be able to successfull instantiate
            // \DateTimeZone() before doing so. Otherwise we could simply instantiate
            // and catch the exception.
            $tzIdentifiers = \DateTimeZone::listIdentifiers();

            try {
                if (
                    (in_array($tzid, $tzIdentifiers)) ||
                    (preg_match('/^GMT(\+|-)([0-9]{4})$/', $tzid, $matches)) ||
                    (in_array($tzid, self::getIdentifiersBC()))
                ) {
                    return new \DateTimeZone($tzid);
                }
            } catch (\Exception $e) {
            }
        }

        self::loadTzMaps();

        // Next, we check if the tzid is somewhere in our tzid map.
        if (isset(self::$map[$tzid])) {
            return new \DateTimeZone(self::$map[$tzid]);
        }

        // Some Microsoft products prefix the offset first, so let's strip that off
        // and see if it is our tzid map.  We don't want to check for this first just
        // in case there are overrides in our tzid map.
        $patternsArr = [
            '/^\((UTC|GMT)(\+|\-)[\d]{2}\:[\d]{2}\) (.*)/',
            '/^\((UTC|GMT)(\+|\-)[\d]{2}\.[\d]{2}\) (.*)/'
        ];
        foreach ($patternsArr as $pattern) {
            if (preg_match($pattern, $tzid, $matches)) {
                $tzidAlternate = $matches[3];
                if (isset(self::$map[$tzidAlternate])) {
                    return new \DateTimeZone(self::$map[$tzidAlternate]);
                }
            }
        }

        // Maybe the author was hyper-lazy and just included an offset. We
        // support it, but we aren't happy about it.
        if (preg_match('/^GMT(\+|-)([0-9]{4})$/', $tzid, $matches)) {
            // Note that the path in the source will never be taken from PHP 5.5.10
            // onwards. PHP 5.5.10 supports the "GMT+0100" style of format, so it
            // already gets returned early in this function. Once we drop support
            // for versions under PHP 5.5.10, this bit can be taken out of the
            // source.
            // @codeCoverageIgnoreStart
            return new \DateTimeZone('Etc/GMT'.$matches[1].ltrim(substr($matches[2], 0, 2), '0'));
            // @codeCoverageIgnoreEnd
        }

        if ($vcalendar) {
            // If that didn't work, we will scan VTIMEZONE objects
            foreach ($vcalendar->select('VTIMEZONE') as $vtimezone) {
                if ((string) $vtimezone->TZID === $tzid) {
                    foreach (self::$timezoneGuessers as $timezoneGuesser) {
                        $timezone = $timezoneGuesser->guess($vtimezone, $failIfUncertain);
                        if (!$timezone instanceof \DateTimeZone) {
                            continue;
                        }
                        return $timezone;
                    }
                }
            }
        }

        if ($failIfUncertain) {
            throw new \InvalidArgumentException('We were unable to determine the correct PHP timezone for tzid: '.$tzid);
        }

        // If we got all the way here, we default to UTC.
        return new \DateTimeZone(date_default_timezone_get());
    }

    /**
     * This method will load in all the tz mapping information, if it's not yet
     * done.
     */
    public static function loadTzMaps()
    {
        if (!is_null(self::$map)) {
            return;
        }

        self::$map = array_merge(
            include __DIR__ . '/timezonedata/windowszones.php',
            include __DIR__ . '/timezonedata/lotuszones.php',
            include __DIR__ . '/timezonedata/exchangezones.php',
            include __DIR__ . '/timezonedata/php-workaround.php',
            include __DIR__ . '/timezonedata/teamup-workaround.php'
        );
    }

    /**
     * This method returns an array of timezone identifiers, that are supported
     * by DateTimeZone(), but not returned by DateTimeZone::listIdentifiers().
     *
     * We're not using DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC) because:
     * - It's not supported by some PHP versions as well as HHVM.
     * - It also returns identifiers, that are invalid values for new DateTimeZone() on some PHP versions.
     * (See timezonedata/php-bc.php and timezonedata php-workaround.php)
     *
     * @return array
     */
    public static function getIdentifiersBC()
    {
        return include __DIR__.'/timezonedata/php-bc.php';
    }
}

TimeZoneUtil::addTimezoneGuesser(new GuessFromLicEntry());
TimeZoneUtil::addTimezoneGuesser(new GuessFromMsTzId());
