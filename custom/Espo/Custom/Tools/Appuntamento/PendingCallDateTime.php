<?php

namespace Espo\Custom\Tools\Appuntamento;

/**
 * Calcola data/ora richiamo Call per Appuntamento Pending:
 * +2 giorni dalla data appuntamento, ore 9:30 (Europe/Rome).
 * Se il risultato cade sabato o domenica, slitta al lunedì successivo.
 */
class PendingCallDateTime
{
    private const TIMEZONE = 'Europe/Rome';
    private const CALL_HOUR = 9;
    private const CALL_MINUTE = 30;
    private const DAYS_OFFSET = 2;

    public static function fromAppointmentDateStart(?string $dateStart): ?string
    {
        if (!$dateStart) {
            return null;
        }

        $timezone = new \DateTimeZone(self::TIMEZONE);

        $appointment = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateStart, $timezone);

        if (!$appointment) {
            $appointment = new \DateTimeImmutable($dateStart, $timezone);
        }

        $date = $appointment
            ->setTime(0, 0, 0)
            ->modify('+' . self::DAYS_OFFSET . ' days');

        $date = self::adjustWeekendToMonday($date);

        return $date
            ->setTime(self::CALL_HOUR, self::CALL_MINUTE, 0)
            ->format('Y-m-d H:i:s');
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
