<?php

/**
 * UX Customizer - Impact Map data endpoint (GET, JSON)
 *
 * Returns the impact graph (nodes / edges / compounds) for the client
 * vis-network renderer. Read-only — does NOT mutate impact tables.
 *
 * Query params (all optional):
 *   itemtype  — restrict to the connected subgraph from this asset (with items_id)
 *   items_id  — paired with itemtype
 *   types[]   — filter nodes to these itemtypes (repeatable, e.g. ?types[]=Computer&types[]=Software)
 *
 * Response: { ok: bool, error?: string, data?: {nodes,edges,compounds,meta} }
 *
 * CSRF: read-only GET, no token required. Authn/Authz enforced below.
 *
 * @license   GPL-3.0-or-later
 */

use GlpiPlugin\Uxcustomizer\Config;
use GlpiPlugin\Uxcustomizer\ImpactMap;

include('../../../inc/includes.php');

global $CFG_GLPI, $DB;

header('Content-Type: application/json; charset=utf-8');

Session::checkLoginUser();

// The impact analysis itself isn't gated on a specific right in GLPI core
// (it's a side-tab on assets, gated by the asset's READ). For our admin-side
// map page we keep the same bar as the rest of the config UI: super-admin.
Session::checkRight('config', UPDATE);

$plugin = new Plugin();
if (!$plugin->isInstalled('uxcustomizer') || !$plugin->isActivated('uxcustomizer')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Plugin not active']);
    exit;
}

if (!Config::isModuleEnabled('impactmap')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Impact Map module disabled']);
    exit;
}

$scope = [];
if (!empty($_GET['itemtype']) && !empty($_GET['items_id'])) {
    // Whitelist itemtype against our known list — prevents arbitrary class
    // strings reaching getTableForItemType().
    $known = ImpactMap::knownItemtypes();
    if (isset($known[$_GET['itemtype']])) {
        $scope['itemtype'] = (string) $_GET['itemtype'];
        $scope['items_id'] = (int)    $_GET['items_id'];
    }
}
if (!empty($_GET['types']) && is_array($_GET['types'])) {
    $known = ImpactMap::knownItemtypes();
    $types = [];
    foreach ($_GET['types'] as $t) {
        if (is_string($t) && isset($known[$t])) {
            $types[] = $t;
        }
    }
    if ($types) {
        $scope['types'] = $types;
    }
}

try {
    $graph = ImpactMap::getGraph($scope);
    echo json_encode(['ok' => true, 'data' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to build graph']);
}
