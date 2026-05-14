<?php

if (!function_exists('cinem4_load_env')) {
    function cinem4_load_env(?string $path = null): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;
        $path = $path ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $value = str_replace(['\\n', '\\r'], ["\n", "\r"], $value);

            // Jangan menimpa environment variable yang sudah di-set oleh server/hosting.
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('cinem4_env')) {
    function cinem4_env(string $key, $default = null)
    {
        cinem4_load_env();

        $value = getenv($key);

        if ($value !== false) {
            return $value;
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        return $default;
    }
}

if (!function_exists('cinem4_env_bool')) {
    function cinem4_env_bool(string $key, bool $default = false): bool
    {
        $value = cinem4_env($key, null);

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
