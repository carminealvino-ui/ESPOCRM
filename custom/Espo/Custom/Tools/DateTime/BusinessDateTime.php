<?php

namespace Espo\Custom\Tools\DateTime;

/**
 * Conversione sistemica datetime CRM: storage UTC, logica di business Europe/Rome.
 *
 * Stesso modello di custom/Espo/Custom/Hooks/Disponibilita/SetName.php:
 * il DB Espo salva in UTC; in PHP si interpreta sempre UTC e si converte in Rome
 * per orari "di parete", poi si riscrive in UTC al salvataggio programmatico.
 */
class BusinessDateTime
{
    public const BUSINESS_TIMEZONE = 'Europe/Rome';
    public const STORAGE_TIMEZONE = 'UTC';
    public const STORAGE_FORMAT = 'Y-m-d H:i:s';

    public static function storageToBusiness(string $storageDateTime): \DateTimeImmutable
    {
        $utc = new \DateTimeZone(self::STORAGE_TIMEZONE);

        $parsed = \DateTimeImmutable::createFromFormat(self::STORAGE_FORMAT, $storageDateTime, $utc);

        if ($parsed) {
            return $parsed->setTimezone(new \DateTimeZone(self::BUSINESS_TIMEZONE));
        }

        return (new \DateTimeImmutable($storageDateTime, $utc))
            ->setTimezone(new \DateTimeZone(self::BUSINESS_TIMEZONE));
    }

    public static function businessToStorage(\DateTimeImmutable $businessDateTime): string
    {
        return $businessDateTime
            ->setTimezone(new \DateTimeZone(self::STORAGE_TIMEZONE))
            ->format(self::STORAGE_FORMAT);
    }

    /**
     * @param string $wallClockDatetime Y-m-d H:i:s nel fuso Europe/Rome
     */
    public static function wallClockToStorage(string $wallClockDatetime): string
    {
        $businessTz = new \DateTimeZone(self::BUSINESS_TIMEZONE);

        $parsed = \DateTimeImmutable::createFromFormat(self::STORAGE_FORMAT, $wallClockDatetime, $businessTz);

        if (!$parsed) {
            $parsed = new \DateTimeImmutable($wallClockDatetime, $businessTz);
        }

        return self::businessToStorage($parsed);
    }

    public static function formatBusiness(string $storageDateTime, string $format = 'd/m/Y H:i'): string
    {
        return self::storageToBusiness($storageDateTime)->format($format);
    }
}
