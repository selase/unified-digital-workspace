<?php

declare(strict_types=1);

/**
 * Standalone verification configuration, read before Laravel or config caching.
 * Cloud process variables override Dotenv values without mutating the process.
 */
return (static function (): array {
    $values = [];
    $invalid = false;
    $path = dirname(__DIR__).'/.env';

    try {
        if (is_file($path)) {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                $invalid = true;
            } else {
                $values = Dotenv\Dotenv::parse($contents);
            }
        }
    } catch (Throwable) {
        $invalid = true;
    }

    $read = static function (string $name) use ($values): string {
        $value = getenv($name);
        if ($value === false) {
            $value = $_SERVER[$name] ?? $_ENV[$name] ?? $values[$name] ?? '';
        }

        return is_string($value) ? $value : '';
    };

    $flag = mb_strtolower(mb_trim($read('CLOUD_VERIFICATION_ISOLATED')));

    return [
        'enabled' => ! in_array($flag, ['', 'false', '(false)', '0', 'off', 'no'], true) || $invalid,
        'token' => $read('CLOUD_VERIFICATION_TOKEN'),
        'invalid' => $invalid,
    ];
})();
