<?php
namespace App\Storage;

final class RecordId
{
    // Legacy seconds, or seconds followed by a 128-bit random suffix.
    public static function isValid(string $id): bool
    {
        return (bool) preg_match('/\A[0-9]{10}(?:-[a-f0-9]{32})?\z/', $id);
    }
}
