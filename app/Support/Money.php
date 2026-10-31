<?php

namespace App\Support;

class Money
{
    /**
     * Format whole Iraqi dinars: "1,450,000 د.ع" in Arabic, "1,450,000 IQD" in English.
     */
    public static function format(int $amount, ?string $locale = null): string
    {
        $currency = ($locale ?? app()->getLocale()) === 'ar' ? 'د.ع' : 'IQD';

        return number_format($amount).' '.$currency;
    }
}
