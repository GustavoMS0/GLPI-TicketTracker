<?php

/**
 * ------------------------------------------------------------------------
 * Ticket Tracker - Suíte de testes automatizados (PHP 7.4+)
 *
 * Executa todas as asserções simulando GLPI 10 (não tipado), GLPI 11
 * (não tipado com prefixo de assets moderno) e GLPI 12 ($rightname tipado).
 *
 * Copyright (C) 2026 by G. Martins
 * ------------------------------------------------------------------------
 */

require_once __DIR__ . '/bootstrap.php';

function assertSameValue($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $label = $message !== '' ? $message . ': ' : '';
        throw new RuntimeException(sprintf(
            '%sExpected %s, got %s',
            $label,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertTrueValue(bool $condition, string $message = ''): void
{
    if (!$condition) {
        throw new RuntimeException($message !== '' ? $message : 'Expected true, got false');
    }
}

function assertFalseValue(bool $condition, string $message = ''): void
{
    if ($condition) {
        throw new RuntimeException($message !== '' ? $message : 'Expected false, got true');
    }
}

function assertStringContains(string $needle, string $haystack, string $message = ''): void
{
    if (strpos($haystack, $needle) === false) {
        $label = $message !== '' ? $message . ': ' : '';
        throw new RuntimeException(sprintf('%sExpected string to contain %s', $label, var_export($needle, true)));
    }
}

function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
{
    if (strpos($haystack, $needle) !== false) {
        $label = $message !== '' ? $message . ': ' : '';
        throw new RuntimeException(sprintf('%sExpected string NOT to contain %s', $label, var_export($needle, true)));
    }
}

function resetFixture(): void
{
    global $DB, $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS       = [];
    $CFG_GLPI           = ['root_doc' => ''];
    Config::$store      = [];
    Plugin::$registered = [];
    User::$canView      = true;

    TestRuntime::reset();
    $DB->reset();

    plugin_init_tickettracker();
    plugin_tickettracker_install();
    PluginTickettrackerTracker::resetRuntimeState();
}

function seedUser(int $id, string $login, string $firstname, string $realname): void
{
    global $DB;

    $DB->insert('glpi_users', [
        'id'        => $id,
        'name'      => $login,
        'firstname' => $firstname,
        'realname'  => $realname,
    ]);
}

function test_plugin_manifest_and_hooks(): void
{
    global $PLUGIN_HOOKS;

    resetFixture();

    $info = plugin_version_tickettracker();
    assertSameValue('1.0.0', $info['version'], 'Version mismatch');
    assertSameValue('G. Martins', $info['author'], 'Author mismatch');
    assertSameValue('GPLv3+', $info['license'], 'License mismatch');
    assertSameValue('10.0.0', $info['requirements']['glpi']['min'], 'Min GLPI mismatch');
    assertSameValue('12.0.99', $info['requirements']['glpi']['max'], 'Max GLPI mismatch');
    assertSameValue('7.4', $info['requirements']['php']['min'], 'Min PHP mismatch');
    assertTrueValue(plugin_tickettracker_check_prerequisites(), 'Prerequisites should pass');
    assertTrueValue(plugin_tickettracker_check_config(), 'Check config should pass');

    assertSameValue(true, $PLUGIN_HOOKS['csrf_compliant']['tickettracker'] ?? null, 'CSRF compliant flag');
    assertSameValue('front/config.form.php', $PLUGIN_HOOKS['config_page']['tickettracker'] ?? null, 'Config page');

    $isModern    = version_compare(GLPI_VERSION, '11.0.0-dev', '>=');
    $expectedCss = $isModern ? ['css/tickettracker.css'] : ['public/css/tickettracker.css'];
    assertSameValue($expectedCss, $PLUGIN_HOOKS['add_css']['tickettracker'] ?? null, 'Conditional CSS prefix');

    assertSameValue(['addtabon' => ['Ticket']], Plugin::$registered['PluginTickettrackerTab'] ?? null, 'Tab registration');

    $expectedViewHook = [PluginTickettrackerTracker::class, 'handleTicketView'];
    assertSameValue($expectedViewHook, $PLUGIN_HOOKS['post_show_item']['tickettracker'] ?? null, 'post_show_item hook');
    assertSameValue($expectedViewHook, $PLUGIN_HOOKS['post_item_form']['tickettracker'] ?? null, 'post_item_form hook');
    assertSameValue(
        ['Ticket' => [PluginTickettrackerTracker::class, 'purgeTicketViews']],
        $PLUGIN_HOOKS['item_purge']['tickettracker'] ?? null,
        'item_purge hook'
    );
}

function test_rightname_typing_matches_glpi_core(): void
{
    resetFixture();

    $expectedTyped = TEST_TYPED_RIGHTNAME;
    assertSameValue($expectedTyped, PluginTickettrackerCompat::typedRightname(), 'typedRightname detection');

    $refConfig = new ReflectionProperty(PluginTickettrackerConfig::class, 'rightname');
    $refTab    = new ReflectionProperty(PluginTickettrackerTab::class, 'rightname');

    assertSameValue($expectedTyped, $refConfig->hasType(), 'Config::$rightname type presence');
    assertSameValue($expectedTyped, $refTab->hasType(), 'Tab::$rightname type presence');
    assertSameValue('config', PluginTickettrackerConfig::$rightname, 'Config rightname value');
    assertSameValue('ticket', PluginTickettrackerTab::$rightname, 'Tab rightname value');
}

function test_compat_helpers_and_sanitization(): void
{
    global $CFG_GLPI;

    resetFixture();

    $isModern      = version_compare(GLPI_VERSION, '11.0.0-dev', '>=');
    $expectedMajor = TEST_TYPED_RIGHTNAME ? 12 : ($isModern ? 11 : 10);
    assertSameValue($expectedMajor, PluginTickettrackerCompat::glpiMajor(), 'glpiMajor');

    // webPath não deve chamar Plugin::getWebDir() (stub lança exceção se chamado)
    assertSameValue('/plugins/tickettracker', PluginTickettrackerCompat::webPath(), 'webPath default');
    $CFG_GLPI['root_doc'] = '/glpi';
    assertSameValue('/glpi/plugins/tickettracker', PluginTickettrackerCompat::webPath(), 'webPath with root_doc');
    $CFG_GLPI['root_doc'] = '';

    $expectedAsset = $isModern ? 'css/tickettracker.css' : 'public/css/tickettracker.css';
    assertSameValue($expectedAsset, PluginTickettrackerCompat::assetPath('css/tickettracker.css'), 'assetPath');

    if ($isModern) {
        assertSameValue("DOMAIN\\user & <Admin>", PluginTickettrackerCompat::fromCore("DOMAIN\\user & <Admin>"), 'GLPI 11+ fromCore');
        assertSameValue("O'Brien", PluginTickettrackerCompat::coreInput("O'Brien"), 'GLPI 11+ coreInput');
        $dbVals = PluginTickettrackerCompat::dbValues(['name' => "O'Brien"]);
        assertSameValue("O'Brien", $dbVals['name'], 'GLPI 11+ dbValues');
    } else {
        // GLPI 10: decodifica entidades HTML sem remover barras invertidas literais
        assertSameValue(
            "DOMAIN\\user & O'Brien <Admin>",
            PluginTickettrackerCompat::fromCore("DOMAIN\\user &amp; O&#039;Brien &lt;Admin&gt;"),
            'GLPI 10 fromCore must preserve literal backslash'
        );
        $sanitized = PluginTickettrackerCompat::coreInput("O'Brien & <Admin>");
        assertStringContains('&amp;', $sanitized, 'GLPI 10 coreInput');
        $dbVals = PluginTickettrackerCompat::dbValues(['name' => "O'Brien"]);
        assertSameValue("O\\'Brien", $dbVals['name'], 'GLPI 10 dbValues');
    }

    $origServer = $_SERVER;
    try {
        $_SERVER = ['REQUEST_METHOD' => 'GET'];
        assertFalseValue(PluginTickettrackerCompat::requireSafePost(), 'GET must fail requireSafePost');

        $_SERVER = ['REQUEST_METHOD' => 'POST', 'HTTP_SEC_FETCH_SITE' => 'same-origin'];
        assertTrueValue(PluginTickettrackerCompat::requireSafePost(), 'Sec-Fetch-Site same-origin must pass');

        $_SERVER = ['REQUEST_METHOD' => 'POST', 'HTTP_SEC_FETCH_SITE' => 'cross-site'];
        assertFalseValue(PluginTickettrackerCompat::requireSafePost(), 'Sec-Fetch-Site cross-site must fail');

        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST'      => 'glpi.example.local:8443',
            'HTTP_ORIGIN'    => 'https://glpi.example.local:8443',
        ];
        assertTrueValue(PluginTickettrackerCompat::requireSafePost(), 'Matching Origin/Host must pass');

        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST'      => 'glpi.example.local',
            'HTTP_ORIGIN'    => 'https://evil.example.org',
        ];
        assertFalseValue(PluginTickettrackerCompat::requireSafePost(), 'Mismatched Origin must fail');
    } finally {
        $_SERVER = $origServer;
    }
}

function test_dbquery_falls_back_to_query_on_glpi_10_0_5(): void
{
    global $DB;

    resetFixture();
    $origDb = $DB;

    try {
        $legacyDb = new LegacyQueryOnlyDB();
        assertFalseValue(method_exists($legacyDb, 'doQuery'), 'LegacyQueryOnlyDB must not define doQuery()');

        $DB = $legacyDb;
        PluginTickettrackerTracker::installSchema();

        assertTrueValue($legacyDb->tableExists(PluginTickettrackerTracker::TABLE), 'installSchema must create table via query() fallback');
        assertSameValue(1, count($legacyDb->queries), 'Expected 1 DDL query executed on install');

        PluginTickettrackerTracker::uninstallSchema();
        assertFalseValue($legacyDb->tableExists(PluginTickettrackerTracker::TABLE), 'uninstallSchema must drop table via query() fallback');
        assertSameValue(2, count($legacyDb->queries), 'Expected 2nd DDL query executed on uninstall');
    } finally {
        $DB = $origDb;
    }
}

function test_deny_access_behavior(): void
{
    resetFixture();

    if (PluginTickettrackerCompat::glpiMajor() >= 12) {
        $threwAccessDenied = false;
        try {
            PluginTickettrackerCompat::denyAccess('Forbidden on GLPI 12');
        } catch (\Glpi\Exception\Http\AccessDeniedHttpException $e) {
            $threwAccessDenied = ($e->getMessage() === 'Forbidden on GLPI 12');
        }
        assertTrueValue($threwAccessDenied, 'GLPI 12+ must throw AccessDeniedHttpException');
        assertFalseValue(TestRuntime::$rightErrorCalled, 'GLPI 12+ must not call Html::displayRightError()');
    } else {
        $threwRightError = false;
        try {
            PluginTickettrackerCompat::denyAccess('Forbidden on GLPI 10/11');
        } catch (RuntimeException $e) {
            $threwRightError = TestRuntime::$rightErrorCalled;
        }
        assertTrueValue($threwRightError, 'GLPI 10/11 must invoke Html::displayRightError()');
    }
}

function test_config_defaults_and_clamping(): void
{
    resetFixture();

    $cfg = PluginTickettrackerConfig::getConfig();
    assertSameValue(5, $cfg['cooldown_minutes'], 'Default cooldown');
    assertSameValue(0, $cfg['ignore_self_requester'], 'Default ignore_self_requester');
    assertSameValue(0, $cfg['show_tab_selfservice'], 'Default show_tab_selfservice');

    $saved = PluginTickettrackerConfig::saveConfig([
        'cooldown_minutes'      => -15,
        'ignore_self_requester' => '1',
        'show_tab_selfservice'  => 0,
    ]);
    assertSameValue(0, $saved['cooldown_minutes'], 'Negative cooldown clamped to 0');
    assertSameValue(1, $saved['ignore_self_requester'], 'ignore_self_requester saved as 1');
    assertSameValue(0, $saved['show_tab_selfservice'], 'show_tab_selfservice saved as 0');

    $savedHigh = PluginTickettrackerConfig::saveConfig([
        'cooldown_minutes'      => 9999,
        'ignore_self_requester' => 0,
        'show_tab_selfservice'  => 1,
    ]);
    assertSameValue(1440, $savedHigh['cooldown_minutes'], 'High cooldown clamped to 1440');
    assertSameValue(1, $savedHigh['show_tab_selfservice'], 'show_tab_selfservice saved as 1');

    $savedInvalid = PluginTickettrackerConfig::saveConfig([
        'cooldown_minutes' => 'not-a-number',
    ]);
    assertSameValue(5, $savedInvalid['cooldown_minutes'], 'Invalid cooldown falls back to default 5');
}

function test_records_first_view_and_deduplicates_within_same_request(): void
{
    resetFixture();

    seedUser(10, 'tech1', 'Carlos', 'Silva');
    TestRuntime::$userId = 10;
    PluginTickettrackerTracker::setNowForTest('2026-11-16 09:00:00');

    $ticket = new Ticket(501);

    // Dispara ambos os hooks na mesma requisição HTTP
    PluginTickettrackerTracker::handleTicketView(['item' => $ticket]);
    PluginTickettrackerTracker::handleTicketView(['item' => $ticket]);

    $views = PluginTickettrackerTracker::getViewsForTicket(501);
    assertSameValue(1, count($views), 'Expected single viewer row');
    assertSameValue(1, $views[0]['view_count'], 'Expected view_count=1 after both hooks in same request');
    assertSameValue('2026-11-16 09:00:00', $views[0]['first_seen'], 'first_seen timestamp');
    assertSameValue('2026-11-16 09:00:00', $views[0]['last_seen'], 'last_seen timestamp');

    // Casos que não devem gravar
    PluginTickettrackerTracker::resetRuntimeState();
    $ticketOther = new Ticket(502);

    TestRuntime::$isCli = true;
    PluginTickettrackerTracker::handleTicketView(['item' => $ticketOther]);
    assertSameValue(0, PluginTickettrackerTracker::countUniqueViewers(502), 'CLI request must be skipped');
    TestRuntime::$isCli = false;

    $ticketHidden          = new Ticket(503);
    $ticketHidden->canView = false;
    PluginTickettrackerTracker::handleTicketView(['item' => $ticketHidden]);
    assertSameValue(0, PluginTickettrackerTracker::countUniqueViewers(503), 'Unauthorized ticket must be skipped');

    PluginTickettrackerTracker::handleTicketView(['item' => new Ticket(504), 'options' => ['withtemplate' => 1]]);
    assertSameValue(0, PluginTickettrackerTracker::countUniqueViewers(504), 'Template view must be skipped');

    TestRuntime::$userId = 0;
    PluginTickettrackerTracker::handleTicketView(['item' => new Ticket(505)]);
    assertSameValue(0, PluginTickettrackerTracker::countUniqueViewers(505), 'Anonymous user must be skipped');
}

function test_cooldown_prevents_increment_within_window_and_increments_after_expiry(): void
{
    resetFixture();

    PluginTickettrackerConfig::saveConfig(['cooldown_minutes' => 5]);
    seedUser(20, 'analyst', 'Ana', 'Costa');
    TestRuntime::$userId = 20;

    $ticket = new Ticket(601);

    // 1a visualização às 10:00:00
    PluginTickettrackerTracker::setNowForTest('2026-11-16 10:00:00');
    PluginTickettrackerTracker::handleTicketView(['item' => $ticket]);

    $views = PluginTickettrackerTracker::getViewsForTicket(601);
    assertSameValue(1, $views[0]['view_count'], 'Initial view_count must be 1');

    // Nova requisição após 3 minutos (dentro da janela de 5 min): atualiza last_seen, mantém view_count = 1
    PluginTickettrackerTracker::resetRuntimeState();
    PluginTickettrackerTracker::setNowForTest('2026-11-16 10:03:00');
    PluginTickettrackerTracker::handleTicketView(['item' => $ticket]);

    $views = PluginTickettrackerTracker::getViewsForTicket(601);
    assertSameValue(1, $views[0]['view_count'], 'Cooldown window must prevent increment');
    assertSameValue('2026-11-16 10:00:00', $views[0]['first_seen'], 'first_seen must remain unchanged');
    assertSameValue('2026-11-16 10:03:00', $views[0]['last_seen'], 'last_seen must update inside cooldown');

    // Nova requisição após 5 minutos exatos desde 10:03:00 -> 10:08:00: incrementa para 2
    PluginTickettrackerTracker::resetRuntimeState();
    PluginTickettrackerTracker::setNowForTest('2026-11-16 10:08:00');
    PluginTickettrackerTracker::handleTicketView(['item' => $ticket]);

    $views = PluginTickettrackerTracker::getViewsForTicket(601);
    assertSameValue(2, $views[0]['view_count'], 'After cooldown expiry view_count must increment to 2');
    assertSameValue('2026-11-16 10:00:00', $views[0]['first_seen'], 'first_seen must still remain unchanged');
    assertSameValue('2026-11-16 10:08:00', $views[0]['last_seen'], 'last_seen must update after expiry');

    // Cooldown = 0 deve incrementar em toda nova requisição
    PluginTickettrackerConfig::saveConfig(['cooldown_minutes' => 0]);
    PluginTickettrackerTracker::resetRuntimeState();
    PluginTickettrackerTracker::setNowForTest('2026-11-16 10:08:05');
    PluginTickettrackerTracker::handleTicketView(['item' => $ticket]);

    $views = PluginTickettrackerTracker::getViewsForTicket(601);
    assertSameValue(3, $views[0]['view_count'], 'Cooldown 0 must increment on every new request');
}

function test_race_condition_duplicate_insert_falls_back_to_update(): void
{
    global $DB;

    resetFixture();
    PluginTickettrackerConfig::saveConfig(['cooldown_minutes' => 0]);
    seedUser(10, 'tech10', 'Bruno', 'Ferreira');

    // Tabela começa vazia para (650, 10), então findView() retorna null.
    // Quando recordView() tentar $DB->insert(), o mock insere uma linha concorrente
    // e lança RuntimeException (1062), acionando o catch(\Throwable) -> rebusca -> update().
    $DB->injectRaceRowOnNextInsert = [
        'tickets_id' => 650,
        'users_id'   => 10,
        'view_count' => 1,
        'first_seen' => '2026-11-16 10:00:00',
        'last_seen'  => '2026-11-16 10:00:00',
    ];

    TestRuntime::$userId = 10;
    PluginTickettrackerTracker::setNowForTest('2026-11-16 10:02:00');
    PluginTickettrackerTracker::handleTicketView(['item' => new Ticket(650)]);

    assertSameValue(null, $DB->injectRaceRowOnNextInsert, 'Race injection must have been consumed by recordView()->insert()');
    $views = PluginTickettrackerTracker::getViewsForTicket(650);
    assertSameValue(1, count($views), 'Expected single deduplicated row after race fallback');
    assertSameValue(2, $views[0]['view_count'], 'Race fallback must update view_count cleanly when cooldown=0');
    assertSameValue('2026-11-16 10:00:00', $views[0]['first_seen'], 'first_seen from concurrent insert preserved');
    assertSameValue('2026-11-16 10:02:00', $views[0]['last_seen'], 'last_seen updated by fallback');
}

function test_ignore_self_requester_skips_ticket_creator(): void
{
    global $DB;

    resetFixture();
    PluginTickettrackerConfig::saveConfig([
        'cooldown_minutes'      => 5,
        'ignore_self_requester' => 1,
    ]);

    seedUser(30, 'creator', 'Joao', 'Pereira');
    seedUser(31, 'requester2', 'Maria', 'Souza');
    seedUser(32, 'req_tech', 'Lucas', 'Mendes');
    seedUser(33, 'tech_other', 'Bruna', 'Lima');

    $ticket = new Ticket(701, ['users_id_recipient' => 30]);

    $DB->insert('glpi_tickets_users', ['tickets_id' => 701, 'users_id' => 31, 'type' => CommonITILActor::REQUESTER]);
    $DB->insert('glpi_tickets_users', ['tickets_id' => 701, 'users_id' => 32, 'type' => CommonITILActor::REQUESTER]);
    $DB->insert('glpi_tickets_users', ['tickets_id' => 701, 'users_id' => 32, 'type' => CommonITILActor::ASSIGN]);

    // 1. Criador do chamado (users_id_recipient) deve ser ignorado
    TestRuntime::$userId = 30;
    PluginTickettrackerTracker::handleTicketView(['item' => $ticket]);
    assertSameValue(0, PluginTickettrackerTracker::countUniqueViewers(701), 'Creator must be skipped when ignore_self_requester=1');

    // 2. Requerente sem atribuição técnica deve ser ignorado
    TestRuntime::$userId = 31;
    PluginTickettrackerTracker::handleTicketView(['item' => $ticket]);
    assertSameValue(0, PluginTickettrackerTracker::countUniqueViewers(701), 'Requester-only must be skipped');

    // 3. Requerente que também está atribuído como técnico deve ser contabilizado
    TestRuntime::$userId = 32;
    PluginTickettrackerTracker::handleTicketView(['item' => $ticket]);
    assertSameValue(1, PluginTickettrackerTracker::countUniqueViewers(701), 'Requester + Assign technician must be recorded');

    // 4. Outro técnico deve ser contabilizado
    TestRuntime::$userId = 33;
    PluginTickettrackerTracker::handleTicketView(['item' => $ticket]);
    assertSameValue(2, PluginTickettrackerTracker::countUniqueViewers(701), 'Other viewer must be recorded');

    // 5. Desligando ignore_self_requester, o criador passa a ser contabilizado
    PluginTickettrackerConfig::saveConfig(['ignore_self_requester' => 0]);
    PluginTickettrackerTracker::resetRuntimeState();
    TestRuntime::$userId = 30;
    PluginTickettrackerTracker::handleTicketView(['item' => $ticket]);
    assertSameValue(3, PluginTickettrackerTracker::countUniqueViewers(701), 'Creator must be recorded when ignore_self_requester=0');
}

function test_selfservice_interface_hides_tab_when_show_tab_selfservice_disabled(): void
{
    resetFixture();

    seedUser(40, 'viewer1', 'Paula', 'Nunes');
    seedUser(41, 'viewer2', 'Rafael', 'Dias');

    $ticket = new Ticket(801);

    TestRuntime::$interface = 'central';
    TestRuntime::$userId    = 40;
    PluginTickettrackerTracker::setNowForTest('2026-11-16 11:00:00');
    PluginTickettrackerTracker::handleTicketView(['item' => $ticket]);

    TestRuntime::$userId = 41;
    PluginTickettrackerTracker::setNowForTest('2026-11-16 11:05:00');
    PluginTickettrackerTracker::handleTicketView(['item' => $ticket]);

    $tab = new PluginTickettrackerTab();

    // Autoatendimento com opção desativada: oculta título e conteúdo
    TestRuntime::$interface = 'helpdesk';
    PluginTickettrackerConfig::saveConfig(['show_tab_selfservice' => 0]);

    assertSameValue('', $tab->getTabNameForItem($ticket), 'Tab title must be hidden on selfservice when disabled');

    ob_start();
    $shownDisabled = PluginTickettrackerTab::displayTabContentForItem($ticket);
    $htmlDisabled  = (string) ob_get_clean();
    assertFalseValue($shownDisabled, 'Tab content must return false on selfservice when disabled');
    assertSameValue('', $htmlDisabled, 'No HTML must be emitted when selfservice tab is disabled');

    // Autoatendimento com opção ativada: exibe título com contador e tabela
    PluginTickettrackerConfig::saveConfig(['show_tab_selfservice' => 1]);
    assertSameValue('Visualizações (2)', $tab->getTabNameForItem($ticket), 'Tab title with unique viewer count');

    ob_start();
    $shownEnabled = PluginTickettrackerTab::displayTabContentForItem($ticket);
    $htmlEnabled  = (string) ob_get_clean();
    assertTrueValue($shownEnabled, 'Tab content must render when selfservice tab is enabled');
    assertStringContains('Rafael Dias', $htmlEnabled, 'Viewer 2 rendered');
    assertStringContains('Paula Nunes', $htmlEnabled, 'Viewer 1 rendered');

    // Ordenação DESC: Rafael Dias (11:05) aparece antes de Paula Nunes (11:00)
    assertTrueValue(
        strpos($htmlEnabled, 'Rafael Dias') < strpos($htmlEnabled, 'Paula Nunes'),
        'Rows must be ordered by last_seen DESC'
    );
}

function test_tab_rendering_and_xss_escaping(): void
{
    resetFixture();

    $isModern = version_compare(GLPI_VERSION, '11.0.0-dev', '>=');
    $rawName  = $isModern ? '<script>alert(1)</script>' : '&lt;script&gt;alert(1)&lt;/script&gt;';
    $rawLast  = $isModern ? "O'Brien & Sons" : 'O&#039;Brien &amp; Sons';
    seedUser(50, 'xssuser', $rawName, $rawLast);

    TestRuntime::$userId = 50;
    $ticket              = new Ticket(850);
    PluginTickettrackerTracker::setNowForTest('2026-11-16 12:00:00');
    PluginTickettrackerTracker::handleTicketView(['item' => $ticket]);

    User::$canView = true;
    ob_start();
    PluginTickettrackerTab::displayTabContentForItem($ticket);
    $htmlWithLink = (string) ob_get_clean();

    assertStringNotContains('<script>', $htmlWithLink, 'Raw script tag must never appear in HTML');
    assertStringNotContains('&amp;lt;script&amp;gt;', $htmlWithLink, 'Must not double-escape GLPI 10 encoded entities');
    assertStringContains('&lt;script&gt;alert(1)&lt;/script&gt;', $htmlWithLink, 'Escaped text must appear once');
    assertStringContains('O&#039;Brien &amp; Sons', $htmlWithLink, 'Escaped apostrophe and ampersand must appear once');
    assertStringContains('/front/user.form.php?id=50', $htmlWithLink, 'User link rendered when User::canView() is true');

    User::$canView = false;
    ob_start();
    PluginTickettrackerTab::displayTabContentForItem($ticket);
    $htmlNoLink = (string) ob_get_clean();

    assertStringNotContains('user.form.php', $htmlNoLink, 'User link must be omitted when User::canView() is false');
}

function test_item_purge_cleans_up_views(): void
{
    resetFixture();

    seedUser(60, 'u60', 'Carlos', 'Alves');
    TestRuntime::$userId = 60;

    $ticketA = new Ticket(901);
    $ticketB = new Ticket(902);

    PluginTickettrackerTracker::handleTicketView(['item' => $ticketA]);
    PluginTickettrackerTracker::handleTicketView(['item' => $ticketB]);

    assertSameValue(1, PluginTickettrackerTracker::countUniqueViewers(901), 'Ticket 901 must have 1 viewer before purge');
    assertSameValue(1, PluginTickettrackerTracker::countUniqueViewers(902), 'Ticket 902 must have 1 viewer before purge');

    PluginTickettrackerTracker::purgeTicketViews($ticketA);

    assertSameValue(0, PluginTickettrackerTracker::countUniqueViewers(901), 'Ticket 901 views must be purged');
    assertSameValue(1, PluginTickettrackerTracker::countUniqueViewers(902), 'Ticket 902 views must remain intact');
}

function test_config_form_get_and_post(): void
{
    resetFixture();

    $origServer = $_SERVER;
    $origPost   = $_POST;

    try {
        $_SERVER = ['REQUEST_METHOD' => 'GET'];
        $_POST   = [];

        ob_start();
        include GLPI_ROOT . '/tickettracker/front/config.form.php';
        $formHtml = (string) ob_get_clean();

        assertStringContains('name="cooldown_minutes"', $formHtml, 'Cooldown field rendered');
        assertStringContains('name="ignore_self_requester"', $formHtml, 'Ignore self requester field rendered');
        assertStringContains('name="show_tab_selfservice"', $formHtml, 'Selfservice tab field rendered');

        $_SERVER = [
            'REQUEST_METHOD'      => 'POST',
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
        ];
        $_POST = [
            'update'                => '1',
            'cooldown_minutes'      => '15',
            'ignore_self_requester' => '1',
            'show_tab_selfservice'  => '1',
        ];

        ob_start();
        include GLPI_ROOT . '/tickettracker/front/config.form.php';
        ob_end_clean();

        $saved = PluginTickettrackerConfig::getConfig();
        assertSameValue(15, $saved['cooldown_minutes'], 'POST updated cooldown_minutes');
        assertSameValue(1, $saved['ignore_self_requester'], 'POST updated ignore_self_requester');
        assertSameValue(1, $saved['show_tab_selfservice'], 'POST updated show_tab_selfservice');

        // Cross-origin POST deve falhar via Compat::denyAccess()
        $_SERVER = [
            'REQUEST_METHOD'      => 'POST',
            'HTTP_SEC_FETCH_SITE' => 'cross-site',
        ];
        $blocked = false;
        try {
            include GLPI_ROOT . '/tickettracker/front/config.form.php';
        } catch (RuntimeException $e) {
            if (PluginTickettrackerCompat::glpiMajor() >= 12) {
                assertTrueValue(
                    $e instanceof \Glpi\Exception\Http\AccessDeniedHttpException,
                    'GLPI 12 cross-origin must throw AccessDeniedHttpException'
                );
            }
            $blocked = true;
        }
        assertTrueValue($blocked, 'Cross-origin POST must be rejected');

        // Somente leitura deve bloquear POST
        TestRuntime::$rights['config'] = READ;
        $_SERVER                       = [
            'REQUEST_METHOD'      => 'POST',
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
        ];
        $denied = false;
        try {
            include GLPI_ROOT . '/tickettracker/front/config.form.php';
        } catch (RuntimeException $e) {
            $denied = true;
        }
        assertTrueValue($denied, 'Read-only config right must reject POST');
    } finally {
        $_SERVER = $origServer;
        $_POST   = $origPost;
    }
}

function test_install_and_uninstall_lifecycle(): void
{
    global $DB;

    resetFixture();
    assertTrueValue($DB->tableExists(PluginTickettrackerTracker::TABLE), 'Table must exist after install');

    $cfg = Config::getConfigurationValues(PluginTickettrackerConfig::CONTEXT);
    assertSameValue(5, $cfg['cooldown_minutes'] ?? null, 'Config defaults populated on install');

    plugin_tickettracker_uninstall();
    assertFalseValue($DB->tableExists(PluginTickettrackerTracker::TABLE), 'Table must be dropped on uninstall');

    $cfgAfter = Config::getConfigurationValues(PluginTickettrackerConfig::CONTEXT);
    assertSameValue([], $cfgAfter, 'Config values must be deleted on uninstall');
}

$tests = [
    'test_plugin_manifest_and_hooks',
    'test_rightname_typing_matches_glpi_core',
    'test_compat_helpers_and_sanitization',
    'test_dbquery_falls_back_to_query_on_glpi_10_0_5',
    'test_deny_access_behavior',
    'test_config_defaults_and_clamping',
    'test_records_first_view_and_deduplicates_within_same_request',
    'test_cooldown_prevents_increment_within_window_and_increments_after_expiry',
    'test_race_condition_duplicate_insert_falls_back_to_update',
    'test_ignore_self_requester_skips_ticket_creator',
    'test_selfservice_interface_hides_tab_when_show_tab_selfservice_disabled',
    'test_tab_rendering_and_xss_escaping',
    'test_item_purge_cleans_up_views',
    'test_config_form_get_and_post',
    'test_install_and_uninstall_lifecycle',
];

$isGlpi11Flag = in_array('--glpi11', $argv ?? [], true) || getenv('TICKETTRACKER_TEST_GLPI11') === '1';
if (TEST_TYPED_RIGHTNAME) {
    $modeLabel = 'GLPI 12.0.1 (typed $rightname)';
} elseif ($isGlpi11Flag) {
    $modeLabel = 'GLPI 11.0.0 (untyped $rightname, modern assets/webPath)';
} else {
    $modeLabel = 'GLPI 10.0.15 (untyped $rightname)';
}

echo "Running Ticket Tracker tests in {$modeLabel}...\n";

foreach ($tests as $testFn) {
    $testFn();
    echo "  [PASS] {$testFn}\n";
}

echo count($tests) . " tests passed in {$modeLabel}.\n";

if (!TEST_TYPED_RIGHTNAME && !$isGlpi11Flag) {
    foreach (['--glpi11', '--typed-rightname'] as $flag) {
        $cmd    = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . $flag . ' 2>&1';
        $output = [];
        $code   = 0;
        exec($cmd, $output, $code);
        foreach ($output as $line) {
            echo $line . "\n";
        }
        if ($code !== 0) {
            exit($code);
        }
    }
}
