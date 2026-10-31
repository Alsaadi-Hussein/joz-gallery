<?php

namespace App\Support;

class Phone
{
    /**
     * What a valid Iraqi mobile looks like once normalized.
     */
    public const PATTERN = '/^07[5789]\d{8}$/';

    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /**
     * Whatever shoppers type becomes 07xxxxxxxxx: Arabic digits, spaces, dashes and
     * the +964 country code are all common on phones here.
     */
    public static function normalize(?string $number): string
    {
        $digits = preg_replace('/[\s\-().]/u', '', strtr((string) $number, self::DIGITS));

        return preg_replace('/^(\+|00)?964/', '0', $digits);
    }

    /**
     * "07740243538" becomes "0774 024 3538".
     */
    public static function local(?string $number): string
    {
        return preg_replace('/^(\d{4})(\d{3})(\d{4})$/', '$1 $2 $3', (string) $number);
    }

    /**
     * "9647740243538" becomes "+964 774 024 3538".
     */
    public static function international(?string $number): string
    {
        return preg_replace('/^964(\d{3})(\d{3})(\d{4})$/', '+964 $1 $2 $3', (string) $number);
    }
}
