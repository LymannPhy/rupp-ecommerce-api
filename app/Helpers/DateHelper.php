<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    /**
     * Format a given date to 'd-m-Y'
     *
     * @param string|null $date
     * @return string|null
     */
    public static function formatDate($date)
    {
        return $date ? Carbon::parse($date)->format('d-m-Y') : null;
    }
}
