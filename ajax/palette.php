<?php

/**
 * UX Customizer - Color Palette AJAX endpoint
 *
 * Writes/removes a custom palette SCSS file in GLPI_THEMES_DIR (see ColorPalette).
 * The palette becomes a SELECTABLE theme in My Settings — it does not override
 * anyone's chosen theme.
 *
 * Response: { ok: bool, error?: string, data?: object }
 * Actions (form-encoded POST):
 *   save  — fields: palette_name, palette (JSON object of colors)
 *   reset — restore default colors + rewrite the file
 *
 * CSRF handled by GLPI 11's CheckCsrfListener middleware.
 *
 * @license   GPL-3.0-or-later
 */

use GlpiPlugin\Uxcustomizer\ColorPalette;

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

$action = $_POST['action'] ?? 'save';

if ($action === 'reset') {
    $res = ColorPalette::reset();
    http_response_code($res['ok'] ? 200 : 500);
    echo json_encode([
        'ok'    => $res['ok'],
        'error' => $res['error'],
        'data'  => ['palette' => ColorPalette::get()],
    ]);
    exit;
}

$name   = (string) ($_POST['palette_name'] ?? '');
$dark   = !empty($_POST['palette_dark']);
$colors = json_decode($_POST['palette'] ?? 'null', true);
if (!is_array($colors)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'palette must be a JSON object']);
    exit;
}

$res = ColorPalette::save($name, $colors, $dark);
http_response_code($res['ok'] ? 200 : 500);
echo json_encode([
    'ok'    => $res['ok'],
    'error' => $res['error'],
    'data'  => ['palette' => ColorPalette::get(), 'key' => $res['key']],
]);
