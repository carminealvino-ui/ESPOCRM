<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PDO;

/**
 * Backfill Brand + colore calendario su ProductBrand, calendari lavorativi,
 * Disponibilità e Appuntamenti.
 */
class BrandCalendarColorBackfill
{
    /** @var array<string, Entity> */
    private array $brandByName = [];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @param array{
     *   dryRun?: bool,
     *   only?: string,
     *   applyDefaultColors?: bool,
     *   colorsJsonPath?: ?string,
     *   limit?: int,
     *   forceColor?: bool
     * } $options
     * @return array<string, mixed>
     */
    public function run(array $options = []): array
    {
        $dryRun = (bool) ($options['dryRun'] ?? false);
        $only = $options['only'] ?? 'all';
        $applyDefaultColors = (bool) ($options['applyDefaultColors'] ?? false);
        $colorsJsonPath = $options['colorsJsonPath'] ?? null;
        $limit = (int) ($options['limit'] ?? 0);
        $forceColor = (bool) ($options['forceColor'] ?? false);

        $this->loadBrandMap();

        $stats = [
            'dryRun' => $dryRun,
            'brandsColored' => 0,
            'brandsSkipped' => 0,
            'calendarsUpdated' => 0,
            'calendarsSkipped' => 0,
            'disponibilitaUpdated' => 0,
            'disponibilitaSkipped' => 0,
            'appuntamentiUpdated' => 0,
            'appuntamentiSkipped' => 0,
            'warnings' => [],
        ];

        if ($only === 'all' || $only === 'brands') {
            if (!$applyDefaultColors && !$colorsJsonPath) {
                $stats['warnings'][] = 'Step brands: usare --apply-default-colors o --colors-json=FILE.';
            } else {
                $colorMap = $this->loadColorMap($colorsJsonPath);
                $brandStats = $this->backfillBrandColors($colorMap, $dryRun, $forceColor);
                $stats['brandsColored'] = $brandStats['updated'];
                $stats['brandsSkipped'] = $brandStats['skipped'];
            }
        }

        if ($only === 'all' || $only === 'calendars') {
            $calendarStats = $this->backfillWorkingTimeCalendars($dryRun, $limit);
            $stats['calendarsUpdated'] = $calendarStats['updated'];
            $stats['calendarsSkipped'] = $calendarStats['skipped'];
        }

        if ($only === 'all' || $only === 'disponibilita') {
            $dispStats = $this->backfillDisponibilita($dryRun, $limit, $forceColor);
            $stats['disponibilitaUpdated'] = $dispStats['updated'];
            $stats['disponibilitaSkipped'] = $dispStats['skipped'];
        }

        if ($only === 'all' || $only === 'appuntamenti') {
            $appStats = $this->backfillAppuntamenti($dryRun, $limit, $forceColor);
            $stats['appuntamentiUpdated'] = $appStats['updated'];
            $stats['appuntamentiSkipped'] = $appStats['skipped'];
        }

        return $stats;
    }

    /**
     * @return array<string, string>
     */
    private function loadColorMap(?string $colorsJsonPath): array
    {
        $paths = array_filter([
            $colorsJsonPath,
            dirname(__DIR__, 4) . '/tools/data/brand-calendar-colors.json',
            dirname(__DIR__, 4) . '/tools/data/brand-calendar-colors.example.json',
        ]);

        foreach ($paths as $path) {
            if (!$path || !is_file($path)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($path), true);

            if (!is_array($decoded)) {
                continue;
            }

            $map = [];

            foreach ($decoded as $name => $color) {
                if (!is_string($name) || !is_string($color)) {
                    continue;
                }

                $name = trim($name);
                $color = trim($color);

                if ($name === '' || $color === '') {
                    continue;
                }

                $map[strtoupper($name)] = $color;
            }

            if ($map !== []) {
                return $map;
            }
        }

        return [];
    }

    private function loadBrandMap(): void
    {
        $this->brandByName = [];

        $brands = $this->entityManager
            ->getRDBRepository('ProductBrand')
            ->find();

        foreach ($brands as $brand) {
            $name = trim((string) $brand->get('name'));

            if ($name === '') {
                continue;
            }

            $this->brandByName[strtoupper($name)] = $brand;
        }
    }

    private function resolveBrandByName(?string $name): ?Entity
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return $this->brandByName[strtoupper($name)] ?? null;
    }

    /**
     * @param array<string, string> $colorMap
     * @return array{updated: int, skipped: int}
     */
    private function backfillBrandColors(array $colorMap, bool $dryRun, bool $forceColor): array
    {
        $updated = 0;
        $skipped = 0;

        if ($colorMap === []) {
            return ['updated' => 0, 'skipped' => 0];
        }

        foreach ($this->brandByName as $brand) {
            $name = strtoupper(trim((string) $brand->get('name')));
            $targetColor = $colorMap[$name] ?? null;

            if ($targetColor === null) {
                $skipped++;
                continue;
            }

            $currentColor = trim((string) ($brand->get('color') ?: ''));

            if ($currentColor !== '' && !$forceColor) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $updated++;
                continue;
            }

            $brand->set('color', $targetColor);
            $this->entityManager->saveEntity($brand, [
                'skipHooks' => true,
                'silent' => true,
            ]);
            $updated++;
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @return array{updated: int, skipped: int}
     */
    private function backfillWorkingTimeCalendars(bool $dryRun, int $limit): array
    {
        $updated = 0;
        $skipped = 0;

        $query = $this->entityManager
            ->getRDBRepository('WorkingTimeCalendar')
            ->where([
                'generazioneProductBrandId' => null,
            ])
            ->order('createdAt', 'ASC');

        if ($limit > 0) {
            $query->limit(0, $limit);
        }

        $legacyMap = $this->loadLegacyCalendarAziendaMap();

        foreach ($query->find() as $calendar) {
            $legacyAzienda = $legacyMap[$calendar->getId()] ?? null;
            $brand = $this->resolveBrandByName($legacyAzienda);

            if (!$brand) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $updated++;
                continue;
            }

            $calendar->set([
                'generazioneProductBrandId' => $brand->getId(),
                'generazioneProductBrandName' => $brand->get('name'),
            ]);

            $this->entityManager->saveEntity($calendar, [
                'skipHooks' => true,
                'silent' => true,
            ]);
            $updated++;
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @return array<string, string>
     */
    private function loadLegacyCalendarAziendaMap(): array
    {
        if (!$this->tableHasColumn('working_time_calendar', 'generazione_azienda')) {
            return [];
        }

        $pdo = $this->entityManager->getPDO();
        $stmt = $pdo->query(
            "SELECT id, generazione_azienda
             FROM working_time_calendar
             WHERE deleted = 0
               AND generazione_azienda IS NOT NULL
               AND generazione_azienda != ''"
        );

        if ($stmt === false) {
            return [];
        }

        $map = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(string) $row['id']] = trim((string) $row['generazione_azienda']);
        }

        return $map;
    }

    /**
     * @return array{updated: int, skipped: int}
     */
    private function backfillDisponibilita(bool $dryRun, int $limit, bool $forceColor): array
    {
        $updated = 0;
        $skipped = 0;

        $query = $this->entityManager
            ->getRDBRepository('Disponibilita')
            ->order('createdAt', 'ASC');

        if ($limit > 0) {
            $query->limit(0, $limit);
        }

        foreach ($query->find() as $entity) {
            if (!$this->shouldUpdateDisponibilita($entity, $forceColor)) {
                $skipped++;
                continue;
            }

            $brand = $this->resolveDisponibilitaBrand($entity);

            if (!$brand) {
                $skipped++;
                continue;
            }

            if (!$entity->get('productBrandId')) {
                $entity->set([
                    'productBrandId' => $brand->getId(),
                    'productBrandName' => $brand->get('name'),
                ]);
            }

            if ($dryRun) {
                $updated++;
                continue;
            }

            $this->entityManager->saveEntity($entity, [
                'silent' => true,
            ]);
            $updated++;
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    private function shouldUpdateDisponibilita(Entity $entity, bool $forceColor): bool
    {
        $brand = $this->resolveDisponibilitaBrand($entity, false);

        if (!$brand) {
            return false;
        }

        if (!$entity->get('productBrandId')) {
            return true;
        }

        $brandColor = trim((string) ($brand->get('color') ?: ''));

        if ($brandColor === '') {
            return false;
        }

        $currentColor = trim((string) ($entity->get('color') ?: ''));

        if ($forceColor) {
            return $currentColor !== $brandColor;
        }

        return $currentColor === '';
    }

    private function resolveDisponibilitaBrand(Entity $entity, bool $allowLegacyAzienda = true): ?Entity
    {
        $brandId = $entity->get('productBrandId');

        if ($brandId) {
            return $this->entityManager->getEntityById('ProductBrand', $brandId);
        }

        if (!$allowLegacyAzienda) {
            return null;
        }

        return $this->resolveBrandByName($entity->get('azienda'));
    }

    /**
     * @return array{updated: int, skipped: int}
     */
    private function backfillAppuntamenti(bool $dryRun, int $limit, bool $forceColor): array
    {
        $updated = 0;
        $skipped = 0;

        $query = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->where([
                'productBrandId!=' => null,
            ])
            ->order('createdAt', 'ASC');

        if ($limit > 0) {
            $query->limit(0, $limit);
        }

        foreach ($query->find() as $entity) {
            $brand = $this->entityManager->getEntityById(
                'ProductBrand',
                $entity->get('productBrandId')
            );

            if (!$brand) {
                $skipped++;
                continue;
            }

            $brandColor = trim((string) ($brand->get('color') ?: ''));

            if ($brandColor === '') {
                $skipped++;
                continue;
            }

            $currentColor = trim((string) ($entity->get('color') ?: ''));

            if (!$forceColor && $currentColor === $brandColor) {
                $skipped++;
                continue;
            }

            if (!$forceColor && $currentColor !== '' && $currentColor !== $brandColor) {
                // Mantieni colori manuali o per status se già impostati.
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $updated++;
                continue;
            }

            $entity->set('color', $brandColor);
            $this->entityManager->saveEntity($entity, [
                'skipHooks' => true,
                'silent' => true,
            ]);
            $updated++;
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $pdo = $this->entityManager->getPDO();

        try {
            $stmt = $pdo->prepare(
                'SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE :column'
            );
            $stmt->execute(['column' => $column]);

            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return false;
        }
    }
}
