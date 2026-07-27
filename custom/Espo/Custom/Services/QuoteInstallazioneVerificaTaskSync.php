<?php

namespace Espo\Custom\Services;

use Espo\Core\Utils\Log;
use Espo\Custom\Tools\DateTime\BusinessDateTime;
use Espo\Modules\Crm\Entities\Reminder;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Alla data installazione su Contratto crea/aggiorna un To-Do con promemoria
 * per il giorno successivo (verifica eseguita / rinviata).
 */
class QuoteInstallazioneVerificaTaskSync
{
    private const TASK_DUE_HOUR = 9;
    private const TASK_DUE_MINUTE = 0;
    private const REMINDER_SECONDS = 0;

    public function __construct(
        private EntityManager $entityManager,
        private ContrattoStatiRules $contrattoStatiRules,
        private Log $log,
    ) {}

    public function syncFromQuote(Entity $quote, bool $allowQuoteLinkUpdate = true): void
    {
        if ($quote->getEntityType() !== 'Quote') {
            return;
        }

        if ($this->isQuoteAlreadyClosed($quote) || $this->isQuoteInvalid($quote)) {
            if ($this->isQuoteInvalid($quote) && $quote->get('dataInstallazione')) {
                $quote->set('dataInstallazione', null);

                if ($allowQuoteLinkUpdate) {
                    $this->entityManager->saveEntity($quote, [
                        'silent' => true,
                        'skipHooks' => true,
                    ]);
                }
            }

            $this->cancelLinkedTask($quote, $allowQuoteLinkUpdate);

            return;
        }

        $installDate = $this->normalizeDate($quote->get('dataInstallazione'));

        if ($installDate === null) {
            $this->cancelLinkedTask($quote, $allowQuoteLinkUpdate);

            return;
        }

        $dueStorage = $this->resolveDueDateTimeStorage($installDate);
        $task = $this->resolveTaskForQuote($quote);
        $isNew = $task->isNew();

        if ($task->get('status') === 'Canceled') {
            $task->set('status', 'Not Started');
        }

        $label = trim((string) ($quote->get('name') ?: $quote->get('numeroContratto') ?: $quote->getId()));

        $task->set([
            'name' => 'Verifica installazione: ' . $label,
            'status' => $task->get('status') ?: 'Not Started',
            'priority' => $task->get('priority') ?: 'Normal',
            'dateEnd' => $dueStorage,
            'parentType' => 'Quote',
            'parentId' => $quote->getId(),
            'verificaInstallazioneContratto' => true,
            'tipologia' => 'Verifica installazione',
            'description' => $this->buildTaskDescription($quote, $installDate),
        ]);

        $assignedUserId = $quote->get('assignedUserId') ?: $quote->get('createdById');

        if ($assignedUserId) {
            $task->set('assignedUserId', $assignedUserId);
        }

        $opportunityId = $quote->get('opportunityId') ?: $quote->get('opportunitaId');

        if ($opportunityId) {
            $task->set('opportunitaId', $opportunityId);
        }

        if ($isNew || $task->isAttributeChanged('dateEnd') || $task->isAttributeChanged('assignedUserId')) {
            $task->set('esitoVerificaInstallazione', null);
        }

        $this->entityManager->saveEntity($task, [
            'silent' => true,
            'skipHooks' => true,
        ]);

        $this->syncPopupReminder($task, $dueStorage, $assignedUserId ? (string) $assignedUserId : null);

        if ($allowQuoteLinkUpdate && $quote->get('verificaInstallazioneTaskId') !== $task->getId()) {
            $quote->set('verificaInstallazioneTaskId', $task->getId());
            $this->entityManager->saveEntity($quote, [
                'silent' => true,
                'skipHooks' => true,
            ]);
        }
    }

    public function applyEsitoFromTask(Entity $task): void
    {
        if ($task->getEntityType() !== 'Task' || !(bool) $task->get('verificaInstallazioneContratto')) {
            return;
        }

        $esito = trim((string) ($task->get('esitoVerificaInstallazione') ?? ''));

        if ($esito === '') {
            return;
        }

        $quote = $this->resolveQuoteFromTask($task);

        if (!$quote) {
            return;
        }

        if ($esito === 'Installato') {
            $quote->set([
                'status' => 'Installato',
                'statoContratto' => 'Chiuso',
            ]);
            $this->contrattoStatiRules->apply($quote);
            $this->entityManager->saveEntity($quote, ['silent' => true]);

            $task->set([
                'status' => 'Completed',
                'dateCompleted' => date(BusinessDateTime::STORAGE_FORMAT),
            ]);
            $this->entityManager->saveEntity($task, [
                'silent' => true,
                'skipHooks' => true,
            ]);

            return;
        }

        if ($esito === 'Rinviato') {
            $task->set('status', 'Deferred');
            $note = 'Installazione rinviata: aggiornare la Data Installazione sul contratto '
                . 'per ripianificare il promemoria.';
            $current = trim((string) ($task->get('description') ?? ''));

            if (!str_contains($current, 'Installazione rinviata')) {
                $task->set('description', $current === '' ? $note : $current . "\n\n" . $note);
            }

            $this->entityManager->saveEntity($task, [
                'silent' => true,
                'skipHooks' => true,
            ]);
        }
    }

    private function isQuoteAlreadyClosed(Entity $quote): bool
    {
        $status = trim((string) ($quote->get('status') ?? ''));
        $statoContratto = trim((string) ($quote->get('statoContratto') ?? ''));

        return $status === 'Installato' || $statoContratto === 'Chiuso';
    }

    private function isQuoteInvalid(Entity $quote): bool
    {
        $status = trim((string) ($quote->get('status') ?? ''));
        $statoContratto = trim((string) ($quote->get('statoContratto') ?? ''));

        return $status === 'Invalido'
            || in_array($statoContratto, ['Annullato', 'Recesso'], true);
    }

    private function resolveTaskForQuote(Entity $quote): Entity
    {
        $taskId = $quote->get('verificaInstallazioneTaskId');

        if ($taskId) {
            $existing = $this->entityManager->getEntityById('Task', $taskId);

            if ($existing && (bool) $existing->get('verificaInstallazioneContratto')) {
                return $existing;
            }
        }

        $found = $this->entityManager
            ->getRDBRepository('Task')
            ->where([
                'parentType' => 'Quote',
                'parentId' => $quote->getId(),
                'verificaInstallazioneContratto' => true,
                'status!=' => 'Completed',
            ])
            ->order('createdAt', 'DESC')
            ->findOne();

        if ($found) {
            return $found;
        }

        return $this->entityManager->getNewEntity('Task');
    }

    private function cancelLinkedTask(Entity $quote, bool $allowQuoteLinkUpdate): void
    {
        $taskId = $quote->get('verificaInstallazioneTaskId');

        if (!$taskId) {
            return;
        }

        $task = $this->entityManager->getEntityById('Task', $taskId);

        if ($task && (bool) $task->get('verificaInstallazioneContratto') && $task->get('status') !== 'Completed') {
            $task->set('status', 'Canceled');
            $this->entityManager->saveEntity($task, [
                'silent' => true,
                'skipHooks' => true,
            ]);
            $this->clearPopupReminders((string) $task->getId());
        }

        if ($allowQuoteLinkUpdate && $quote->get('verificaInstallazioneTaskId')) {
            $quote->set('verificaInstallazioneTaskId', null);
            $this->entityManager->saveEntity($quote, [
                'silent' => true,
                'skipHooks' => true,
            ]);
        }
    }

    private function resolveQuoteFromTask(Entity $task): ?Entity
    {
        if ($task->get('parentType') === 'Quote' && $task->get('parentId')) {
            $quote = $this->entityManager->getEntityById('Quote', $task->get('parentId'));

            if ($quote) {
                return $quote;
            }
        }

        $collection = $this->entityManager
            ->getRDBRepository('Quote')
            ->where(['verificaInstallazioneTaskId' => $task->getId()])
            ->limit(0, 1)
            ->find();

        foreach ($collection as $quote) {
            return $quote;
        }

        return null;
    }

    private function buildTaskDescription(Entity $quote, string $installDate): string
    {
        $cliente = trim((string) ($quote->get('accountName') ?? ''));

        $lines = [
            'Promemoria automatico: verificare se l\'installazione è stata eseguita.',
            'Data installazione prevista: ' . $this->formatItalianDate($installDate) . '.',
        ];

        if ($cliente !== '') {
            $lines[] = 'Cliente: ' . $cliente . '.';
        }

        $lines[] = 'Esito: Installato → contratto Chiuso/Installato; Rinviato → aggiornare Data Installazione.';

        return implode("\n", $lines);
    }

    private function resolveDueDateTimeStorage(string $installDateYmd): string
    {
        $businessTz = new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE);
        $install = new \DateTimeImmutable($installDateYmd . ' 00:00:00', $businessTz);
        $due = $install
            ->modify('+1 day')
            ->setTime(self::TASK_DUE_HOUR, self::TASK_DUE_MINUTE, 0);

        return BusinessDateTime::businessToStorage($due);
    }

    private function syncPopupReminder(Entity $task, string $dueStorage, ?string $userId): void
    {
        if (!$userId) {
            return;
        }

        $taskId = (string) $task->getId();
        $this->clearPopupReminders($taskId);

        try {
            $this->entityManager->createEntity('Reminder', [
                'entityType' => 'Task',
                'entityId' => $taskId,
                'type' => Reminder::TYPE_POPUP,
                'userId' => $userId,
                'seconds' => self::REMINDER_SECONDS,
                'remindAt' => $dueStorage,
                'startAt' => $dueStorage,
            ], [
                'skipAcl' => true,
                'silent' => true,
                'skipHooks' => true,
            ]);
        } catch (\Throwable $e) {
            $this->log->warning(
                'Verifica installazione: promemoria Task non creato ({taskId}): {message}',
                [
                    'taskId' => $taskId,
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    private function clearPopupReminders(string $taskId): void
    {
        $collection = $this->entityManager
            ->getRDBRepository('Reminder')
            ->where([
                'entityType' => 'Task',
                'entityId' => $taskId,
                'type' => Reminder::TYPE_POPUP,
            ])
            ->find();

        foreach ($collection as $reminder) {
            $this->entityManager->removeEntity($reminder, ['skipAcl' => true]);
        }
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return substr(trim($value), 0, 10);
    }

    private function formatItalianDate(string $ymd): string
    {
        $parts = explode('-', $ymd);

        if (count($parts) !== 3) {
            return $ymd;
        }

        return $parts[2] . '.' . $parts[1] . '.' . $parts[0];
    }
}
