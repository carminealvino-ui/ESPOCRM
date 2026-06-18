<?php

namespace Espo\Custom\Tools\Appuntamento;

/**
 * Calcola data/ora richiamo Call per Appuntamento Pending:
 * +2 giorni dalla data appuntamento, ore 9:30 (Europe/Rome).
 * Se il risultato cade sabato o domenica, slitta al lunedì successivo.
 *
 * EspoCRM salva i datetime in UTC: fromAppointmentDateStart restituisce UTC.
 */
class PendingCallDateTime
{
    private const TIMEZONE = 'Europe/Rome';
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
            ->setTimezone(new \DateTimeZone(self::TIMEZONE));

        $cutoff = new \DateTimeImmutable(
            self::MIN_APPOINTMENT_DATE . ' 00:00:00',
            new \DateTimeZone(self::TIMEZONE)
        );

        return $appointment >= $cutoff;
    }

    /**
     * @return string|null Datetime in formato system (UTC) per salvataggio su Call
     */
    public static function fromAppointmentDateStart(
        ?string $dateStart,
        ?\DateTimeImmutable $notBefore = null
    ): ?string {
        if (!$dateStart) {
            return null;
        }

        $localCall = self::computeLocalCallDateTime(
            self::parseStoredDateTime($dateStart),
            $notBefore
        );

        return self::toStorageDateTime($localCall);
    }

    /**
     * Converte un datetime UTC salvato in Espo in etichetta locale Europe/Rome.
     */
    public static function formatLocalDateTime(string $storageDateTime, string $format = 'd/m/Y H:i'): string
    {
        return self::parseStoredDateTime($storageDateTime)
            ->setTimezone(new \DateTimeZone(self::TIMEZONE))
            ->format($format);
    }

    /**
     * @return string Datetime UTC (Y-m-d H:i:s)
     */
    public static function toStorageDateTime(\DateTimeImmutable $localDateTime): string
    {
        return $localDateTime
            ->setTimezone(new \DateTimeZone(self::STORAGE_TIMEZONE))
            ->format('Y-m-d H:i:s');
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

    private static function computeLocalCallDateTime(
        \DateTimeImmutable $appointmentUtc,
        ?\DateTimeImmutable $notBefore
    ): \DateTimeImmutable {
        $timezone = new \DateTimeZone(self::TIMEZONE);

        $appointment = $appointmentUtc->setTimezone($timezone);

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
