<?php

/**
 * ------------------------------------------------------------------------
 * Ticket Tracker
 *
 * Página de configuração do plugin (Configurar > Plugins > Ticket Tracker).
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
    include_once __DIR__ . '/../../../inc/includes.php';
}

require_once __DIR__ . '/../inc/compat.class.php';
require_once __DIR__ . '/../inc/config.class.php';

Session::checkRight('config', READ);

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST') {
    Session::checkRight('config', UPDATE);

    if (!PluginTickettrackerCompat::requireSafePost()) {
        PluginTickettrackerCompat::denyAccess(__('Origem da requisição não permitida.', 'tickettracker'));
        return;
    }

    if (isset($_POST['update'])) {
        PluginTickettrackerConfig::saveConfig([
            'cooldown_minutes'      => $_POST['cooldown_minutes'] ?? PluginTickettrackerConfig::DEFAULT_COOLDOWN,
            'ignore_self_requester' => empty($_POST['ignore_self_requester']) ? 0 : 1,
            'show_tab_selfservice'  => empty($_POST['show_tab_selfservice']) ? 0 : 1,
        ]);

        Session::addMessageAfterRedirect(__('Configuração salva.', 'tickettracker'));
        Html::back();
        return;
    }
}

$e = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$config    = PluginTickettrackerConfig::getConfig();
$canUpdate = PluginTickettrackerConfig::canUpdate();
$title     = __('Ticket Tracker', 'tickettracker');
$action    = PluginTickettrackerCompat::webPath() . '/front/config.form.php';
$disabled  = $canUpdate ? '' : ' disabled';

Html::header($title, '', 'config', 'plugin');

echo '<div class="card m-3 tickettracker-config-card">';
echo '<div class="card-header"><h3 class="card-title mb-0">'
    . '<i class="ti ti-eye me-2" aria-hidden="true"></i>'
    . $e($title)
    . '</h3></div>';
echo '<div class="card-body">';

if (!$canUpdate) {
    echo '<div class="alert alert-warning mb-3">'
        . $e(__('Você possui permissão apenas de leitura nas configurações.', 'tickettracker'))
        . '</div>';
}

echo '<form method="post" action="' . $e($action) . '">';

echo '<div class="mb-3">';
echo '<label class="form-label" for="tickettracker-cooldown">'
    . $e(__('Janela de cooldown (minutos)', 'tickettracker'))
    . '</label>';
echo '<input type="number" class="form-control tickettracker-cooldown-input" id="tickettracker-cooldown"'
    . ' name="cooldown_minutes" min="0" max="' . $e((string) PluginTickettrackerConfig::MAX_COOLDOWN) . '" step="1"'
    . ' value="' . $e((string) $config['cooldown_minutes']) . '"' . $disabled . '>';
echo '<div class="form-text">'
    . $e(__('Tempo mínimo em minutos entre visualizações do mesmo usuário para incrementar o contador (0 a 1440). Visualizações dentro da janela atualizam apenas a última visualização.', 'tickettracker'))
    . '</div>';
echo '</div>';

echo '<div class="form-check form-switch mb-3">';
echo '<input type="hidden" name="ignore_self_requester" value="0">';
echo '<input class="form-check-input" type="checkbox" role="switch" id="tickettracker-ignore-self"'
    . ' name="ignore_self_requester" value="1"'
    . ($config['ignore_self_requester'] ? ' checked' : '') . $disabled . '>';
echo '<label class="form-check-label" for="tickettracker-ignore-self">'
    . $e(__('Ignorar visualizações do próprio requerente', 'tickettracker'))
    . '</label>';
echo '<div class="form-text">'
    . $e(__('Quando ativado, acessos do criador ou requerente do chamado não são contabilizados, exceto se ele também estiver atribuído como técnico.', 'tickettracker'))
    . '</div>';
echo '</div>';

echo '<div class="form-check form-switch mb-4">';
echo '<input type="hidden" name="show_tab_selfservice" value="0">';
echo '<input class="form-check-input" type="checkbox" role="switch" id="tickettracker-show-selfservice"'
    . ' name="show_tab_selfservice" value="1"'
    . ($config['show_tab_selfservice'] ? ' checked' : '') . $disabled . '>';
echo '<label class="form-check-label" for="tickettracker-show-selfservice">'
    . $e(__('Exibir aba Visualizações no autoatendimento', 'tickettracker'))
    . '</label>';
echo '<div class="form-text">'
    . $e(__('Por padrão, a aba Visualizações aparece apenas na interface padrão (Central). Ative para exibi-la também na interface simplificada (Self-Service).', 'tickettracker'))
    . '</div>';
echo '</div>';

if ($canUpdate) {
    echo '<button type="submit" name="update" value="1" class="btn btn-primary">'
        . '<i class="ti ti-device-floppy me-1" aria-hidden="true"></i>'
        . '<span>' . $e(__('Salvar', 'tickettracker')) . '</span>'
        . '</button>';
}

Html::closeForm();

echo '</div></div>';

Html::footer();
