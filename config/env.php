<?php

/**
 * Lightweight environment file loader.
 *
 * Reads key/value pairs from a `.env` file at the project root (same level as this config directory)
 * and populates getenv/$_ENV/$_SERVER if they are not already defined.
 */
if (!function_exists('fs_load_env')) {
    /**
     * Load environment variables from the given directory.
     */
    function fs_load_env(string $baseDir): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $base = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $envPath = $base . '.env';
        if (!is_readable($envPath)) {
            // Some hosts create the file as "env" (no dot); try that.
            $envPath = $base . 'env';
        }
        if (!is_readable($envPath)) {
            // Allow developers to copy config/env.sample manually without runtime warnings.
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$name, $value] = $parts;
            $name = trim($name);
            $value = trim($value);

            // Remove optional quotes
            $value = trim($value, "\"'");

            if ($name === '') {
                continue;
            }

            if (getenv($name) === false) {
                putenv(sprintf('%s=%s', $name, $value));
            }

            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }

            if (!array_key_exists($name, $_SERVER)) {
                $_SERVER[$name] = $value;
            }
        }

        $loaded = true;
    }
}

fs_load_env(__DIR__ . '/..');

if (!function_exists('fs_env_bool')) {
    function fs_env_bool($value, bool $default = false): bool
    {
        if ($value === null || $value === false) {
            return $default;
        }

        $value = strtolower((string)$value);
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}

