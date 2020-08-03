<?php


namespace TestTrap;

final class GlobalManager
{
    private const KEYS = [
        'migrations_ended',
        'tt_queries'
    ];

    public static function has(string $key): bool
    {
        return isset($GLOBALS[$key]);
    }

    public static function set(string $key, $value): void
    {
        $GLOBALS[$key] = $value;
    }

    public static function get(string $key)
    {
        return self::has($key) ? $GLOBALS[$key] : null;
    }

    public static function push(string $key, $value): void
    {
        if (! self::has($key)) {
            $GLOBALS[$key] = [];
        }

        array_push($GLOBALS[$key], $value);
    }

    public static function flush()
    {
        foreach (self::KEYS as $KEY) {
            unset($GLOBALS[$KEY]);
        }
    }
}
