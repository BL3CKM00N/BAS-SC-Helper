<?php

namespace App\Support;

/**
 * Whether the app can really deliver email. The "log" and "array" mailers only write to the log or to memory,
 * which is fine while developing locally but means nobody receives anything.
 */
final class MailSettings
{
    public static function canDeliver(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true) || app()->environment('local');
    }
}
