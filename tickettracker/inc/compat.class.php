<?php

/**
 * ------------------------------------------------------------------------
 * Ticket Tracker
 *
 * Camada de compatibilidade entre GLPI 10, 11 e 12.
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

class PluginTickettrackerCompat
{
    public static function glpiVersion(): string
    {
        return defined('GLPI_VERSION') ? (string) GLPI_VERSION : '10.0.0';
    }

    public static function glpiMajor(): int
    {
        $parts = explode('.', self::glpiVersion());
        return max(0, (int) ($parts[0] ?? 10));
    }

    public static function isGlpi11OrHigher(): bool
    {
        return version_compare(self::glpiVersion(), '11.0.0-dev', '>=');
    }

    public static function typedRightname(): bool
    {
        if (!class_exists('CommonGLPI', true) || !property_exists('CommonGLPI', 'rightname')) {
            return false;
        }
        try {
            $ref = new ReflectionProperty('CommonGLPI', 'rightname');
            return $ref->hasType();
        } catch (ReflectionException $e) {
            return false;
        }
    }

    public static function webPath(string $pluginKey = 'tickettracker'): string
    {
        global $CFG_GLPI;

        $rootDoc = '';
        if (isset($CFG_GLPI) && is_array($CFG_GLPI) && isset($CFG_GLPI['root_doc'])) {
            $rootDoc = rtrim((string) $CFG_GLPI['root_doc'], '/');
        }

        $safeKey = rawurlencode(trim($pluginKey, '/'));
        $folder  = 'plugins';
        if (!self::isGlpi11OrHigher() && basename(dirname(__DIR__, 2)) === 'marketplace') {
            $folder = 'marketplace';
        }

        return $rootDoc . '/' . $folder . '/' . $safeKey;
    }

    public static function assetPath(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');
        $prefix       = self::isGlpi11OrHigher() ? '' : 'public/';
        return $prefix . $relativePath;
    }

    public static function fromCore(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (!self::isGlpi11OrHigher()) {
            if (
                class_exists('Glpi\\Toolbox\\Sanitizer')
                && method_exists('Glpi\\Toolbox\\Sanitizer', 'decodeHtmlSpecialChars')
            ) {
                return (string) \Glpi\Toolbox\Sanitizer::decodeHtmlSpecialChars($value);
            }
            return htmlspecialchars_decode($value, ENT_QUOTES);
        }
        return $value;
    }

    public static function dbValues(array $values): array
    {
        global $DB;

        if (!self::isGlpi11OrHigher() && isset($DB) && is_object($DB) && method_exists($DB, 'escape')) {
            foreach ($values as $k => $v) {
                if (is_string($v)) {
                    $values[$k] = $DB->escape($v);
                }
            }
        }
        return $values;
    }

    /**
     * @param mixed $values
     * @return mixed
     */
    public static function coreInput($values)
    {
        if (!self::isGlpi11OrHigher()) {
            if (class_exists('Glpi\\Toolbox\\Sanitizer')) {
                return \Glpi\Toolbox\Sanitizer::sanitize($values);
            }
            if (class_exists('Toolbox') && method_exists('Toolbox', 'addslashes_deep')) {
                return Toolbox::addslashes_deep($values);
            }
        }
        return $values;
    }

    /**
     * Executa DDL compatível com GLPI 10.0.0–10.0.10 (query) e GLPI 10.0.11+ (doQuery).
     *
     * @param object $db
     * @return mixed
     */
    public static function dbQuery($db, string $sql)
    {
        if (!is_object($db)) {
            return false;
        }
        if (method_exists($db, 'doQuery')) {
            return $db->doQuery($sql);
        }
        if (method_exists($db, 'query')) {
            return $db->query($sql);
        }
        return false;
    }

    public static function denyAccess(string $message = ''): void
    {
        if (self::glpiMajor() >= 12 && class_exists('Glpi\\Exception\\Http\\AccessDeniedHttpException')) {
            throw new \Glpi\Exception\Http\AccessDeniedHttpException($message);
        }
        if (class_exists('Html') && method_exists('Html', 'displayRightError')) {
            Html::displayRightError();
            return;
        }
        if (class_exists('Glpi\\Exception\\Http\\AccessDeniedHttpException')) {
            throw new \Glpi\Exception\Http\AccessDeniedHttpException($message);
        }
        if (!headers_sent()) {
            http_response_code(403);
        }
        echo htmlspecialchars($message !== '' ? $message : 'Access denied.', ENT_QUOTES, 'UTF-8');
        exit;
    }

    public static function requireSafePost(): bool
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            return false;
        }

        $fetchSite = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
        if ($fetchSite !== '') {
            return $fetchSite === 'same-origin';
        }

        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin === '' && isset($_SERVER['HTTP_REFERER'])) {
            $origin = trim((string) $_SERVER['HTTP_REFERER']);
        }
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

        if ($origin === '' || $host === '') {
            return false;
        }

        $originHost = parse_url($origin, PHP_URL_HOST);
        $originPort = parse_url($origin, PHP_URL_PORT);
        if (!is_string($originHost) || $originHost === '') {
            return false;
        }

        $expectedHost = $originHost . ($originPort !== null ? ':' . $originPort : '');
        return hash_equals(strtolower($host), strtolower($expectedHost));
    }
}
