<?php

namespace Espo\Custom\Tools\Appuntamento;

use Espo\Custom\Tools\DateTime\BusinessDateTime;

/**
 * Calcola data/ora richiamo Call per Appuntamento Pending:
 * +2 giorni dalla data appuntamento, ore 9:00 (Europe/Rome).
 * Se il risultato cade sabato o domenica, slitta al lunedì successivo.
 */
class PendingCallDateTime
{
    public const POPUP_DELAY_HOURS = 12;

    private const CALL_HOUR = 9;
    private const CALL_MINUTE = 0;
    private const DAYS_OFFSET = 2;

    /** Appuntamenti con dateStart precedente non generano Call automatiche. */
    public const MIN_APPOINTMENT_DATE = '2026-01-01';

    public static function isAppointmentEligible(?string $dateStart): bool
    {
        if (!$dateStart) {
            return false;
        }

        $appointment = BusinessDateTime::storageToBusiness($dateStart);

        $cutoff = new \DateTimeImmutable(
            self::MIN_APPOINTMENT_DATE . ' 00:00:00',
            new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE)
        );

        return $appointment >= $cutoff;
    }

    /**
     * @return string|null Datetime UTC per salvataggio su Call (skipHooks / save programmatico)
     */
    public static function fromAppointmentDateStart(
        ?string $dateStart,
        ?\DateTimeImmutable $notBefore = null
    ): ?string {
        if (!$dateStart) {
            return null;
        }

        $instant = self::computeCallInstant(
            BusinessDateTime::storageToBusiness($dateStart),
            $notBefore
        );

        return BusinessDateTime::businessToStorage($instant);
    }

    public static function computeCallInstant(
        \DateTimeImmutable $appointmentBusiness,
        ?\DateTimeImmutable $notBefore = null
    ): \DateTimeImmutable {
        $timezone = new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE);

        $appointment = $appointmentBusiness->setTimezone($timezone);

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

    public static function formatBusinessDateTime(
        \DateTimeImmutable $instant,
        string $format = 'd/m/Y H:i'
    ): string {
        return $instant
            ->setTimezone(new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE))
            ->format($format);
    }

    public static function popupEligibilityCutoff(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('-' . self::POPUP_DELAY_HOURS . ' hours')
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
