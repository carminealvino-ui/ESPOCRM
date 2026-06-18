<?php

namespace Espo\Custom\Tools\Appuntamento;

/**
 * Calcola data/ora richiamo Call per Appuntamento Pending:
 * +2 giorni dalla data appuntamento, ore 9:30 (Europe/Rome).
 * Se il risultato cade sabato o domenica, slitta al lunedì successivo.
 */
class PendingCallDateTime
{
    public const BUSINESS_TIMEZONE = 'Europe/Rome';
    private const STORAGE_TIMEZONE = 'UTC';
    private const CALL_HOUR = 9;
    private const CALL_MINUTE = 30;
    private const DAYS_OFFSET = 2;

    /** Appuntamenti con dateStart precedente non generano Call automatiche. */
    public const MIN_APPOINTMENT_DATE = '2026-01-01';

    public static function isAppointmentEligible(?string $dateStart): bool
    {
        if (!$dateStart) {
            return false;
        }

        $appointment = self::parseStoredDateTime($dateStart)
            ->setTimezone(new \DateTimeZone(self::BUSINESS_TIMEZONE));

        $cutoff = new \DateTimeImmutable(
            self::MIN_APPOINTMENT_DATE . ' 00:00:00',
            new \DateTimeZone(self::BUSINESS_TIMEZONE)
        );

        return $appointment >= $cutoff;
    }

    /**
     * @return string|null Valore per Call::dateStart (timezone applicazione, come da UI Espo)
     */
    public static function fromAppointmentDateStart(
        ?string $dateStart,
        ?\DateTimeImmutable $notBefore = null,
        string $applicationTimeZone = self::BUSINESS_TIMEZONE
    ): ?string {
        if (!$dateStart) {
            return null;
        }

        $instant = self::computeCallInstant(
            self::parseStoredDateTime($dateStart),
            $notBefore
        );

        return self::formatForApplicationTimezone($instant, $applicationTimeZone);
    }

    public static function computeCallInstant(
        \DateTimeImmutable $appointmentStored,
        ?\DateTimeImmutable $notBefore = null
    ): \DateTimeImmutable {
        $timezone = new \DateTimeZone(self::BUSINESS_TIMEZONE);

        $appointment = $appointmentStored->setTimezone($timezone);

        $date = $appointment
            ->setTime(0, 0, 0)
            ->modify('+' . self::DAYS_OFFSET . ' days');

        $date = self::adjustWeekendToMonday($date);

        if ($notBefore !== null) {
            $minDate = $notBefore
                ->setTimezone($timezone)
                ->setTime(0, 0, 0);

            if ($date < $minDate) {
                $date = self::adjustWeekendToMonday($minDate);
            }
        }

        return $date->setTime(self::CALL_HOUR, self::CALL_MINUTE, 0);
    }

    public static function formatForApplicationTimezone(
        \DateTimeImmutable $instant,
        string $applicationTimeZone
    ): string {
        return $instant
            ->setTimezone(new \DateTimeZone($applicationTimeZone))
            ->format('Y-m-d H:i:s');
    }

    public static function formatBusinessDateTime(
        \DateTimeImmutable $instant,
        string $format = 'd/m/Y H:i'
    ): string {
        return $instant
            ->setTimezone(new \DateTimeZone(self::BUSINESS_TIMEZONE))
            ->format($format);
    }

    /**
     * Converte come il repository Event di Espo (input timezone app → UTC in DB).
     */
    public static function toStoredUtc(
        string $applicationDateTime,
        string $applicationTimeZone
    ): string {
        $appTz = new \DateTimeZone($applicationTimeZone);
        $utc = new \DateTimeZone(self::STORAGE_TIMEZONE);

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $applicationDateTime, $appTz)
            ?: new \DateTimeImmutable($applicationDateTime, $appTz);

        return $parsed->setTimezone($utc)->format('Y-m-d H:i:s');
    }

    public static function formatLocalDateTime(
        string $storageDateTime,
        string $format = 'd/m/Y H:i',
        string $applicationTimeZone = self::BUSINESS_TIMEZONE
    ): string {
        $utc = new \DateTimeZone(self::STORAGE_TIMEZONE);
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $storageDateTime, $utc)
            ?: new \DateTimeImmutable($storageDateTime, $utc);

        return $parsed
            ->setTimezone(new \DateTimeZone($applicationTimeZone))
            ->format($format);
    }

    private static function parseStoredDateTime(string $dateStart): \DateTimeImmutable
    {
        $utc = new \DateTimeZone(self::STORAGE_TIMEZONE);

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateStart, $utc);

        if ($parsed) {
            return $parsed;
        }

        return new \DateTimeImmutable($dateStart, $utc);
    }

    private static function adjustWeekendToMonday(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $weekday = (int) $date->format('w');

        if ($weekday === 6) {
            return $date->modify('+2 days');
        }

        if ($weekday === 0) {
            return $date->modify('+1 day');
        }

        return $date;
    }
}
