<?php

namespace Sabre\VObject\TimezoneGuesser;

use DateTimeZone;
use Sabre\VObject\Component\VTimeZone;

interface TimezoneFinder
{
    public function find(string $tzid, bool $failIfUncertain = false): ?DateTimeZone;
}