<?php

/**
 * ------------------------------------------------------------------------
 * Ticket Tracker
 *
 * Manifesto e registro de hooks para GLPI 10, 11 e 12.
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

define('PLUGIN_TICKETTRACKER_VERSION', '1.0.0');
define('PLUGIN_TICKETTRACKER_MIN_GLPI', '10.0.0');
define('PLUGIN_TICKETTRACKER_MAX_GLPI', '12.0.99');
define('PLUGIN_TICKETTRACKER_MIN_PHP', '7.4');

function plugin_init_tickettracker(): void
{
    global $PLUGIN_HOOKS;

    require_once __DIR__ . '/inc/compat.class.php';
    require_once __DIR__ . '/inc/config.class.php';
    require_once __DIR__ . '/inc/tracker.class.php';
    require_once __DIR__ . '/inc/tab.class.php';

    $PLUGIN_HOOKS['csrf_compliant']['tickettracker'] = true;
    $PLUGIN_HOOKS['config_page']['tickettracker']    = 'front/config.form.php';
    $PLUGIN_HOOKS['add_css']['tickettracker']        = [
        PluginTickettrackerCompat::assetPath('css/tickettracker.css'),
    ];

    Plugin::registerClass('PluginTickettrackerTab', ['addtabon' => ['Ticket']]);

    $PLUGIN_HOOKS['post_show_item']['tickettracker'] = [
        PluginTickettrackerTracker::class,
        'handleTicketView',
    ];
    $PLUGIN_HOOKS['post_item_form']['tickettracker'] = [
        PluginTickettrackerTracker::class,
        'handleTicketView',
    ];
    $PLUGIN_HOOKS['item_purge']['tickettracker'] = [
        'Ticket' => [PluginTickettrackerTracker::class, 'purgeTicketViews'],
    ];
}

function plugin_version_tickettracker(): array
{
    return [
        'name'         => 'Ticket Tracker',
        'version'      => PLUGIN_TICKETTRACKER_VERSION,
        'author'       => 'G. Martins',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/GustavoMS0/GLPI-TicketTracker',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_TICKETTRACKER_MIN_GLPI,
                'max' => PLUGIN_TICKETTRACKER_MAX_GLPI,
            ],
            'php' => [
                'min' => PLUGIN_TICKETTRACKER_MIN_PHP,
            ],
        ],
    ];
}

function plugin_tickettracker_check_prerequisites(): bool
{
    if (version_compare(PHP_VERSION, PLUGIN_TICKETTRACKER_MIN_PHP, '<')) {
        echo sprintf('Este plugin requer PHP %s ou superior.', PLUGIN_TICKETTRACKER_MIN_PHP);
        return false;
    }

    if (defined('GLPI_VERSION')) {
        if (version_compare(GLPI_VERSION, PLUGIN_TICKETTRACKER_MIN_GLPI, '<')) {
            echo sprintf('Este plugin requer GLPI %s ou superior.', PLUGIN_TICKETTRACKER_MIN_GLPI);
            return false;
        }
        if (version_compare(GLPI_VERSION, PLUGIN_TICKETTRACKER_MAX_GLPI, '>')) {
            echo sprintf('Este plugin suporta até GLPI %s.', PLUGIN_TICKETTRACKER_MAX_GLPI);
            return false;
        }
    }

    return true;
}

function plugin_tickettracker_check_config($verbose = false): bool
{
    return true;
}
