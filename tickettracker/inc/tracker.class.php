<?php

/**
 * ------------------------------------------------------------------------
 * Ticket Tracker
 *
 * Registro e consulta de visualizações de chamados por usuário.
 *
 * Copyright (C) 2026 by G. Martins
 * ------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Ticket Tracker.
 *
 * Ticket Tracker is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Ticket Tracker is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Ticket Tracker. If not, see <https://www.gnu.org/licenses/>.
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

require_once __DIR__ . '/compat.class.php';
require_once __DIR__ . '/config.class.php';

class PluginTickettrackerTracker
{
    public const TABLE = 'glpi_plugin_tickettracker_views';

    /** @var array<int, array<int, bool>> */
    private static array $recorded = [];

    private static ?string $nowOverride = null;

    public static function resetRuntimeState(): void
    {
        self::$recorded    = [];
        self::$nowOverride = null;
    }

    public static function setNowForTest(?string $now): void
    {
        self::$nowOverride = $now;
    }

    private static function currentTimestamp(): string
    {
        if (self::$nowOverride !== null && self::$nowOverride !== '') {
            return self::$nowOverride;
        }
        return date('Y-m-d H:i:s');
    }

    public static function installSchema(): void
    {
        global $DB;

        if (!isset($DB) || !is_object($DB) || $DB->tableExists(self::TABLE)) {
            return;
        }

        $charset   = class_exists('DBConnection') && method_exists('DBConnection', 'getDefaultCharset')
            ? DBConnection::getDefaultCharset()
            : 'utf8mb4';
        $collation = class_exists('DBConnection') && method_exists('DBConnection', 'getDefaultCollation')
            ? DBConnection::getDefaultCollation()
            : 'utf8mb4_unicode_ci';

        $sql = "CREATE TABLE `" . self::TABLE . "` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tickets_id` int unsigned NOT NULL DEFAULT '0',
            `users_id` int unsigned NOT NULL DEFAULT '0',
            `view_count` int unsigned NOT NULL DEFAULT '1',
            `first_seen` timestamp NULL DEFAULT NULL,
            `last_seen` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_ticket_user` (`tickets_id`, `users_id`),
            KEY `idx_tickets_last` (`tickets_id`, `last_seen`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC";

        PluginTickettrackerCompat::dbQuery($DB, $sql);
    }

    public static function uninstallSchema(): void
    {
        global $DB;

        if (!isset($DB) || !is_object($DB) || !$DB->tableExists(self::TABLE)) {
            return;
        }

        PluginTickettrackerCompat::dbQuery($DB, 'DROP TABLE IF EXISTS `' . self::TABLE . '`');
    }

    public static function handleTicketView(array $params): void
    {
        global $DB;

        if (function_exists('isCommandLineInterface') && isCommandLineInterface()) {
            return;
        }
        if (!empty($params['options']['withtemplate'])) {
            return;
        }

        $item = $params['item'] ?? null;
        if (!($item instanceof Ticket)) {
            return;
        }

        $tickets_id = (int) $item->getID();
        if ($tickets_id <= 0) {
            return;
        }

        $users_id = (int) Session::getLoginUserID(true);
        if ($users_id <= 0) {
            return;
        }

        if (!$item->canViewItem()) {
            return;
        }

        if (isset(self::$recorded[$tickets_id][$users_id])) {
            return;
        }
        self::$recorded[$tickets_id][$users_id] = true;

        if (!isset($DB) || !is_object($DB) || !$DB->tableExists(self::TABLE)) {
            return;
        }

        $config = PluginTickettrackerConfig::getConfig();
        if (!empty($config['ignore_self_requester']) && self::isSelfRequesterOnly($item, $users_id)) {
            return;
        }

        self::recordView($tickets_id, $users_id, (int) $config['cooldown_minutes']);
    }

    private static function isSelfRequesterOnly(Ticket $item, int $users_id): bool
    {
        global $DB;

        $isRecipient = ((int) ($item->fields['users_id_recipient'] ?? 0) === $users_id);
        $isRequester = false;
        $isAssigned  = false;

        $requesterType = class_exists('CommonITILActor') && defined('CommonITILActor::REQUESTER')
            ? (int) CommonITILActor::REQUESTER
            : 1;
        $assignType    = class_exists('CommonITILActor') && defined('CommonITILActor::ASSIGN')
            ? (int) CommonITILActor::ASSIGN
            : 2;

        $iterator = $DB->request([
            'SELECT' => ['type'],
            'FROM'   => 'glpi_tickets_users',
            'WHERE'  => [
                'tickets_id' => (int) $item->getID(),
                'users_id'   => $users_id,
            ],
        ]);

        foreach ($iterator as $row) {
            $type = (int) ($row['type'] ?? 0);
            if ($type === $requesterType) {
                $isRequester = true;
            } elseif ($type === $assignType) {
                $isAssigned = true;
            }
        }

        if ($isAssigned) {
            return false;
        }

        return $isRecipient || $isRequester;
    }

    private static function findView(int $tickets_id, int $users_id): ?array
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['id', 'tickets_id', 'users_id', 'view_count', 'first_seen', 'last_seen'],
            'FROM'   => self::TABLE,
            'WHERE'  => [
                'tickets_id' => $tickets_id,
                'users_id'   => $users_id,
            ],
            'LIMIT'  => 1,
        ]);

        foreach ($iterator as $row) {
            return $row;
        }

        return null;
    }

    private static function recordView(int $tickets_id, int $users_id, int $cooldownMinutes): void
    {
        global $DB;

        $now             = self::currentTimestamp();
        $nowTs           = strtotime($now);
        $cooldownSeconds = max(0, $cooldownMinutes) * 60;
        $existing        = self::findView($tickets_id, $users_id);

        if ($existing === null) {
            $inserted = false;
            try {
                $inserted = (bool) $DB->insert(
                    self::TABLE,
                    PluginTickettrackerCompat::dbValues([
                        'tickets_id' => $tickets_id,
                        'users_id'   => $users_id,
                        'view_count' => 1,
                        'first_seen' => $now,
                        'last_seen'  => $now,
                    ])
                );
            } catch (Throwable $e) {
                $inserted = false;
            }

            if ($inserted) {
                return;
            }

            $existing = self::findView($tickets_id, $users_id);
            if ($existing === null) {
                return;
            }
        }

        $lastSeenTs = strtotime((string) ($existing['last_seen'] ?? ''));
        $elapsed    = ($nowTs !== false && $lastSeenTs !== false)
            ? ($nowTs - $lastSeenTs)
            : $cooldownSeconds;

        $update = [
            'last_seen' => $now,
        ];
        if ($cooldownSeconds <= 0 || $elapsed >= $cooldownSeconds) {
            $update['view_count'] = ((int) ($existing['view_count'] ?? 0)) + 1;
        }

        $DB->update(
            self::TABLE,
            PluginTickettrackerCompat::dbValues($update),
            ['id' => (int) $existing['id']]
        );
    }

    public static function purgeTicketViews(CommonDBTM $item): void
    {
        global $DB;

        if (!($item instanceof Ticket) && $item->getType() !== 'Ticket') {
            return;
        }

        $tickets_id = (int) $item->getID();
        if ($tickets_id <= 0) {
            return;
        }

        unset(self::$recorded[$tickets_id]);

        if (!isset($DB) || !is_object($DB) || !$DB->tableExists(self::TABLE)) {
            return;
        }

        $DB->delete(self::TABLE, ['tickets_id' => $tickets_id]);
    }

    public static function countUniqueViewers(int $tickets_id): int
    {
        global $DB;

        if ($tickets_id <= 0 || !isset($DB) || !is_object($DB) || !$DB->tableExists(self::TABLE)) {
            return 0;
        }

        $iterator = $DB->request([
            'COUNT' => 'cpt',
            'FROM'  => self::TABLE,
            'WHERE' => [
                'tickets_id' => $tickets_id,
            ],
        ]);

        foreach ($iterator as $row) {
            return (int) ($row['cpt'] ?? 0);
        }

        return 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getViewsForTicket(int $tickets_id): array
    {
        global $DB;

        if ($tickets_id <= 0 || !isset($DB) || !is_object($DB) || !$DB->tableExists(self::TABLE)) {
            return [];
        }

        $rows     = [];
        $iterator = $DB->request([
            'SELECT' => [
                'v.id',
                'v.tickets_id',
                'v.users_id',
                'v.view_count',
                'v.first_seen',
                'v.last_seen',
                'u.name',
                'u.realname',
                'u.firstname',
            ],
            'FROM' => self::TABLE . ' AS v',
            'LEFT JOIN' => [
                'glpi_users AS u' => [
                    'ON' => [
                        'v' => 'users_id',
                        'u' => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                'v.tickets_id' => $tickets_id,
            ],
            'ORDER' => [
                'v.last_seen DESC',
                'v.id DESC',
            ],
        ]);

        foreach ($iterator as $row) {
            $rows[] = [
                'id'         => (int) ($row['id'] ?? 0),
                'tickets_id' => (int) ($row['tickets_id'] ?? 0),
                'users_id'   => (int) ($row['users_id'] ?? 0),
                'view_count' => (int) ($row['view_count'] ?? 0),
                'first_seen' => (string) ($row['first_seen'] ?? ''),
                'last_seen'  => (string) ($row['last_seen'] ?? ''),
                'name'       => (string) ($row['name'] ?? ''),
                'realname'   => (string) ($row['realname'] ?? ''),
                'firstname'  => (string) ($row['firstname'] ?? ''),
            ];
        }

        return $rows;
    }
}
