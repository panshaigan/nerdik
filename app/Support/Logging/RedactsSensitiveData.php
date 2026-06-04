<?php

declare(strict_types=1);

namespace App\Support\Logging;

final class RedactsSensitiveData
{
    /**
     * Keys whose values are replaced wherever they appear in log context arrays.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        '_token',
        'password',
        'password_confirmation',
        'current_password',
        'secret',
        'token',
        'authorization',
        'cookie',
        'cookies',
        'session',
        'credit_card',
        'cvv',
        'ssn',
        'REVERB_APP_SECRET',
        'REVERB_APP_KEY',
        'APP_KEY',
        'DB_PASSWORD',
        'MAIL_PASSWORD',
        'AWS_SECRET_ACCESS_KEY',
        'NOCAPTCHA_SECRET',
        'GOOGLE_CLIENT_SECRET',
        'FACEBOOK_CLIENT_SECRET',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function redactArray(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = self::redactArray($value);

                continue;
            }

            if (is_string($value)) {
                $redacted[$key] = self::redactString($value);

                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    public static function redactString(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $redacted = $value;

        foreach (self::SENSITIVE_KEYS as $key) {
            $pattern = '/("'.preg_quote($key, '/').'"\s*:\s*")([^"]*)(")/i';
            $redacted = (string) preg_replace($pattern, '$1[REDACTED]$3', $redacted);
        }

        return $redacted;
    }

    public static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        if (in_array($normalized, array_map(strtolower(...), self::SENSITIVE_KEYS), true)) {
            return true;
        }

        return str_contains($normalized, 'password')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'token')
            || $normalized === 'authorization';
    }
}
