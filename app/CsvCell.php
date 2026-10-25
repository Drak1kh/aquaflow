<?php

namespace App;

class CsvCell
{
    public static function safe(string $value): string
    {
        return preg_match('/^[=+\-@]/', ltrim($value)) ? "'".$value : $value;
    }
}
