<?php

/**
 * UX Customizer - Menu Order AJAX endpoint
 *
 * Response: { ok: bool, error?: string, data?: object }  (see glpi-plugin-api.md)
 * Actions (form-encoded POST):
 *   save  — fields: profiles_id, order (JSON array string)
 *   reset — field:  profiles_id
 *
 * CSRF is handled by GLPI's CheckCsrfListener middleware (X-Glpi-Csrf-Token
 * header / _glpi_csrf_token field). We do NOT call validateCSRF.
 *
 * @license   GPL-3.0-or-later
 */

use GlpiPlugin\Uxcustomizer\MenuOrder;

include('../../../inc/includes.php');

global $CFG_GLPI, $DB;

header('Content-Type: application/json; charset=utf-8');

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

$plugin = new Plugin();
if (!$plugin->isInstalled('uxcustomizer') || !$plugin->isActivated('uxcustomizer')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Plugin not active']);
    exit;
}

function uxc_respond(bool $ok, int $http = 200, ?string $error = null, array $data = []): void
{
    http_response_code($http);
    $out = ['ok' => $ok];
    if ($error !== null) { $out['error'] = $error; }
    if (!empty($data))   { $out['data']  = $data; }
    echo json_encode($out);
    exit;
}

$action    = $_POST['action'] ?? 'save';
$profileId = (int) ($_POST['profiles_id'] ?? 0);
if ($profileId <= 0) {
    uxc_respond(false, 400, 'missing profiles_id');
}

if ($action === 'reset') {
    MenuOrder::resetOrder($profileId);
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        Html::redirect($CFG_GLPI['root_doc']
            . '/plugins/uxcustomizer/front/config.php?tab=menuorder&profiles_id=' . $profileId);
    }
    uxc_respond(true, 200, null, ['reset' => true]);
}

$order = json_decode($_POST['order'] ?? 'null', true);
if (!is_array($order)) {
    uxc_respond(false, 400, 'order must be a JSON array');
}

// Whitelist submitted keys against the live menu.
$known = MenuOrder::getCurrentMenuKeys();
$clean = [];
foreach ($order as $key) {
    if (is_string($key) && $key !== '' && in_array($key, $known, true)) {
        $clean[] = $key;
    }
}
if ($clean === []) {
    uxc_respond(false, 400, 'order is empty after validation');
}

$ok = MenuOrder::saveOrder($profileId, $clean);
uxc_respond($ok, $ok ? 200 : 500, $ok ? null : 'save failed', ['count' => count($clean)]);
