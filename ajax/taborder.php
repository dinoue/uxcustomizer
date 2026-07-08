<?php

/**
 * UX Customizer - Tab Order AJAX endpoint
 *
 * Response: { ok: bool, error?: string, data?: object }
 * Actions:
 *   get   (GET)  — fields: itemtype (slug or class). Returns the saved tab-key
 *                  order for the client reorder script. Read-only, no CSRF.
 *   save  (POST) — fields: itemtype, order (JSON array of tab keys). config UPDATE.
 *   reset (POST) — fields: itemtype. config UPDATE.
 *
 * For save/reset, CSRF is handled by GLPI's CheckCsrfListener middleware.
 *
 * @license   GPL-3.0-or-later
 */

use GlpiPlugin\Uxcustomizer\TabOrder;

include('../../../inc/includes.php');

global $CFG_GLPI, $DB;

header('Content-Type: application/json; charset=utf-8');

Session::checkLoginUser();

$plugin = new Plugin();
if (!$plugin->isInstalled('uxcustomizer') || !$plugin->isActivated('uxcustomizer')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Plugin not active']);
    exit;
}

$action   = $_REQUEST['action'] ?? 'get';
$rawType  = (string) ($_REQUEST['itemtype'] ?? '');
$class    = TabOrder::resolveItemtype($rawType);

if ($class === null) {
    // Unsupported itemtype (e.g., 'config' on config pages). Return gracefully with empty order
    // instead of 400, since external scripts (workflow-refresh.js) may call this for any page.
    http_response_code(200);
    echo json_encode(['ok' => true, 'data' => ['order' => [], 'hidden' => []]]);
    exit;
}

// ── get: read-only, any logged-in user on the CENTRAL interface ──
// The reorder applies to everyone EXCEPT simplified/self-service profiles
// (GLPI profile interface 'helpdesk'). Those users don't normally reach the
// central asset forms anyway; returning an empty order makes the exclusion
// explicit and guaranteed rather than incidental. Missing/unknown interface
// defaults to central (preserves the apply-to-everyone behaviour).
if ($action === 'get') {
    $interface = $_SESSION['glpiactiveprofile']['interface'] ?? 'central';
    if ($interface !== 'central') {
        echo json_encode(['ok' => true, 'data' => ['order' => [], 'hidden' => []]]);
        exit;
    }
    $s = TabOrder::getSettings($class);
    echo json_encode(['ok' => true, 'data' => ['order' => $s['order'], 'hidden' => $s['hidden']]]);
    exit;
}

// save / reset require config UPDATE
Session::checkRight('config', UPDATE);

if ($action === 'reset') {
    TabOrder::resetOrder($class);
    echo json_encode(['ok' => true, 'data' => ['reset' => true]]);
    exit;
}

// save
$order  = json_decode($_POST['order'] ?? 'null', true);
$hidden = json_decode($_POST['hidden'] ?? '[]', true);
if (!is_array($order)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'order must be a JSON array']);
    exit;
}
if (!is_array($hidden)) {
    $hidden = [];
}

// Whitelist both arrays against the itemtype's real tab keys.
$known = array_keys(TabOrder::getTabs($class));
$cleanOrder = [];
foreach ($order as $key) {
    if (is_string($key) && $key !== '' && in_array($key, $known, true)) {
        $cleanOrder[] = $key;
    }
}
$cleanHidden = [];
foreach ($hidden as $key) {
    if (is_string($key) && $key !== '' && in_array($key, $known, true)) {
        $cleanHidden[] = $key;
    }
}
if ($cleanOrder === []) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'order is empty after validation']);
    exit;
}

$ok = TabOrder::saveSettings($class, $cleanOrder, $cleanHidden);
echo json_encode([
    'ok'    => $ok,
    'error' => $ok ? null : 'save failed',
    'data'  => ['count' => count($cleanOrder), 'hidden' => count($cleanHidden)],
]);
