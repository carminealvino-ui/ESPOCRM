<?php

namespace Espo\Custom\Tools\Appuntamento;

use Espo\Custom\Tools\DateTime\BusinessDateTime;

/**
 * Calcola data/ora richiamo Call per Appuntamento Pending:
 * +12 ore dalla data/ora appuntamento (Europe/Rome).
 */
class PendingCallDateTime
{
    public const POPUP_DELAY_HOURS = 12;

    private const HOURS_OFFSET = 12;

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

        $instant = $appointment->modify('+' . self::HOURS_OFFSET . ' hours');

        if ($notBefore !== null && $instant < $notBefore) {
            $instant = $notBefore;
        }

        return $instant;
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
}
