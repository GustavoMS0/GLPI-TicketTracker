<?php

/**
 * ------------------------------------------------------------------------
 * Ticket Tracker
 *
 * Aba "Visualizações" exibida nos chamados do GLPI.
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
require_once __DIR__ . '/tracker.class.php';

interface PluginTickettrackerTabConstants
{
    public const TAB_NUM = 1;
}

trait PluginTickettrackerTabBody
{
    public static function getTypeName($nb = 0): string
    {
        return __('Visualizações', 'tickettracker');
    }

    public static function canView(): bool
    {
        return (bool) Session::haveRight(self::$rightname, READ);
    }

    public static function isAllowedInCurrentInterface(): bool
    {
        if (Session::getCurrentInterface() === 'central') {
            return true;
        }
        $cfg = PluginTickettrackerConfig::getConfig();
        return !empty($cfg['show_tab_selfservice']);
    }

    private static function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function canAccessTicket(CommonGLPI $item, $withtemplate = 0): bool
    {
        if ($withtemplate || !($item instanceof Ticket)) {
            return false;
        }
        $tickets_id = (int) $item->getID();
        if ($tickets_id <= 0) {
            return false;
        }
        if ((int) Session::getLoginUserID(true) <= 0) {
            return false;
        }
        if (!$item->canViewItem()) {
            return false;
        }
        if (
            method_exists($item, 'getEntityID')
            && method_exists('Session', 'haveAccessToEntity')
            && !Session::haveAccessToEntity((int) $item->getEntityID())
        ) {
            return false;
        }
        return self::isAllowedInCurrentInterface();
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!self::canAccessTicket($item, $withtemplate)) {
            return '';
        }

        $count = PluginTickettrackerTracker::countUniqueViewers((int) $item->getID());
        return self::createTabEntry(__('Visualizações', 'tickettracker'), $count);
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if (!self::canAccessTicket($item, $withtemplate)) {
            return false;
        }

        global $CFG_GLPI;

        $tickets_id  = (int) $item->getID();
        $views       = PluginTickettrackerTracker::getViewsForTicket($tickets_id);
        $canViewUser = class_exists('User') && method_exists('User', 'canView') && User::canView();
        $rootDoc     = isset($CFG_GLPI['root_doc']) ? rtrim((string) $CFG_GLPI['root_doc'], '/') : '';

        echo '<div class="tickettracker-tab">';
        echo '<div class="card border-0 shadow-none">';
        echo '<div class="card-header d-flex align-items-center justify-content-between">';
        echo '<h3 class="card-title mb-0">'
            . '<i class="ti ti-eye me-2" aria-hidden="true"></i>'
            . self::escapeHtml(__('Visualizações do chamado', 'tickettracker'))
            . '</h3>';
        echo '<span class="badge bg-blue-lt tickettracker-badge-total">'
            . self::escapeHtml((string) count($views))
            . '</span>';
        echo '</div>';

        if ($views === []) {
            echo '<div class="card-body">';
            echo '<div class="alert alert-info mb-0 tickettracker-empty">'
                . '<i class="ti ti-eye-off me-2" aria-hidden="true"></i>'
                . self::escapeHtml(__('Nenhuma visualização registrada até o momento.', 'tickettracker'))
                . '</div>';
            echo '</div>';
        } else {
            echo '<div class="table-responsive">';
            echo '<table class="table table-hover table-striped card-table mb-0 tickettracker-table">';
            echo '<thead><tr>';
            echo '<th scope="col">' . self::escapeHtml(__('Usuário', 'tickettracker')) . '</th>';
            echo '<th scope="col" class="text-center">' . self::escapeHtml(__('Total de acessos', 'tickettracker')) . '</th>';
            echo '<th scope="col">' . self::escapeHtml(__('Primeira visualização', 'tickettracker')) . '</th>';
            echo '<th scope="col">' . self::escapeHtml(__('Última visualização', 'tickettracker')) . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($views as $row) {
                $usersId   = (int) ($row['users_id'] ?? 0);
                $firstname = trim(PluginTickettrackerCompat::fromCore((string) ($row['firstname'] ?? '')));
                $realname  = trim(PluginTickettrackerCompat::fromCore((string) ($row['realname'] ?? '')));
                $login     = trim(PluginTickettrackerCompat::fromCore((string) ($row['name'] ?? '')));

                $fullName    = trim($firstname . ' ' . $realname);
                $displayName = $fullName !== ''
                    ? $fullName
                    : ($login !== '' ? $login : sprintf(__('Usuário #%d', 'tickettracker'), $usersId));

                $userHtml = self::escapeHtml($displayName);
                if ($usersId > 0 && $canViewUser) {
                    $userUrl = method_exists('User', 'getFormURLWithID')
                        ? (string) User::getFormURLWithID($usersId)
                        : ($rootDoc . '/front/user.form.php?id=' . $usersId);
                    $userHtml = '<a href="' . self::escapeHtml($userUrl) . '">' . $userHtml . '</a>';
                }
                if ($fullName !== '' && $login !== '') {
                    $userHtml .= ' <span class="text-muted small">(' . self::escapeHtml($login) . ')</span>';
                }

                $countText = (string) ((int) ($row['view_count'] ?? 0));
                $firstRaw  = (string) ($row['first_seen'] ?? '');
                $lastRaw   = (string) ($row['last_seen'] ?? '');

                $firstText = $firstRaw !== '' ? (string) Html::convDateTime($firstRaw) : '-';
                $lastText  = $lastRaw !== '' ? (string) Html::convDateTime($lastRaw) : '-';

                echo '<tr>';
                echo '<td>' . $userHtml . '</td>';
                echo '<td class="text-center"><span class="badge bg-secondary-lt tickettracker-count">'
                    . self::escapeHtml($countText)
                    . '</span></td>';
                echo '<td>' . self::escapeHtml($firstText) . '</td>';
                echo '<td>' . self::escapeHtml($lastText) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table></div>';
        }

        echo '</div></div>';
        return true;
    }
}

if (!class_exists('PluginTickettrackerTab', false)) {
    if (PluginTickettrackerCompat::typedRightname()) {
        class PluginTickettrackerTab extends CommonGLPI implements PluginTickettrackerTabConstants
        {
            use PluginTickettrackerTabBody;

            public static string $rightname = 'ticket';
        }
    } else {
        class PluginTickettrackerTab extends CommonGLPI implements PluginTickettrackerTabConstants
        {
            use PluginTickettrackerTabBody;

            public static $rightname = 'ticket';
        }
    }
}
