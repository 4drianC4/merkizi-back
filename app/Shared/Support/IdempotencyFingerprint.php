<?php

namespace App\Shared\Support;

class IdempotencyFingerprint
{
    /** @param array<string, mixed> $data */
    public static function make(array $data): string
    {
        return hash('sha256', json_encode(self::normalize($data), JSON_THROW_ON_ERROR));
    }

    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::normalize($item);
        }

        return $value;
    }
}
