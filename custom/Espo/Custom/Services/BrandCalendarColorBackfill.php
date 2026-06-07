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

    private bool $verbose = false;

    /** @var string[] */
    private array $log = [];

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
     *   forceColor?: bool,
     *   verbose?: bool,
     *   report?: bool
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
        $this->verbose = (bool) ($options['verbose'] ?? false);
        $this->log = [];

        if (!empty($options['report'])) {
            return $this->buildReport();
        }

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
            'log' => [],
        ];

        if (!$this->entityHasAttribute('ProductBrand', 'color')) {
            $stats['warnings'][] =
                'Campo ProductBrand.color assente: eseguire deploy completo + php command.php rebuild.';
        }

        if (!$this->entityHasAttribute('Disponibilita', 'productBrandId')) {
            $stats['warnings'][] =
                'Campo Disponibilita.productBrandId assente: eseguire deploy completo + php command.php rebuild.';
        }

        if ($only === 'all' || $only === 'brands') {
            if (!$applyDefaultColors && !$colorsJsonPath) {
                $stats['warnings'][] = 'Step brands: usare --apply-default-colors o --colors-json=FILE.';
            } else {
                $colorMap = $this->loadColorMap($colorsJsonPath);
                $colorFile = $this->resolveColorMapPath($colorsJsonPath);

                if ($colorMap === []) {
                    $stats['warnings'][] = 'Nessun colore caricato dal file JSON.';
                } elseif ($this->verbose) {
                    $this->logLine('File colori: ' . ($colorFile ?: 'n/d'));
                    $this->logLine('Brand CRM: ' . implode(', ', $this->listBrandNames()));
                }

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

        $stats['log'] = $this->log;

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(): array
    {
        $this->loadBrandMap();

        $report = [
            'report' => true,
            'brands' => [],
            'disponibilitaAzienda' => [],
            'disponibilitaSamples' => [],
            'warnings' => [],
            'log' => [],
        ];

        foreach ($this->brandByName as $brand) {
            $report['brands'][] = [
                'id' => $brand->getId(),
                'name' => $brand->get('name'),
                'color' => $brand->get('color') ?: '',
            ];
        }

        $report['disponibilitaAzienda'] = $this->loadDistinctDisponibilitaAzienda();

        $samples = 0;

        foreach ($this->entityManager->getRDBRepository('Disponibilita')->limit(0, 10)->find() as $entity) {
            $label = $this->resolveDisponibilitaLabel($entity);
            $brand = $this->resolveDisponibilitaBrand($entity, true);

            $report['disponibilitaSamples'][] = [
                'id' => $entity->getId(),
                'name' => $entity->get('name'),
                'azienda' => $entity->get('azienda'),
                'productBrandId' => $entity->get('productBrandId'),
                'color' => $entity->get('color') ?: '',
                'labelRisolto' => $label,
                'brandRisolto' => $brand ? $brand->get('name') : null,
                'brandColor' => $brand ? ($brand->get('color') ?: '') : '',
            ];

            $samples++;
        }

        if (!$this->entityHasAttribute('ProductBrand', 'color')) {
            $report['warnings'][] = 'Manca ProductBrand.color → eseguire deploy + rebuild.';
        }

        if (!$this->entityHasAttribute('Disponibilita', 'productBrandId')) {
            $report['warnings'][] = 'Manca Disponibilita.productBrandId → eseguire deploy + rebuild.';
        }

        $colorPath = $this->resolveColorMapPath(null);
        $report['log'][] = 'File colori: ' . ($colorPath ?: 'non trovato');
        $report['log'][] = 'Brand CRM: ' . implode(', ', $this->listBrandNames());

        if ($colorPath) {
            $map = $this->loadColorMap(null);
            $report['log'][] = 'Chiavi JSON colori: ' . implode(', ', array_keys($map));
        }

        return $report;
    }

    /**
     * @return array<int, array{value: string, count: int}>
     */
    private function loadDistinctDisponibilitaAzienda(): array
    {
        if (!$this->tableHasColumn('disponibilita', 'azienda')) {
            return [];
        }

        $pdo = $this->entityManager->getPDO();
        $stmt = $pdo->query(
            "SELECT azienda, COUNT(*) AS cnt
             FROM disponibilita
             WHERE deleted = 0
             GROUP BY azienda
             ORDER BY cnt DESC"
        );

        if ($stmt === false) {
            return [];
        }

        $rows = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                'value' => (string) ($row['azienda'] ?? ''),
                'count' => (int) ($row['cnt'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return string[]
     */
    public function listBrandNames(): array
    {
        $names = [];

        foreach ($this->brandByName as $brand) {
            $names[] = (string) $brand->get('name');
        }

        sort($names);

        return $names;
    }

    /**
     * @return array<string, string>
     */
    private function loadColorMap(?string $colorsJsonPath): array
    {
        $path = $this->resolveColorMapPath($colorsJsonPath);

        if ($path === null) {
            return AppuntamentoCalendarColor::defaultBrandColorMap();
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            return [];
        }

        $map = [];

        foreach ($decoded as $name => $color) {
            if (!is_string($name) || str_starts_with($name, '_')) {
                continue;
            }

            if (!is_string($color)) {
                continue;
            }

            $name = $this->normalizeBrandKey($name);
            $color = trim($color);

            if ($name === '' || $color === '') {
                continue;
            }

            $map[$name] = $color;
        }

        if ($map === []) {
            return AppuntamentoCalendarColor::defaultBrandColorMap();
        }

        return $map;
    }

    private function resolveColorMapPath(?string $colorsJsonPath): ?string
    {
        $crmRoot = $this->resolveCrmRoot();

        $paths = array_filter([
            $colorsJsonPath,
            $colorsJsonPath && !str_starts_with((string) $colorsJsonPath, '/')
                ? $crmRoot . '/' . ltrim((string) $colorsJsonPath, '/')
                : $colorsJsonPath,
            $crmRoot . '/tools/data/brand-calendar-colors.json',
            $crmRoot . '/tools/data/brand-calendar-colors.example.json',
            dirname(__DIR__, 4) . '/tools/data/brand-calendar-colors.json',
            dirname(__DIR__, 4) . '/tools/data/brand-calendar-colors.example.json',
        ]);

        foreach ($paths as $path) {
            if ($path && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function resolveCrmRoot(): string
    {
        $cwd = getcwd();

        if ($cwd && is_file($cwd . '/bootstrap.php')) {
            return rtrim($cwd, '/');
        }

        $fromService = dirname(__DIR__, 4);

        if (is_file($fromService . '/bootstrap.php')) {
            return $fromService;
        }

        return rtrim((string) $cwd, '/');
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

            $this->brandByName[$this->normalizeBrandKey($name)] = $brand;
        }
    }

    private function normalizeBrandKey(string $name): string
    {
        $name = strtoupper(trim($name));
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return $name;
    }

    private function resolveBrandByName(?string $name): ?Entity
    {
        $name = $this->normalizeBrandKey((string) $name);

        if ($name === '') {
            return null;
        }

        if (isset($this->brandByName[$name])) {
            return $this->brandByName[$name];
        }

        $firstToken = explode(' ', $name)[0] ?? '';

        if ($firstToken !== '' && isset($this->brandByName[$firstToken])) {
            return $this->brandByName[$firstToken];
        }

        foreach ($this->brandByName as $key => $brand) {
            if (str_starts_with($name, $key) || str_starts_with($key, $name)) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $colorMap
     */
    private function resolveColorFromMap(string $brandName, array $colorMap): ?string
    {
        $key = $this->normalizeBrandKey($brandName);

        if (isset($colorMap[$key])) {
            return $colorMap[$key];
        }

        $firstToken = explode(' ', $key)[0] ?? '';

        if ($firstToken !== '' && isset($colorMap[$firstToken])) {
            return $colorMap[$firstToken];
        }

        foreach ($colorMap as $mapKey => $color) {
            if (str_starts_with($key, $mapKey) || str_starts_with($mapKey, $key)) {
                return $color;
            }
        }

        return null;
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

        if (!$this->entityHasAttribute('ProductBrand', 'color')) {
            return ['updated' => 0, 'skipped' => count($this->brandByName)];
        }

        foreach ($this->brandByName as $brand) {
            $displayName = trim((string) $brand->get('name'));
            $targetColor = $this->resolveColorFromMap($displayName, $colorMap);

            if ($targetColor === null) {
                if ($this->verbose) {
                    $this->logLine("Brand saltato (no colore in JSON): {$displayName}");
                }
                $skipped++;
                continue;
            }

            $currentColor = trim((string) ($brand->get('color') ?: ''));

            if ($currentColor !== '' && !$forceColor) {
                if ($this->verbose) {
                    $this->logLine("Brand saltato (colore già presente): {$displayName} = {$currentColor}");
                }
                $skipped++;
                continue;
            }

            if ($dryRun) {
                if ($this->verbose) {
                    $this->logLine("[dry-run] Brand {$displayName} → {$targetColor}");
                }
                $updated++;
                continue;
            }

            $brand->set('color', $targetColor);
            $this->entityManager->saveEntity($brand, [
                'skipHooks' => true,
                'silent' => true,
            ]);

            if ($this->verbose) {
                $this->logLine("Brand {$displayName} → {$targetColor}");
            }

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

        if (!$this->entityHasAttribute('WorkingTimeCalendar', 'generazioneProductBrandId')) {
            return ['updated' => 0, 'skipped' => 0];
        }

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
        $brand = $this->resolveDisponibilitaBrand($entity, true);

        if (!$brand) {
            if ($this->verbose) {
                $this->logLine(
                    'Disponibilità saltata (brand non trovato): '
                    . ($entity->get('name') ?: $entity->getId())
                );
            }

            return false;
        }

        if (!$entity->get('productBrandId')) {
            return true;
        }

        if ($forceColor) {
            return true;
        }

        $brandColor = trim((string) ($brand->get('color') ?: ''));

        if ($brandColor === '') {
            return false;
        }

        $currentColor = trim((string) ($entity->get('color') ?: ''));

        return $currentColor === '';
    }

    private function resolveDisponibilitaLabel(Entity $entity): ?string
    {
        $candidates = [
            trim((string) ($entity->get('azienda') ?: '')),
            $this->readDisponibilitaFieldFromDb($entity->getId(), 'azienda'),
            $this->parseLabelFromDisponibilitaName($entity->get('name')),
        ];

        foreach ($candidates as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function parseLabelFromDisponibilitaName(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        if (preg_match('/^([^|]+)/u', $name, $matches)) {
            $label = trim($matches[1]);

            return $label !== '' ? $label : null;
        }

        return null;
    }

    private function readDisponibilitaFieldFromDb(?string $id, string $column): ?string
    {
        if (!$id || !$this->tableHasColumn('disponibilita', $column)) {
            return null;
        }

        $pdo = $this->entityManager->getPDO();
        $stmt = $pdo->prepare(
            "SELECT `{$column}` FROM disponibilita WHERE id = :id AND deleted = 0 LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $value = trim((string) ($row[$column] ?? ''));

        return $value !== '' ? $value : null;
    }

    private function resolveDisponibilitaBrand(Entity $entity, bool $allowLegacyAzienda = true): ?Entity
    {
        $brandId = $entity->get('productBrandId');

        if ($brandId) {
            $brand = $this->entityManager->getEntityById('ProductBrand', $brandId);

            if ($brand) {
                return $brand;
            }
        }

        if (!$allowLegacyAzienda) {
            return null;
        }

        $label = $this->resolveDisponibilitaLabel($entity);

        return $this->resolveBrandByName($label);
    }

    /**
     * @return array{updated: int, skipped: int}
     */
    private function backfillAppuntamenti(bool $dryRun, int $limit, bool $forceColor): array
    {
        $updated = 0;
        $skipped = 0;

        $colorResolver = new AppuntamentoCalendarColor($this->entityManager);

        $query = $this->entityManager
            ->getRDBRepository('Appuntamento')
            ->order('createdAt', 'ASC');

        if ($limit > 0) {
            $query->limit(0, $limit);
        }

        foreach ($query->find() as $entity) {
            $targetColor = $colorResolver->resolveColor($entity);

            if ($targetColor === null) {
                $skipped++;
                continue;
            }

            $currentColor = trim((string) ($entity->get('color') ?: ''));

            if (!$forceColor && $currentColor === $targetColor) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $updated++;
                continue;
            }

            $entity->set('color', $targetColor);
            $this->entityManager->saveEntity($entity, [
                'skipHooks' => true,
                'silent' => true,
            ]);
            $updated++;
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    private function entityHasAttribute(string $entityType, string $attribute): bool
    {
        return $this->entityManager->getNewEntity($entityType)->hasAttribute($attribute);
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

    private function logLine(string $message): void
    {
        $this->log[] = $message;
    }
}
