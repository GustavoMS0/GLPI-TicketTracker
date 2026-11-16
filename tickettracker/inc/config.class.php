<?php

/**
 * ------------------------------------------------------------------------
 * Ticket Tracker
 *
 * Configuração persistida em glpi_configs (contexto plugin:tickettracker).
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

interface PluginTickettrackerConfigConstants
{
    public const CONTEXT               = 'plugin:tickettracker';
    public const DEFAULT_COOLDOWN      = 5;
    public const MAX_COOLDOWN          = 1440;
    public const DEFAULT_IGNORE_SELF   = 0;
    public const DEFAULT_SHOW_SELFSERV = 0;
}

trait PluginTickettrackerConfigBody
{
    public static function getTypeName($nb = 0): string
    {
        return __('Ticket Tracker', 'tickettracker');
    }

    public static function canView(): bool
    {
        return (bool) Session::haveRight(self::$rightname, READ);
    }

    public static function canUpdate(): bool
    {
        return (bool) Session::haveRight(self::$rightname, UPDATE);
    }

    public static function getDefaults(): array
    {
        return [
            'cooldown_minutes'      => self::DEFAULT_COOLDOWN,
            'ignore_self_requester' => self::DEFAULT_IGNORE_SELF,
            'show_tab_selfservice'  => self::DEFAULT_SHOW_SELFSERV,
        ];
    }

    public static function sanitizeCooldown($value): int
    {
        if (!is_numeric($value)) {
            return self::DEFAULT_COOLDOWN;
        }
        $minutes = (int) $value;
        if ($minutes < 0) {
            return 0;
        }
        if ($minutes > self::MAX_COOLDOWN) {
            return self::MAX_COOLDOWN;
        }
        return $minutes;
    }

    public static function getConfig(): array
    {
        $raw      = Config::getConfigurationValues(self::CONTEXT);
        $defaults = self::getDefaults();

        $cooldown = isset($raw['cooldown_minutes'])
            ? self::sanitizeCooldown($raw['cooldown_minutes'])
            : $defaults['cooldown_minutes'];

        return [
            'cooldown_minutes'      => $cooldown,
            'ignore_self_requester' => !empty($raw['ignore_self_requester']) ? 1 : 0,
            'show_tab_selfservice'  => !empty($raw['show_tab_selfservice']) ? 1 : 0,
        ];
    }

    public static function saveConfig(array $input): array
    {
        $clean = [
            'cooldown_minutes'      => array_key_exists('cooldown_minutes', $input)
                ? self::sanitizeCooldown($input['cooldown_minutes'])
                : self::DEFAULT_COOLDOWN,
            'ignore_self_requester' => !empty($input['ignore_self_requester']) ? 1 : 0,
            'show_tab_selfservice'  => !empty($input['show_tab_selfservice']) ? 1 : 0,
        ];

        Config::setConfigurationValues(self::CONTEXT, $clean);
        return $clean;
    }

    public static function installDefaults(): void
    {
        $current = Config::getConfigurationValues(self::CONTEXT);
        $missing = array_diff_key(self::getDefaults(), $current);
        if ($missing !== []) {
            Config::setConfigurationValues(self::CONTEXT, $missing);
        }
    }

    public static function uninstallConfig(): void
    {
        $keys = array_keys(self::getDefaults());
        if (method_exists('Config', 'deleteConfigurationValues')) {
            Config::deleteConfigurationValues(self::CONTEXT, $keys);
        }
    }
}

if (!class_exists('PluginTickettrackerConfig', false)) {
    if (PluginTickettrackerCompat::typedRightname()) {
        class PluginTickettrackerConfig extends CommonGLPI implements PluginTickettrackerConfigConstants
        {
            use PluginTickettrackerConfigBody;

            public static string $rightname = 'config';
        }
    } else {
        class PluginTickettrackerConfig extends CommonGLPI implements PluginTickettrackerConfigConstants
        {
            use PluginTickettrackerConfigBody;

            public static $rightname = 'config';
        }
    }
}
