<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;
use Throwable;

/**
 * Safety net that masks marketplace secrets before a log line is written. HTTP client
 * errors quote the request URL, which carries access tokens and signatures.
 */
class RedactSecrets
{
    private const KEYS = 'access_token|refresh_token|auth_code|app_secret|client_secret|partner_key|sign';

    private const MASK = '[redacted]';

    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(fn (LogRecord $record) => $record->with(
            message: self::clean($record->message),
            context: self::cleanArray($record->context),
        ));
    }

    public static function clean(string $text): string
    {
        return preg_replace(
            [
                // Query strings: access_token=abc
                '/\b(' . self::KEYS . ')=[^&\s"\'\\\\]+/i',
                // JSON, plain or escaped inside another string: "access_token":"abc"
                '/((?:' . self::KEYS . ')\\\\*"\s*:\s*\\\\*")[^"\\\\]+/i',
            ],
            ['$1=' . self::MASK, '$1' . self::MASK],
            $text,
        ) ?? self::MASK;
    }

    private static function cleanArray(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = match (true) {
                is_string($key) && preg_match('/^(' . self::KEYS . ')$/i', $key) === 1 => self::MASK,
                is_array($value) => self::cleanArray($value),
                is_string($value) => self::clean($value),
                $value instanceof Throwable => self::clean((string) $value),
                default => $value,
            };
        }

        return $values;
    }
}
