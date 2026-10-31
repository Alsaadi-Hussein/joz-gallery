<?php

namespace App\Support;

class Phone
{
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
