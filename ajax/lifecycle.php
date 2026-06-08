<?php

/**
 * UX Customizer - Lifecycle / retention save endpoint
 *
 * Accepts a plain form POST (the Lifecycle config tab) with:
 *   retention_default        — default retention years
 *   retention[<typeId>]      — per-Computer-type years
 * CSRF handled by GLPI 11's CheckCsrfListener middleware.
 *
 * @license   GPL-3.0-or-later
 */

use GlpiPlugin\Uxcustomizer\Lifecycle;

include('../../../inc/includes.php');

global $CFG_GLPI, $DB;

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

$plugin = new Plugin();
if (!$plugin->isInstalled('uxcustomizer') || !$plugin->isActivated('uxcustomizer')) {
    Html::displayNotFoundError();
}

$map = ['default' => (int) ($_POST['retention_default'] ?? Lifecycle::DEFAULT_YEARS)];
if (isset($_POST['retention']) && is_array($_POST['retention'])) {
    foreach ($_POST['retention'] as $typeId => $years) {
        $map[(string) $typeId] = (int) $years;
    }
}
Lifecycle::saveRetentionMap($map);

Session::addMessageAfterRedirect(__('Retention policy saved.', 'uxcustomizer'), true, INFO);
Html::redirect($CFG_GLPI['root_doc'] . '/plugins/uxcustomizer/front/config.php?tab=lifecycle');
