<?php

declare(strict_types=1);

namespace App\Service\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PDO;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Importe une base SQLite Hermes 2.2.x (schéma « feuille / menu / section / post »)
 * vers le schéma Hermes 3 (menus arborescents, sans {@see \App\Entity\Sheet}).
 *
 * Règles principales :
 * - chaque ligne {@code sheet} devient un menu racine (parent null) ;
 * - chaque menu 2.x rattaché à une feuille devient enfant de ce menu racine ;
 * - {@code template}, {@code user}, {@code section}, {@code post} sont recopiés en conservant les ids
 *   (base cible vide ou écrasée avec --force) ;
 * - les sections dont le menu source est absent ou non mappé sont ignorées (les posts qui y sont liés aussi) ;
 * - les chaînes trop longues pour les contraintes Hermes 3 (ex. nom 25 car.) sont tronquées ;
 * - si la source expose {@code parent_id} sur {@code menu}, l’ordre d’insertion respecte la hiérarchie ;
 * - un menu source dont le nom est identique (sans tenir compte de la casse) au menu parent ou au nom de la
 *   feuille (lorsqu’il n’a qu’un {@code sheet_id}) est fusionné : aucune ligne menu enfant n’est créée,
 *   les anciens ids pointent vers le menu parent cible ;
 * - colonnes supprimées en 3.x (ex. {@code url} sur post, {@code name} sur section, {@code user_id} sur menu) sont ignorées ;
 * - si un fichier {@code config/<nom>.sqlite} existe au même niveau que la base source (ex. {@code data/atlas.sqlite}
 *   → {@code data/config/atlas.sqlite}), sa table {@code config} est recopiée dans la base cible ;
 * - dans le HTML des posts (et champs texte config), les URLs Hermes 2.2.x
 *   {@code /{nom}/uploads/…} sont réécrites en {@code /uploads/{nom}/…} ;
 *   {@code {nom}} source = stem de {@code dataFrom}, cible = stem de {@code dataTo}
 *   (ex. {@code data/db/jazzenville.sqlite} → {@code /jazzenville/uploads/} → {@code /uploads/jazzenville/}).
 *
 * La génération du DDL repose sur le schéma Doctrine du projet : l’URL Doctrine par défaut
 * ({@code DATABASE_URL}) doit être **SQLite**, afin que le SQL créé corresponde au fichier cible.
 */
final class Hermes227SqliteMigrator
{
    private const STR_NAME_MAX = 25;

    private const STR_SLUG_MAX = 50;

    private const STR_CODE_MAX = 50;

    private const STR_TYPE_MAX = 50;

    private const STR_SUMMARY_MAX = 255;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    /**
     * Copie les données de l’ancienne base vers la nouvelle (schéma Hermes 3).
     */
    public function migrate(string $dataFrom, string $dataTo, bool $force, callable $log): void
    {
        $fromPath = $this->normalizeSqlitePath($dataFrom);
        $toPath = $this->normalizeSqlitePath($dataTo);

        if (!is_file($fromPath)) {
            throw new \InvalidArgumentException(sprintf('Le fichier source n’existe pas : %s', $fromPath));
        }

        if ($fromPath === $toPath) {
            throw new \InvalidArgumentException('Les chemins source et cible doivent être différents.');
        }

        $toDir = \dirname($toPath);
        if (!is_dir($toDir) && !@mkdir($toDir, 0775, true) && !is_dir($toDir)) {
            throw new \InvalidArgumentException(sprintf('Impossible de créer le répertoire cible : %s', $toDir));
        }

        if (is_file($toPath) && filesize($toPath) > 0 && !$force) {
            throw new \InvalidArgumentException(
                'Le fichier cible existe déjà. Utilisez --force pour le réinitialiser (schéma supprimé puis recréé).',
            );
        }

        if (!is_file($toPath)) {
            touch($toPath);
        }

        $source = new PDO('sqlite:' . $fromPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        foreach (['template', 'menu', 'section', 'post'] as $required) {
            if (!$this->tableExists($source, $required)) {
                throw new \InvalidArgumentException(sprintf('La base source ne contient pas la table « %s ».', $required));
            }
        }

        $hasSheet = $this->tableExists($source, 'sheet');
        $hasUser = $this->tableExists($source, 'user');

        $this->createTargetSchema($toPath, $force, $log);

        $target = new PDO('sqlite:' . $toPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $target->exec('PRAGMA foreign_keys = OFF');
        $this->truncateHermes3Tables($target, $log);

        if ($hasUser) {
            $n = $this->migrateUsers($source, $target);
            $log(sprintf('%d utilisateur(s) migré(s).', $n));
        } else {
            $log('Aucune table « user » en source — étape ignorée.');
        }

        $nTpl = $this->migrateTemplates($source, $target);
        $log(sprintf('%d gabarit(s) (template) migré(s).', $nTpl));

        $sheetToRootMenu = [];
        if ($hasSheet) {
            $sheetToRootMenu = $this->migrateSheetsAsRootMenus($source, $target);
            $log(sprintf('%d feuille(s) (sheet) → menu(x) racine.', \count($sheetToRootMenu)));
        } else {
            $log('Aucune table « sheet » — les menus sources resteront sans parent (racines).');
        }

        $menuResult = $this->migrateMenus($source, $target, $sheetToRootMenu);
        $menuIdMap = $menuResult['map'];
        $log(sprintf(
            '%d menu(x) source mappé(s) : %d ligne(s) « menu » créée(s), %d fusionnée(s) (même nom que le parent ou la feuille, sans tenir compte de la casse).',
            \count($menuIdMap),
            $menuResult['inserted'],
            $menuResult['merged'],
        ));

        $sectionResult = $this->migrateSections($source, $target, $menuIdMap);
        $log(sprintf(
            '%d section(s) migrée(s), %d ignorée(s) (menu absent ou non mappé).',
            $sectionResult['inserted'],
            $sectionResult['skipped'],
        ));

        $legacyMediaSite = $this->resolveDbStem($fromPath);
        $targetMediaSite = $this->resolveDbStem($toPath);
        $log(sprintf(
            'Réécriture URLs médias : /%s/uploads/ → /uploads/%s/ (stem dataFrom → stem dataTo).',
            $legacyMediaSite,
            $targetMediaSite,
        ));

        $postResult = $this->migratePosts(
            $source,
            $target,
            $sectionResult['insertedSectionLocales'],
            $legacyMediaSite,
            $targetMediaSite,
        );
        $log(sprintf(
            '%d publication(s) (post) migrée(s), %d ignorée(s) (section absente ou non migrée).',
            $postResult['inserted'],
            $postResult['skipped'],
        ));
        if ($postResult['urlsRewritten'] > 0) {
            $log(sprintf(
                '%d publication(s) : URLs médias réécrites (/%s/uploads/ → /uploads/%s/).',
                $postResult['urlsRewritten'],
                $legacyMediaSite,
                $targetMediaSite,
            ));
        }

        $nCfg = $this->migrateConfigFromCompanionSqlite($fromPath, $target, $legacyMediaSite, $targetMediaSite, $log);
        if ($nCfg > 0) {
            $log(sprintf('%d entrée(s) de configuration migrée(s) depuis la base compagnon.', $nCfg));
        }

        $target->exec('PRAGMA foreign_keys = ON');
    }

    private function normalizeSqlitePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new \InvalidArgumentException('Chemin vide.');
        }
        if (!str_starts_with($path, '/')) {
            $path = $this->projectDir . '/' . ltrim($path, '/');
        }
        $dir = \dirname($path);
        $base = basename($path);
        $realDir = realpath($dir);

        return $realDir === false ? $path : $realDir . \DIRECTORY_SEPARATOR . $base;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    }

    private function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        if (!$this->tableExists($pdo, $table)) {
            return false;
        }
        $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (($row['name'] ?? '') === $column) {
                return true;
            }
        }

        return false;
    }

    private function createTargetSchema(string $toPath, bool $force, callable $log): void
    {
        $tool = new SchemaTool($this->entityManager);
        $meta = $this->entityManager->getMetadataFactory()->getAllMetadata();

        $targetConn = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => $toPath,
        ]);

        if (!$targetConn->getDatabasePlatform() instanceof SQLitePlatform) {
            throw new \InvalidArgumentException('La base cible doit être un fichier SQLite.');
        }

        if ($force) {
            $log('Suppression des tables existantes sur la base cible (si présent)…');
            $this->dropAllSqliteUserTables($targetConn);
        }

        $log('Création du schéma Doctrine Hermes 3 sur la base cible…');
        foreach ($tool->getCreateSchemaSql($meta) as $sql) {
            $targetConn->executeStatement($sql);
        }
    }

    private function dropAllSqliteUserTables(Connection $conn): void
    {
        $conn->executeStatement('PRAGMA foreign_keys = OFF');
        $schema = $conn->createSchemaManager()->introspectSchema();
        foreach ($schema->toDropSql($conn->getDatabasePlatform()) as $sql) {
            try {
                $conn->executeStatement($sql);
            } catch (\Throwable) {
            }
        }
    }

    private function truncateHermes3Tables(PDO $target, callable $log): void
    {
        foreach (['post', 'section', 'menu', 'template', 'user', 'config'] as $t) {
            if ($this->tableExists($target, $t)) {
                $target->exec('DELETE FROM ' . $t);
            }
        }
        $log('Tables Hermes 3 vidées (post, section, menu, template, user, config).');
    }

    private function migrateUsers(PDO $source, PDO $target): int
    {
        $rows = $source->query('SELECT * FROM user')->fetchAll(PDO::FETCH_ASSOC);
        $hasNewsletterCols = $this->tableHasColumn($source, 'user', 'active_newsletter');
        if ($hasNewsletterCols) {
            $stmt = $target->prepare(
                'INSERT INTO user (id, email, roles, password, is_verified, firstname, lastname, active_newsletter) VALUES (:id, :email, :roles, :password, :is_verified, :firstname, :lastname, :active_newsletter)',
            );
        } else {
            $stmt = $target->prepare(
                'INSERT INTO user (id, email, roles, password, is_verified) VALUES (:id, :email, :roles, :password, :is_verified)',
            );
        }
        $n = 0;
        foreach ($rows as $r) {
            $roles = $r['roles'] ?? '[]';
            if (\is_array($roles)) {
                $roles = json_encode($roles, JSON_THROW_ON_ERROR);
            }
            $params = [
                'id' => (int) $r['id'],
                'email' => (string) ($r['email'] ?? 'user' . $r['id'] . '@migrated.invalid'),
                'roles' => (string) $roles,
                'password' => (string) ($r['password'] ?? ''),
                'is_verified' => 1,
            ];
            if ($hasNewsletterCols) {
                $params['firstname'] = $r['firstname'] ?? null;
                $params['lastname'] = $r['lastname'] ?? null;
                $params['active_newsletter'] = isset($r['active_newsletter']) ? (int) (bool) $r['active_newsletter'] : 1;
            }
            $stmt->execute($params);
            ++$n;
        }

        if ($n > 0 && $this->tableExists($target, 'sqlite_sequence')) {
            $target->exec('DELETE FROM sqlite_sequence WHERE name = ' . $target->quote('user'));
            $max = (int) $target->query('SELECT MAX(id) FROM user')->fetchColumn();
            $target->prepare('INSERT INTO sqlite_sequence (name, seq) VALUES (?, ?)')->execute(['user', $max]);
        }

        return $n;
    }

    private function migrateTemplates(PDO $source, PDO $target): int
    {
        $rows = $source->query('SELECT * FROM template')->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $target->prepare(
            'INSERT INTO template (id, active, type, code, name, summary) VALUES (:id, :active, :type, :code, :name, :summary)',
        );
        foreach ($rows as $r) {
            $stmt->execute([
                'id' => (int) $r['id'],
                'active' => (int) ($r['active'] ?? 1),
                'type' => $this->clipString($r['type'] ?? null, self::STR_TYPE_MAX) ?: null,
                'code' => $this->clipString($r['code'] ?? null, self::STR_CODE_MAX) ?: null,
                'name' => $this->clipString($r['name'] ?? '', self::STR_NAME_MAX),
                'summary' => $this->clipString($r['summary'] ?? '', self::STR_SUMMARY_MAX),
            ]);
        }
        $this->syncSqliteSequence($target, 'template');

        return \count($rows);
    }

    /**
     * @return array<int, int> sheet_id => nouveau menu_id racine
     */
    private function migrateSheetsAsRootMenus(PDO $source, PDO $target): array
    {
        $map = [];
        $rows = $source->query('SELECT * FROM sheet ORDER BY position ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $target->prepare(
            'INSERT INTO menu (code, active, name, slug, position, locale, updated_at, parent_id)
             VALUES (:code, :active, :name, :slug, :position, :locale, :updated_at, NULL)',
        );
        foreach ($rows as $r) {
            $code = $r['code'] ?? $r['reference_name'] ?? ('sheet-' . $r['id']);
            $codeStr = $this->clipString((string) $code, self::STR_CODE_MAX);
            $stmt->execute([
                'code' => $codeStr,
                'active' => (int) ($r['active'] ?? 1),
                'name' => $this->clipString((string) ($r['name'] ?? 'Feuille'), self::STR_NAME_MAX),
                'slug' => $this->clipString((string) ($r['slug'] ?? $code), self::STR_SLUG_MAX),
                'position' => (int) ($r['position'] ?? 0),
                'locale' => (string) ($r['locale'] ?? 'fr'),
                'updated_at' => $this->normalizeDateTime($r['updated_at'] ?? null),
            ]);
            $map[(int) $r['id']] = (int) $target->lastInsertId();
        }
        $this->syncSqliteSequence($target, 'menu');

        return $map;
    }

    /**
     * @param array<int, int> $sheetToRootMenu
     *
     * @return array{map: array<int, int>, inserted: int, merged: int}
     */
    private function migrateMenus(PDO $source, PDO $target, array $sheetToRootMenu): array
    {
        $menuCols = $this->getTableColumns($source, 'menu');
        $hasSheetId = \in_array('sheet_id', $menuCols, true);
        $hasParentId = \in_array('parent_id', $menuCols, true);
        $hasRefName = \in_array('reference_name', $menuCols, true);

        $rows = $source->query('SELECT * FROM menu ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        /** @var array<int, array<string, mixed>> $rowsByOldId */
        $rowsByOldId = [];
        foreach ($rows as $r) {
            $rowsByOldId[(int) $r['id']] = $r;
        }
        $sheetNames = $this->loadSheetNamesById($source);
        $mergeSpec = $this->buildMenuSameNameAsParentMergeSpec(
            $rows,
            $rowsByOldId,
            $sheetNames,
            $hasParentId,
            $hasSheetId,
        );

        $stmt = $target->prepare(
            'INSERT INTO menu (code, active, name, slug, position, locale, reference_name, updated_at, parent_id)
             VALUES (:code, :active, :name, :slug, :position, :locale, :reference_name, :updated_at, :parent_id)',
        );

        $map = [];
        $menusInserted = 0;
        /** @var array<int, array<string, mixed>> $pending */
        $pending = [];
        foreach ($rows as $r) {
            $pending[(int) $r['id']] = $r;
        }

        while ($pending !== []) {
            $before = \count($pending);

            do {
                $mergedAny = false;
                foreach ($pending as $oldId => $_r) {
                    if (!isset($mergeSpec[$oldId])) {
                        continue;
                    }
                    $spec = $mergeSpec[$oldId];
                    if ($spec['kind'] === 'sheet') {
                        $nid = $sheetToRootMenu[$spec['sheetId']] ?? null;
                        if ($nid !== null) {
                            $map[$oldId] = $nid;
                            unset($pending[$oldId]);
                            $mergedAny = true;
                        }
                    } elseif (isset($map[$spec['menuOldId']])) {
                        $map[$oldId] = $map[$spec['menuOldId']];
                        unset($pending[$oldId]);
                        $mergedAny = true;
                    }
                }
            } while ($mergedAny);

            foreach ($pending as $oldId => $r) {
                if (isset($mergeSpec[$oldId])) {
                    continue;
                }
                $resolved = $this->resolveMenuParentForInsert(
                    $r,
                    $map,
                    $sheetToRootMenu,
                    $hasParentId,
                    $hasSheetId,
                );
                if ($resolved === false) {
                    continue;
                }

                $code = $r['code'] ?? ($hasRefName ? ($r['reference_name'] ?? null) : null) ?? ('menu-' . $oldId);
                $codeStr = $this->clipString((string) $code, self::STR_CODE_MAX);
                $refName = $hasRefName
                    ? strtolower(trim((string) ($r['reference_name'] ?? $codeStr)))
                    : strtolower(trim($codeStr));
                if ($refName === '') {
                    $refName = 'menu-' . $oldId;
                }
                $stmt->execute([
                    'code' => $codeStr,
                    'active' => (int) ($r['active'] ?? 1),
                    'name' => $this->clipString((string) ($r['name'] ?? ''), self::STR_NAME_MAX),
                    'slug' => $this->clipString((string) ($r['slug'] ?? $code), self::STR_SLUG_MAX),
                    'position' => (int) ($r['position'] ?? 0),
                    'locale' => (string) ($r['locale'] ?? 'fr'),
                    'reference_name' => $this->clipString($refName, 100),
                    'updated_at' => $this->normalizeDateTime($r['updated_at'] ?? null),
                    'parent_id' => $resolved,
                ]);
                $map[$oldId] = (int) $target->lastInsertId();
                ++$menusInserted;
                unset($pending[$oldId]);
            }
            if (\count($pending) === $before) {
                throw new \RuntimeException(
                    'Impossible de résoudre la hiérarchie des menus (parent_id manquant, cycle ou référence invalide).',
                );
            }
        }
        $this->syncSqliteSequence($target, 'menu');

        $merged = \count($rows) - $menusInserted;

        return ['map' => $map, 'inserted' => $menusInserted, 'merged' => $merged];
    }

    /** @return array<int, string> */
    private function loadSheetNamesById(PDO $source): array
    {
        if (!$this->tableExists($source, 'sheet')) {
            return [];
        }
        $out = [];
        foreach ($source->query('SELECT id, name FROM sheet')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['id']] = (string) ($row['name'] ?? '');
        }

        return $out;
    }

    /**
     * Menus à fusionner avec le parent (même libellé, insensible à la casse) : pas d’INSERT, seulement {@code $map}.
     *
     * @param list<array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $rowsByOldId
     *
     * @return array<int, array{kind: 'sheet', sheetId: int}|array{kind: 'menu', menuOldId: int}>
     */
    private function buildMenuSameNameAsParentMergeSpec(
        array $rows,
        array $rowsByOldId,
        array $sheetNames,
        bool $hasParentId,
        bool $hasSheetId,
    ): array {
        $spec = [];
        foreach ($rows as $r) {
            $oldId = (int) $r['id'];
            $self = $this->normalizedMenuLabel((string) ($r['name'] ?? ''));
            if ($self === '') {
                continue;
            }
            if ($hasParentId && isset($r['parent_id']) && $r['parent_id'] !== null && $r['parent_id'] !== '') {
                $pid = (int) $r['parent_id'];
                $parentRow = $rowsByOldId[$pid] ?? null;
                if ($parentRow !== null) {
                    $pLabel = $this->normalizedMenuLabel((string) ($parentRow['name'] ?? ''));
                    if ($pLabel !== '' && $self === $pLabel) {
                        $spec[$oldId] = ['kind' => 'menu', 'menuOldId' => $pid];

                        continue;
                    }
                }
            }
            if ($hasSheetId && isset($r['sheet_id']) && $r['sheet_id'] !== null && $r['sheet_id'] !== '') {
                $sid = (int) $r['sheet_id'];
                $sheetLabel = $this->normalizedMenuLabel($sheetNames[$sid] ?? '');
                if ($sheetLabel !== '' && $self === $sheetLabel) {
                    $spec[$oldId] = ['kind' => 'sheet', 'sheetId' => $sid];
                }
            }
        }

        return $spec;
    }

    private function normalizedMenuLabel(string $name): string
    {
        $t = trim($name);
        if ($t === '') {
            return '';
        }
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($t, 'UTF-8');
        }

        return strtolower($t);
    }

    /**
     * @param array<int, int> $map ancien menu.id => nouveau menu.id
     * @param array<int, int> $sheetToRootMenu
     *
     * @return false si le parent référencé n’est pas encore migré ; sinon le parent_id SQL (int ou null)
     */
    private function resolveMenuParentForInsert(
        array $r,
        array $map,
        array $sheetToRootMenu,
        bool $hasParentId,
        bool $hasSheetId,
    ): int|false|null {
        if ($hasParentId && isset($r['parent_id']) && $r['parent_id'] !== null && $r['parent_id'] !== '') {
            $pid = (int) $r['parent_id'];
            if (!isset($map[$pid])) {
                return false;
            }

            return $map[$pid];
        }
        if ($hasSheetId && isset($r['sheet_id']) && $r['sheet_id'] !== null && $r['sheet_id'] !== '') {
            $sid = (int) $r['sheet_id'];

            return $sheetToRootMenu[$sid] ?? null;
        }

        return null;
    }

    /**
     * @param array<int, int> $menuIdMap
     *
     * @return array{inserted: int, skipped: int, insertedSectionLocales: array<int, string>}
     */
    private function migrateSections(PDO $source, PDO $target, array $menuIdMap): array
    {
        $sectionCols = $this->getTableColumns($source, 'section');
        $menuFkCol = \in_array('menu_id', $sectionCols, true) ? 'menu_id' : (\in_array('menu', $sectionCols, true) ? 'menu' : null);
        $hasSectionLocale = \in_array('locale', $sectionCols, true);
        $hasSectionReferenceName = \in_array('reference_name', $sectionCols, true);

        $rows = $source->query('SELECT * FROM section ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $target->prepare(
            'INSERT INTO section (id, active, position, menu_id, template_id, template_width, template2_id, template2_width,
                transparent, template_bgcolor, template_nb_col, template_image_filter, locale, reference_name)
             VALUES (:id, :active, :position, :menu_id, :template_id, :template_width, :template2_id, :template2_width,
                :transparent, :template_bgcolor, :template_nb_col, :template_image_filter, :locale, :reference_name)',
        );
        $inserted = 0;
        $skipped = 0;
        /** @var array<int, string> $insertedSectionLocales */
        $insertedSectionLocales = [];
        foreach ($rows as $r) {
            $newMenuId = null;
            if ($menuFkCol !== null) {
                $rawMenu = $r[$menuFkCol] ?? null;
                if ($rawMenu !== null && $rawMenu !== '') {
                    $oldMenuId = (int) $rawMenu;
                    $newMenuId = $menuIdMap[$oldMenuId] ?? null;
                    if ($newMenuId === null) {
                        ++$skipped;
                        continue;
                    }
                }
            }

            $sectionLocale = null;
            if ($hasSectionLocale) {
                $rawLocale = strtolower(trim((string) ($r['locale'] ?? '')));
                $sectionLocale = $rawLocale !== '' ? $rawLocale : null;
            }
            if ($sectionLocale === null && $newMenuId !== null) {
                $menuLocaleStmt = $target->prepare('SELECT locale FROM menu WHERE id = ?');
                $menuLocaleStmt->execute([$newMenuId]);
                $menuLocale = strtolower(trim((string) ($menuLocaleStmt->fetchColumn() ?? '')));
                $sectionLocale = $menuLocale !== '' ? $menuLocale : null;
            }

            $sectionReferenceName = null;
            if ($hasSectionReferenceName) {
                $ref = strtolower(trim((string) ($r['reference_name'] ?? '')));
                $sectionReferenceName = $ref !== '' ? $this->clipString($ref, 100) : null;
            }

            $stmt->execute([
                'id' => (int) $r['id'],
                'active' => (int) ($r['active'] ?? 1),
                'position' => (int) ($r['position'] ?? 0),
                'menu_id' => $newMenuId,
                'template_id' => $r['template_id'] !== null && $r['template_id'] !== '' ? (int) $r['template_id'] : null,
                'template_width' => $r['template_width'] !== null && $r['template_width'] !== '' ? (int) $r['template_width'] : null,
                'template2_id' => $r['template2_id'] !== null && $r['template2_id'] !== '' ? (int) $r['template2_id'] : null,
                'template2_width' => $r['template2_width'] !== null && $r['template2_width'] !== '' ? (int) $r['template2_width'] : null,
                'transparent' => $this->nullableBoolToInt($r['transparent'] ?? null),
                'template_bgcolor' => $r['template_bgcolor'] ?? null,
                'template_nb_col' => $r['template_nb_col'] !== null && $r['template_nb_col'] !== '' ? (int) $r['template_nb_col'] : null,
                'template_image_filter' => $r['template_image_filter'] ?? null,
                'locale' => $sectionLocale,
                'reference_name' => $sectionReferenceName,
            ]);
            $insertedSectionLocales[(int) $r['id']] = $sectionLocale ?? 'fr';
            ++$inserted;
        }
        $this->syncSqliteSequence($target, 'section');

        return ['inserted' => $inserted, 'skipped' => $skipped, 'insertedSectionLocales' => $insertedSectionLocales];
    }

    /**
     * @param array<int, string> $insertedSectionLocales
     *
     * @return array{inserted: int, skipped: int, urlsRewritten: int}
     */
    private function migratePosts(
        PDO $source,
        PDO $target,
        array $insertedSectionLocales,
        string $legacyMediaSite,
        string $targetMediaSite,
    ): array {
        $postCols = $this->getTableColumns($source, 'post');
        $sectionCol = \in_array('section_id', $postCols, true) ? 'section_id' : (\in_array('section', $postCols, true) ? 'section' : null);
        if ($sectionCol === null) {
            throw new \InvalidArgumentException('La table post de la source n’a ni colonne « section_id » ni « section ».');
        }
        $hasLocale = \in_array('locale', $postCols, true);

        $rows = $source->query('SELECT * FROM post ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $target->prepare(
            'INSERT INTO post (id, active, start_published_at, end_published_at, position, name, locale, section_id, content, file_name, updated_at)
             VALUES (:id, :active, :start_published_at, :end_published_at, :position, :name, :locale, :section_id, :content, :file_name, :updated_at)',
        );
        $inserted = 0;
        $skipped = 0;
        $urlsRewritten = 0;
        foreach ($rows as $r) {
            $rawSec = $r[$sectionCol] ?? null;
            if ($rawSec === null || $rawSec === '') {
                ++$skipped;
                continue;
            }
            $sectionId = (int) $rawSec;
            if (!isset($insertedSectionLocales[$sectionId])) {
                ++$skipped;
                continue;
            }
            $rawContent = $r['content'] ?? null;
            $content = $this->rewriteLegacyMediaUrls($rawContent, $legacyMediaSite, $targetMediaSite);
            if ($rawContent !== null && $content !== $rawContent) {
                ++$urlsRewritten;
            }
            $postLocale = 'fr';
            if ($hasLocale) {
                $rawPostLocale = strtolower(trim((string) ($r['locale'] ?? '')));
                $postLocale = $rawPostLocale !== '' ? $rawPostLocale : ($insertedSectionLocales[$sectionId] ?? 'fr');
            } else {
                $postLocale = $insertedSectionLocales[$sectionId] ?? 'fr';
            }
            $stmt->execute([
                'id' => (int) $r['id'],
                'active' => (int) ($r['active'] ?? 1),
                'start_published_at' => $this->normalizeDateTime($r['start_published_at'] ?? null),
                'end_published_at' => $this->normalizeDateTime($r['end_published_at'] ?? null),
                'position' => (int) ($r['position'] ?? 0),
                'name' => $this->clipString((string) ($r['name'] ?? ''), self::STR_NAME_MAX),
                'locale' => $postLocale,
                'section_id' => $sectionId,
                'content' => $content,
                'file_name' => $r['file_name'] ?? null,
                'updated_at' => $this->normalizeDateTime($r['updated_at'] ?? null),
            ]);
            ++$inserted;
        }
        $this->syncSqliteSequence($target, 'post');

        return ['inserted' => $inserted, 'skipped' => $skipped, 'urlsRewritten' => $urlsRewritten];
    }

    /**
     * Base Hermes 2.x : {@code <répertoire>/config/<fichier>.sqlite} (même nom de fichier que la base principale source).
     *
     * @return int nombre de lignes insérées dans {@code config} sur la cible
     */
    private function migrateConfigFromCompanionSqlite(
        string $normalizedMainDbPath,
        PDO $target,
        string $legacyMediaSite,
        string $targetMediaSite,
        callable $log,
    ): int {
        $configPath = $this->resolveCompanionConfigSqlitePath($normalizedMainDbPath);
        if (!is_file($configPath)) {
            $log(sprintf('Aucune base compagnon « config » (%s) — étape ignorée.', $configPath));

            return 0;
        }

        $configSource = new PDO('sqlite:' . $configPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        if (!$this->tableExists($configSource, 'config')) {
            $log(sprintf('Le fichier %s ne contient pas la table « config » — étape ignorée.', $configPath));

            return 0;
        }

        if (!$this->tableExists($target, 'config')) {
            throw new \RuntimeException('La base cible n’a pas de table « config ».');
        }

        $targetCols = $this->getTableColumns($target, 'config');
        $rows = $configSource->query('SELECT * FROM config ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return 0;
        }

        $quotedCols = [];
        foreach ($targetCols as $c) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $c)) {
                throw new \InvalidArgumentException('Nom de colonne config invalide.');
            }
            $quotedCols[] = '"' . $c . '"';
        }
        $colSql = implode(', ', $quotedCols);
        $phSql = implode(', ', array_map(static fn (string $c): string => ':' . $c, $targetCols));
        $stmt = $target->prepare('INSERT INTO config (' . $colSql . ') VALUES (' . $phSql . ')');

        foreach ($rows as $r) {
            $params = $this->mapConfigSourceRowToTarget($targetCols, $r, $legacyMediaSite, $targetMediaSite);
            $bound = [];
            foreach ($params as $k => $v) {
                $bound[':' . $k] = $v;
            }
            $stmt->execute($bound);
        }
        $this->syncSqliteSequence($target, 'config');

        return \count($rows);
    }

    private function resolveCompanionConfigSqlitePath(string $normalizedMainDbPath): string
    {
        $dir = \dirname($normalizedMainDbPath);
        $baseName = basename($normalizedMainDbPath);

        return $dir . \DIRECTORY_SEPARATOR . 'config' . \DIRECTORY_SEPARATOR . $baseName;
    }

    /** Nom de base sans {@code .sqlite} (stem du chemin passé à {@code app:migrate}). */
    private function resolveDbStem(string $normalizedSqlitePath): string
    {
        $stem = pathinfo($normalizedSqlitePath, \PATHINFO_FILENAME);

        return $stem !== '' ? $stem : 'app';
    }

    /**
     * Hermes 2.2.x : {@code /{site}/uploads/…} → Hermes 3 : {@code /uploads/{site}/…}.
     */
    private function rewriteLegacyMediaUrls(?string $text, string $legacyMediaSite, string $targetMediaSite): ?string
    {
        if ($text === null || $text === '' || $legacyMediaSite === '' || $targetMediaSite === '') {
            return $text;
        }

        $legacy = '/' . $legacyMediaSite . '/uploads/';
        $modern = '/uploads/' . $targetMediaSite . '/';

        if ($legacy === $modern) {
            return $text;
        }

        return str_replace($legacy, $modern, $text);
    }

    /**
     * @param list<string> $targetCols
     * @param array<string, mixed> $sourceRow
     *
     * @return array<string, mixed>
     */
    private function mapConfigSourceRowToTarget(
        array $targetCols,
        array $sourceRow,
        string $legacyMediaSite,
        string $targetMediaSite,
    ): array {
        $params = [];
        foreach ($targetCols as $col) {
            $params[$col] = match ($col) {
                'id' => (int) ($sourceRow['id'] ?? 0),
                'active' => (int) (bool) ($sourceRow['active'] ?? 1),
                'position' => (int) ($sourceRow['position'] ?? 0),
                'transparent' => \array_key_exists('transparent', $sourceRow)
                    ? $this->nullableBoolToInt($sourceRow['transparent'])
                    : null,
                'published_at' => $this->normalizeDateTime($sourceRow['published_at'] ?? null)
                    ?? (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => $this->normalizeDateTime($sourceRow['updated_at'] ?? null),
                'start_published_at' => $this->normalizeDateTime($sourceRow['start_published_at'] ?? null),
                'end_published_at' => $this->normalizeDateTime($sourceRow['end_published_at'] ?? null),
                'code' => $this->clipString($sourceRow['code'] ?? null, self::STR_CODE_MAX) ?: null,
                'type' => $this->clipString($sourceRow['type'] ?? null, self::STR_TYPE_MAX) ?: null,
                'value' => $this->clipString(
                    $this->rewriteLegacyMediaUrls(
                        isset($sourceRow['value']) ? (string) $sourceRow['value'] : null,
                        $legacyMediaSite,
                        $targetMediaSite,
                    ),
                    250,
                ) ?: null,
                'summary' => $this->clipString(
                    $this->rewriteLegacyMediaUrls(
                        isset($sourceRow['summary']) ? (string) $sourceRow['summary'] : null,
                        $legacyMediaSite,
                        $targetMediaSite,
                    ),
                    self::STR_SUMMARY_MAX,
                ) ?: null,
                'file_name' => $this->clipString($sourceRow['file_name'] ?? null, 255) ?: null,
                default => \array_key_exists($col, $sourceRow) ? $sourceRow[$col] : null,
            };
        }

        return $params;
    }

    private function clipString(?string $value, int $maxChars): string
    {
        $s = (string) ($value ?? '');
        if ($maxChars <= 0) {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, $maxChars, 'UTF-8');
        }

        return substr($s, 0, $maxChars);
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    private function nullableBoolToInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) (bool) $value;
    }

    /** @return list<string> */
    private function getTableColumns(PDO $pdo, string $table): array
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Nom de table invalide.');
        }
        $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
        $cols = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[] = (string) $row['name'];
        }

        return $cols;
    }

    private function syncSqliteSequence(PDO $target, string $table): void
    {
        static $allowed = ['user', 'template', 'menu', 'section', 'post', 'config'];
        if (!\in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('Table non autorisée pour sqlite_sequence.');
        }
        if (!$this->tableExists($target, 'sqlite_sequence')) {
            return;
        }
        $max = (int) $target->query('SELECT MAX(id) FROM ' . $table)->fetchColumn();
        $del = $target->prepare('DELETE FROM sqlite_sequence WHERE name = ?');
        $del->execute([$table]);
        if ($max > 0) {
            $ins = $target->prepare('INSERT INTO sqlite_sequence (name, seq) VALUES (?, ?)');
            $ins->execute([$table, $max]);
        }
    }
}
