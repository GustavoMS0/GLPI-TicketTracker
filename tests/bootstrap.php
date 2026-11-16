<?php

/**
 * ------------------------------------------------------------------------
 * Ticket Tracker - Bootstrap de testes independentes (sem Composer)
 *
 * Simula GLPI 10 (untyped $rightname), GLPI 11 (untyped + prefixo moderno)
 * e GLPI 12 ($rightname tipado + AccessDeniedHttpException).
 *
 * Copyright (C) 2026 by G. Martins
 * ------------------------------------------------------------------------
 */

namespace Glpi\Toolbox {
    class Sanitizer
    {
        /**
         * @param mixed $value
         * @return mixed
         */
        public static function sanitize($value)
        {
            if (is_array($value)) {
                $out = [];
                foreach ($value as $k => $v) {
                    $out[$k] = self::sanitize($v);
                }
                return $out;
            }
            if (is_string($value)) {
                return addslashes(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
            }
            return $value;
        }

        /**
         * @param mixed $value
         * @return mixed
         */
        public static function unsanitize($value)
        {
            if (is_array($value)) {
                $out = [];
                foreach ($value as $k => $v) {
                    $out[$k] = self::unsanitize($v);
                }
                return $out;
            }
            if (is_string($value)) {
                return stripslashes(htmlspecialchars_decode($value, ENT_QUOTES));
            }
            return $value;
        }

        /**
         * @param mixed $value
         * @return mixed
         */
        public static function decodeHtmlSpecialChars($value)
        {
            if (is_array($value)) {
                $out = [];
                foreach ($value as $k => $v) {
                    $out[$k] = self::decodeHtmlSpecialChars($v);
                }
                return $out;
            }
            if (is_string($value)) {
                return htmlspecialchars_decode($value, ENT_QUOTES);
            }
            return $value;
        }
    }
}

namespace Glpi\Exception\Http {
    class AccessDeniedHttpException extends \RuntimeException
    {
    }
}

namespace {
    $typedMode = in_array('--typed-rightname', $argv ?? [], true)
        || getenv('TICKETTRACKER_TEST_TYPED') === '1';
    $glpi11Mode = in_array('--glpi11', $argv ?? [], true)
        || getenv('TICKETTRACKER_TEST_GLPI11') === '1';

    if (!defined('TEST_TYPED_RIGHTNAME')) {
        define('TEST_TYPED_RIGHTNAME', $typedMode);
    }
    if (!defined('GLPI_ROOT')) {
        define('GLPI_ROOT', dirname(__DIR__));
    }
    if (!defined('GLPI_VERSION')) {
        if (TEST_TYPED_RIGHTNAME) {
            define('GLPI_VERSION', '12.0.1');
        } elseif ($glpi11Mode) {
            define('GLPI_VERSION', '11.0.0');
        } else {
            define('GLPI_VERSION', '10.0.15');
        }
    }
    if (!defined('READ')) {
        define('READ', 1);
    }
    if (!defined('UPDATE')) {
        define('UPDATE', 2);
    }
    if (!defined('CREATE')) {
        define('CREATE', 4);
    }
    if (!defined('PURGE')) {
        define('PURGE', 128);
    }
    if (!defined('INFO')) {
        define('INFO', 0);
    }

    global $PLUGIN_HOOKS, $CFG_GLPI, $DB;
    $PLUGIN_HOOKS = [];
    $CFG_GLPI     = ['root_doc' => ''];

    class TestRuntime
    {
        public static bool $isCli = false;
        public static int $userId = 10;
        public static string $interface = 'central';
        public static bool $rightErrorCalled = false;
        /** @var array<string, int> */
        public static array $rights = [
            'config' => 3,
            'ticket' => 1,
            'user'   => 1,
        ];
        /** @var array<int, bool> */
        public static array $activeEntities = [0 => true];
        /** @var array<int, string> */
        public static array $messages = [];

        public static function reset(): void
        {
            self::$isCli            = false;
            self::$userId           = 10;
            self::$interface        = 'central';
            self::$rightErrorCalled = false;
            self::$rights           = ['config' => 3, 'ticket' => 1, 'user' => 1];
            self::$activeEntities   = [0 => true];
            self::$messages         = [];
        }
    }

    function isCommandLineInterface(): bool
    {
        return TestRuntime::$isCli;
    }

    function __($str, $domain = 'glpi'): string
    {
        return (string) $str;
    }

    if (TEST_TYPED_RIGHTNAME) {
        class CommonGLPI
        {
            public static string $rightname = '';

            public static function createTabEntry($text, $nb = 0): string
            {
                return (int) $nb > 0 ? ($text . ' (' . (int) $nb . ')') : (string) $text;
            }
        }
    } else {
        class CommonGLPI
        {
            public static $rightname = '';

            public static function createTabEntry($text, $nb = 0): string
            {
                return (int) $nb > 0 ? ($text . ' (' . (int) $nb . ')') : (string) $text;
            }
        }
    }

    class CommonDBTM extends CommonGLPI
    {
        /** @var array<string, mixed> */
        public $fields = [];

        public function getID(): int
        {
            return (int) ($this->fields['id'] ?? 0);
        }

        public function getType(): string
        {
            return static::class;
        }

        public function getEntityID(): int
        {
            return (int) ($this->fields['entities_id'] ?? 0);
        }

        public function canViewItem(): bool
        {
            return true;
        }
    }

    class Ticket extends CommonDBTM
    {
        public bool $canView = true;

        /**
         * @param array<string, mixed> $fields
         */
        public function __construct(int $id = 0, array $fields = [])
        {
            $this->fields = array_merge([
                'id'                 => $id,
                'entities_id'        => 0,
                'users_id_recipient' => 0,
            ], $fields);
        }

        public function getType(): string
        {
            return 'Ticket';
        }

        public function canViewItem(): bool
        {
            return $this->canView;
        }
    }

    class User extends CommonDBTM
    {
        public static bool $canView = true;

        public static function canView(): bool
        {
            return self::$canView;
        }

        public static function getFormURLWithID(int $id): string
        {
            global $CFG_GLPI;
            $root = isset($CFG_GLPI['root_doc']) ? rtrim((string) $CFG_GLPI['root_doc'], '/') : '';
            return $root . '/front/user.form.php?id=' . $id;
        }
    }

    class CommonITILActor
    {
        public const REQUESTER = 1;
        public const ASSIGN    = 2;
        public const OBSERVER  = 3;
    }

    class Session
    {
        /**
         * @return int|false
         */
        public static function getLoginUserID(bool $force_human = false)
        {
            return TestRuntime::$userId > 0 ? TestRuntime::$userId : false;
        }

        public static function getCurrentInterface(): string
        {
            return TestRuntime::$interface;
        }

        public static function haveRight(string $module, int $right): bool
        {
            return ((int) (TestRuntime::$rights[$module] ?? 0) & $right) === $right;
        }

        public static function checkRight(string $module, int $right): void
        {
            if (!self::haveRight($module, $right)) {
                throw new \RuntimeException("Missing right {$module}:{$right}");
            }
        }

        public static function haveAccessToEntity(int $entity_id): bool
        {
            return !empty(TestRuntime::$activeEntities[$entity_id]);
        }

        public static function addMessageAfterRedirect(string $msg): void
        {
            TestRuntime::$messages[] = $msg;
        }
    }

    class Config
    {
        /** @var array<string, array<string, mixed>> */
        public static array $store = [];

        public static function getConfigurationValues(string $context): array
        {
            return self::$store[$context] ?? [];
        }

        public static function setConfigurationValues(string $context, array $values): void
        {
            if (!isset(self::$store[$context])) {
                self::$store[$context] = [];
            }
            foreach ($values as $k => $v) {
                self::$store[$context][$k] = $v;
            }
        }

        public static function deleteConfigurationValues(string $context, array $keys): void
        {
            foreach ($keys as $k) {
                unset(self::$store[$context][$k]);
            }
        }
    }

    class Plugin
    {
        /** @var array<string, array<string, mixed>> */
        public static array $registered = [];

        public static function registerClass(string $class, array $options = []): void
        {
            self::$registered[$class] = $options;
        }

        public static function getWebDir(string $plugin, bool $full = true): string
        {
            throw new \RuntimeException('Deprecated Plugin::getWebDir() must not be called');
        }
    }

    class Html
    {
        public static function convDateTime(string $date): string
        {
            return $date;
        }

        public static function closeForm(): void
        {
            echo '</form>';
        }

        public static function displayRightError(): void
        {
            TestRuntime::$rightErrorCalled = true;
            throw new \RuntimeException('Html::displayRightError called');
        }

        public static function displayErrorAndDie(string $msg, bool $minimal = false): void
        {
            throw new \RuntimeException($msg);
        }

        public static function header(string $title = '', string $url = '', string $sector = '', string $item = ''): void
        {
        }

        public static function footer(): void
        {
        }

        public static function back(): void
        {
        }
    }

    class DBConnection
    {
        public static function getDefaultCharset(): string
        {
            return 'utf8mb4';
        }

        public static function getDefaultCollation(): string
        {
            return 'utf8mb4_unicode_ci';
        }
    }

    class Toolbox
    {
        /**
         * @param mixed $value
         * @return mixed
         */
        public static function addslashes_deep($value)
        {
            if (is_array($value)) {
                return array_map([self::class, 'addslashes_deep'], $value);
            }
            return is_string($value) ? addslashes($value) : $value;
        }
    }

    class MockGlpiIterator implements \IteratorAggregate
    {
        /** @var array<int, array<string, mixed>> */
        private array $rows;

        /**
         * @param array<int, array<string, mixed>> $rows
         */
        public function __construct(array $rows)
        {
            $this->rows = $rows;
        }

        public function getIterator(): \ArrayIterator
        {
            return new \ArrayIterator($this->rows);
        }

        public function count(): int
        {
            return count($this->rows);
        }

        /**
         * @return array<string, mixed>|null
         */
        public function current(): ?array
        {
            return $this->rows[0] ?? null;
        }
    }

    /**
     * Simula GLPI 10.0.0–10.0.10 onde DBmysql::doQuery() ainda não existia (apenas query()).
     */
    class LegacyQueryOnlyDB
    {
        /** @var array<string, array<int, array<string, mixed>>> */
        public array $tables = [];

        /** @var array<int, string> */
        public array $queries = [];

        public function tableExists(string $table): bool
        {
            return isset($this->tables[$table]);
        }

        public function query(string $sql): bool
        {
            $this->queries[] = $sql;
            if (preg_match('/^\s*CREATE\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
                $this->tables[$m[1]] = [];
                return true;
            }
            if (preg_match('/^\s*DROP\s+TABLE(?:\s+IF\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
                unset($this->tables[$m[1]]);
                return true;
            }
            return true;
        }
    }

    class MockGlpiDB
    {
        /** @var array<string, array<int, array<string, mixed>>> */
        public array $tables = [];

        /** @var array<string, int> */
        public array $autoInc = [];

        public int $lastAffected = 0;
        public bool $throwOnNextDuplicate = false;

        /** @var array<string, mixed>|null */
        public ?array $injectRaceRowOnNextInsert = null;

        public function __construct()
        {
            $this->reset();
        }

        public function reset(): void
        {
            $this->tables = [
                'glpi_users'         => [],
                'glpi_tickets_users' => [],
            ];
            $this->autoInc = [
                'glpi_users'         => 1,
                'glpi_tickets_users' => 1,
            ];
            $this->lastAffected              = 0;
            $this->throwOnNextDuplicate      = false;
            $this->injectRaceRowOnNextInsert = null;
        }

        public function tableExists(string $table): bool
        {
            return isset($this->tables[$table]);
        }

        public function doQuery(string $sql): bool
        {
            if (preg_match('/^\s*CREATE\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
                $name = $m[1];
                if (!isset($this->tables[$name])) {
                    $this->tables[$name]  = [];
                    $this->autoInc[$name] = 1;
                }
                return true;
            }
            if (preg_match('/^\s*DROP\s+TABLE(?:\s+IF\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
                $name = $m[1];
                unset($this->tables[$name], $this->autoInc[$name]);
                return true;
            }
            return true;
        }

        public function escape(string $value): string
        {
            return addslashes($value);
        }

        public function insert(string $table, array $params): bool
        {
            if (!isset($this->tables[$table])) {
                return false;
            }

            if ($table === 'glpi_plugin_tickettracker_views' && $this->injectRaceRowOnNextInsert !== null) {
                $raceRow                         = $this->normalizeRowForStorage($this->injectRaceRowOnNextInsert);
                $this->injectRaceRowOnNextInsert = null;
                if (!isset($raceRow['id'])) {
                    $nextId                 = $this->autoInc[$table] ?? 1;
                    $raceRow['id']          = $nextId;
                    $this->autoInc[$table]  = $nextId + 1;
                }
                $this->tables[$table][] = $raceRow;
                throw new \RuntimeException('Duplicate entry 1062 for key uniq_ticket_user');
            }

            $row = $this->normalizeRowForStorage($params);

            if ($table === 'glpi_plugin_tickettracker_views') {
                foreach ($this->tables[$table] as $existing) {
                    if (
                        (int) ($existing['tickets_id'] ?? 0) === (int) ($row['tickets_id'] ?? 0)
                        && (int) ($existing['users_id'] ?? 0) === (int) ($row['users_id'] ?? 0)
                    ) {
                        if ($this->throwOnNextDuplicate) {
                            $this->throwOnNextDuplicate = false;
                            throw new \RuntimeException('Duplicate entry for key uniq_ticket_user');
                        }
                        return false;
                    }
                }
            }

            if (!isset($row['id'])) {
                $nextId                 = $this->autoInc[$table] ?? 1;
                $row['id']              = $nextId;
                $this->autoInc[$table]  = $nextId + 1;
            }

            $this->tables[$table][] = $row;
            $this->lastAffected     = 1;
            return true;
        }

        public function update(string $table, array $params, array $where): bool
        {
            if (!isset($this->tables[$table])) {
                return false;
            }

            $updates = $this->normalizeRowForStorage($params);
            $count   = 0;

            foreach ($this->tables[$table] as $idx => $row) {
                if ($this->matchesWhere($row, $where)) {
                    foreach ($updates as $k => $v) {
                        $this->tables[$table][$idx][$k] = $v;
                    }
                    $count++;
                }
            }

            $this->lastAffected = $count;
            return true;
        }

        public function delete(string $table, array $where): bool
        {
            if (!isset($this->tables[$table])) {
                return false;
            }

            $remaining = [];
            $deleted   = 0;

            foreach ($this->tables[$table] as $row) {
                if ($this->matchesWhere($row, $where)) {
                    $deleted++;
                } else {
                    $remaining[] = $row;
                }
            }

            $this->tables[$table] = $remaining;
            $this->lastAffected   = $deleted;
            return true;
        }

        public function affectedRows(): int
        {
            return $this->lastAffected;
        }

        public function request(array $criteria): MockGlpiIterator
        {
            $fromSpec = (string) ($criteria['FROM'] ?? '');
            list($baseTable, $baseAlias) = $this->parseTableAlias($fromSpec);

            if (!isset($this->tables[$baseTable])) {
                return new MockGlpiIterator([]);
            }

            $combined = [];
            foreach ($this->tables[$baseTable] as $baseRow) {
                $entry = [];
                foreach ($baseRow as $col => $val) {
                    $entry[$col]                    = $val;
                    $entry[$baseAlias . '.' . $col] = $val;
                }
                $combined[] = $entry;
            }

            if (isset($criteria['LEFT JOIN']) && is_array($criteria['LEFT JOIN'])) {
                foreach ($criteria['LEFT JOIN'] as $joinSpec => $joinCond) {
                    list($joinTable, $joinAlias) = $this->parseTableAlias((string) $joinSpec);
                    $joinRows   = $this->tables[$joinTable] ?? [];
                    $on         = $joinCond['ON'] ?? [];
                    $onKeys     = array_keys($on);
                    $leftAlias  = $onKeys[0] ?? $baseAlias;
                    $leftCol    = $on[$leftAlias] ?? 'id';
                    $rightAlias = $onKeys[1] ?? $joinAlias;
                    $rightCol   = $on[$rightAlias] ?? 'id';

                    $newCombined = [];
                    foreach ($combined as $row) {
                        $leftVal = $row[$leftAlias . '.' . $leftCol] ?? ($row[$leftCol] ?? null);
                        $matched = false;
                        foreach ($joinRows as $jRow) {
                            if ((string) ($jRow[$rightCol] ?? '') === (string) $leftVal) {
                                $merged = $row;
                                foreach ($jRow as $col => $val) {
                                    $merged[$joinAlias . '.' . $col] = $val;
                                    if (!array_key_exists($col, $merged)) {
                                        $merged[$col] = $val;
                                    }
                                }
                                $newCombined[] = $merged;
                                $matched       = true;
                            }
                        }
                        if (!$matched) {
                            $newCombined[] = $row;
                        }
                    }
                    $combined = $newCombined;
                }
            }

            $where = $criteria['WHERE'] ?? [];
            if (is_array($where) && $where !== []) {
                $filtered = [];
                foreach ($combined as $row) {
                    if ($this->matchesWhere($row, $where)) {
                        $filtered[] = $row;
                    }
                }
                $combined = $filtered;
            }

            if (isset($criteria['COUNT'])) {
                $alias = (string) $criteria['COUNT'];
                return new MockGlpiIterator([[$alias => count($combined)]]);
            }

            if (isset($criteria['ORDER'])) {
                $orders = is_array($criteria['ORDER']) ? $criteria['ORDER'] : [$criteria['ORDER']];
                usort($combined, function (array $a, array $b) use ($orders): int {
                    foreach ($orders as $orderExpr) {
                        $parts = preg_split('/\s+/', trim((string) $orderExpr));
                        $col   = $parts[0] ?? 'id';
                        $dir   = strtoupper($parts[1] ?? 'ASC');
                        $valA  = $a[$col] ?? '';
                        $valB  = $b[$col] ?? '';
                        if (is_numeric($valA) && is_numeric($valB)) {
                            $cmp = ((float) $valA <=> (float) $valB);
                        } else {
                            $cmp = strcmp((string) $valA, (string) $valB);
                        }
                        if ($cmp !== 0) {
                            return $dir === 'DESC' ? -$cmp : $cmp;
                        }
                    }
                    return 0;
                });
            }

            if (isset($criteria['SELECT']) && is_array($criteria['SELECT'])) {
                $projected = [];
                foreach ($combined as $row) {
                    $proj = [];
                    foreach ($criteria['SELECT'] as $sel) {
                        $selStr = trim((string) $sel);
                        if (preg_match('/^(.+?)\s+AS\s+([a-zA-Z0-9_]+)$/i', $selStr, $m)) {
                            $src = trim($m[1]);
                            $dst = trim($m[2]);
                        } else {
                            $src = $selStr;
                            $dot = strrpos($selStr, '.');
                            $dst = $dot !== false ? substr($selStr, $dot + 1) : $selStr;
                        }
                        $proj[$dst] = $row[$src] ?? ($row[$dst] ?? null);
                    }
                    $projected[] = $proj;
                }
                $combined = $projected;
            }

            if (isset($criteria['LIMIT'])) {
                $combined = array_slice($combined, 0, max(0, (int) $criteria['LIMIT']));
            }

            return new MockGlpiIterator($combined);
        }

        /**
         * @return array{0: string, 1: string}
         */
        private function parseTableAlias(string $spec): array
        {
            if (preg_match('/^([a-zA-Z0-9_]+)\s+AS\s+([a-zA-Z0-9_]+)$/i', trim($spec), $m)) {
                return [$m[1], $m[2]];
            }
            $clean = trim($spec);
            return [$clean, $clean];
        }

        private function normalizeRowForStorage(array $values): array
        {
            if (version_compare(GLPI_VERSION, '11.0.0-dev', '<')) {
                foreach ($values as $k => $v) {
                    if (is_string($v)) {
                        $values[$k] = stripslashes($v);
                    }
                }
            }
            return $values;
        }

        private function matchesWhere(array $row, array $where): bool
        {
            foreach ($where as $k => $expected) {
                $actual = null;
                if (array_key_exists($k, $row)) {
                    $actual = $row[$k];
                } else {
                    $dot = strrpos((string) $k, '.');
                    if ($dot !== false) {
                        $short = substr((string) $k, $dot + 1);
                        if (array_key_exists($short, $row)) {
                            $actual = $row[$short];
                        }
                    }
                }

                if (is_array($expected)) {
                    $found = false;
                    foreach ($expected as $candidate) {
                        if ((string) $actual === (string) $candidate) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        return false;
                    }
                } else {
                    if ((string) $actual !== (string) $expected) {
                        return false;
                    }
                }
            }
            return true;
        }
    }

    $DB = new MockGlpiDB();

    require_once GLPI_ROOT . '/tickettracker/setup.php';
    require_once GLPI_ROOT . '/tickettracker/hook.php';
}
