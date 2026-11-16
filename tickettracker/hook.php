<?php

/**
 * ------------------------------------------------------------------------
 * Ticket Tracker
 *
 * Instalação e desinstalação do plugin no GLPI.
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

function plugin_tickettracker_install(): bool
{
    require_once __DIR__ . '/inc/compat.class.php';
    require_once __DIR__ . '/inc/config.class.php';
    require_once __DIR__ . '/inc/tracker.class.php';

    PluginTickettrackerTracker::installSchema();
    PluginTickettrackerConfig::installDefaults();

    return true;
}

function plugin_tickettracker_uninstall(): bool
{
    require_once __DIR__ . '/inc/compat.class.php';
    require_once __DIR__ . '/inc/config.class.php';
    require_once __DIR__ . '/inc/tracker.class.php';

    PluginTickettrackerTracker::uninstallSchema();
    PluginTickettrackerConfig::uninstallConfig();

    return true;
}
