<?php

namespace App\Support;

class SpreadsheetText
{
    /**
     * Stops a typed value running as a formula when a spreadsheet opens it. The
     * leading apostrophe is the standard way to mark a cell as plain text.
     */
    public static function escape(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r\n]/', $value) ? "'".$value : $value;
    }
}
