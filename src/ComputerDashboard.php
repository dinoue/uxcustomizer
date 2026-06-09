<?php

/**
 * UX Customizer - Computer Dashboard module
 *
 * Adds a "Dashboard" tab to the Computer form showing a card-based CI overview.
 * It is an ADDITIVE tab — GLPI's native form and all its tabs stay intact (this
 * replaces an earlier pre_show_item approach that replaced the whole form).
 *
 * Registered in setup.php via:
 *   Plugin::registerClass(ComputerDashboard::class, ['addtabon' => ['Computer']]);
 * (There is no $PLUGIN_HOOKS['tabs'] hook — addtabon + registerClass is the
 * GLPI 11 mechanism. The tab lands AFTER core tabs; use the Tab Order module to
 * move it to the top.)
 *
 * @license   GPL-3.0-or-later
 */

namespace GlpiPlugin\Uxcustomizer;

use CommonGLPI;
use Computer;
use Dropdown;

class ComputerDashboard extends CommonGLPI
{
    /** Tab label shown on the Computer form. */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!($item instanceof Computer) || $item->isNewItem()) {
            return '';
        }
        if (!\GlpiPlugin\Uxcustomizer\Config::isModuleEnabled('dashboard')) {
            return '';
        }
        return self::createTabEntry(__('Dashboard', 'uxcustomizer'), 0, $item->getType(), 'ti ti-layout-dashboard');
    }

    /** Render the dashboard card view inside the tab. */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if (!($item instanceof Computer) || $item->isNewItem()) {
            return false;
        }

        global $CFG_GLPI;

        // Scoped dashboard stylesheet (browser-fetched → MUST be under public/).
        echo '<link rel="stylesheet" type="text/css" href="'
            . $CFG_GLPI['root_doc']
            . '/plugins/uxcustomizer/public/css/dashboard.css?v=' . PLUGIN_UXCUSTOMIZER_VERSION . '">';

        $data = self::gatherData($item);   // [$data] is used by the template
        include __DIR__ . '/../templates/computer_dashboard.html.php';

        return true;
    }

    /**
     * Map an OS name to a Tabler brand-icon class + a CSS tone class, used in
     * the consolidated System-info card. Returns ['icon' => 'ti …', 'tone' => '…'].
     * Falls back to a generic device icon so the row never looks broken.
     */
    public static function osIcon(string $osName): array
    {
        $s = strtolower($osName);
        // Tabler brand glyphs ship with GLPI 11. Where no brand-specific Tabler
        // glyph exists, use the closest fit.
        if (str_contains($s, 'windows'))                                                  { return ['icon' => 'ti ti-brand-windows',  'tone' => 'uxc-os-windows']; }
        if (str_contains($s, 'red hat') || str_contains($s, 'rhel'))                      { return ['icon' => 'ti ti-brand-redhat',   'tone' => 'uxc-os-redhat']; }
        if (str_contains($s, 'ubuntu'))                                                   { return ['icon' => 'ti ti-brand-ubuntu',   'tone' => 'uxc-os-ubuntu']; }
        if (str_contains($s, 'debian'))                                                   { return ['icon' => 'ti ti-brand-debian',   'tone' => 'uxc-os-debian']; }
        if (str_contains($s, 'mac') || str_contains($s, 'os x') || str_contains($s, 'macos'))
                                                                                          { return ['icon' => 'ti ti-brand-apple',    'tone' => 'uxc-os-apple']; }
        if (str_contains($s, 'android'))                                                  { return ['icon' => 'ti ti-brand-android',  'tone' => 'uxc-os-android']; }
        if (str_contains($s, 'ios'))                                                      { return ['icon' => 'ti ti-brand-apple',    'tone' => 'uxc-os-apple']; }
        if (str_contains($s, 'fedora') || str_contains($s, 'centos') || str_contains($s, 'rocky') || str_contains($s, 'alma'))
                                                                                          { return ['icon' => 'ti ti-brand-redhat',   'tone' => 'uxc-os-redhat']; }
        if (str_contains($s, 'suse') || str_contains($s, 'opensuse'))                     { return ['icon' => 'ti ti-brand-opensuse', 'tone' => 'uxc-os-suse']; }
        if (str_contains($s, 'linux'))                                                    { return ['icon' => 'ti ti-brand-tux',      'tone' => 'uxc-os-linux']; }
        if (str_contains($s, 'esxi') || str_contains($s, 'vmware'))                       { return ['icon' => 'ti ti-server',         'tone' => 'uxc-os-generic']; }
        return ['icon' => 'ti ti-device-desktop', 'tone' => 'uxc-os-generic'];
    }

    /**
     * Collect the dashboard data from existing GLPI tables (no new schema).
     *
     * NOTE (porting from lcornoc02): the detailed/inventory-derived fields —
     * connectivity (agent last-contact), antivirus, firewall, health checks,
     * uptime, unlicensed-software count, pending reboot, WSUS, custom fields,
     * tags — have a richer mapping in the lcornoc02 ComputerDashboard.php.
     * Drop that logic in where marked `TODO(lcornoc02)`. Everything here is
     * defensive so the tab always renders even when a source is empty.
     *
     * @return array<string,mixed>
     */
    private static function gatherData(Computer $c): array
    {
        global $DB, $CFG_GLPI;

        $id = (int) $c->getID();
        $f  = $c->fields;

        $name = function (string $table, $fk) {
            $fk = (int) $fk;
            return $fk > 0 ? Dropdown::getDropdownName($table, $fk) : '—';
        };

        // Owner: prefer the assigned user, else the group.
        $owner = '—';
        if (!empty($f['users_id'])) {
            $owner = $name('glpi_users', $f['users_id']);
        } elseif (!empty($f['groups_id'])) {
            $owner = $name('glpi_groups', $f['groups_id']);
        }

        // Operating system (Item_OperatingSystem relation).
        $os = ['name' => '—', 'version' => '—', 'install_date' => null];
        foreach ($DB->request([
            'SELECT' => ['operatingsystems_id', 'operatingsystemversions_id', 'install_date'],
            'FROM'   => 'glpi_items_operatingsystems',
            'WHERE'  => ['itemtype' => 'Computer', 'items_id' => $id],
            'LIMIT'  => 1,
        ]) as $row) {
            $os['name']         = $name('glpi_operatingsystems', $row['operatingsystems_id']);
            $os['version']      = $name('glpi_operatingsystemversions', $row['operatingsystemversions_id']);
            $os['install_date'] = $row['install_date'] ?? null;
        }

        $softwareInstalled = (int) countElementsInTable('glpi_items_softwareversions',
            ['itemtype' => 'Computer', 'items_id' => $id]);

        // ── Connectivity: native GLPI inventory agent (glpi_agents) ──
        $conn = ['ok' => null, 'label' => __('Connectivity', 'uxcustomizer'), 'detail' => __('No agent data', 'uxcustomizer')];
        try {
            if ($DB->tableExists('glpi_agents')) {
                foreach ($DB->request([
                    'FROM'  => 'glpi_agents',
                    'WHERE' => ['itemtype' => 'Computer', 'items_id' => $id],
                    'ORDER' => ['last_contact DESC'],
                    'LIMIT' => 1,
                ]) as $a) {
                    $last = $a['last_contact'] ?? null;
                    $ver  = trim((string) ($a['version'] ?? ''));
                    if (!empty($last) && $last !== 'NULL') {
                        $days = (int) floor((time() - strtotime((string) $last)) / 86400);
                        $conn['ok']    = $days <= 2;
                        $conn['label'] = $conn['ok'] ? __('Connectivity online', 'uxcustomizer') : __('Connectivity offline', 'uxcustomizer');
                        $seen = $days <= 0 ? __('today', 'uxcustomizer') : sprintf(_n('%d day ago', '%d days ago', $days, 'uxcustomizer'), $days);
                        $conn['detail'] = trim(($ver !== '' ? __('Agent', 'uxcustomizer') . ' ' . $ver . ' — ' : '') . __('last seen', 'uxcustomizer') . ' ' . $seen);
                    } else {
                        $conn['detail'] = __('Agent present, never reported', 'uxcustomizer');
                    }
                }
            }
        } catch (\Throwable $e) { /* keep placeholder */ }

        // ── Antivirus: native inventory (glpi_items_antiviruses) ──
        $av = ['ok' => null, 'label' => __('Antivirus', 'uxcustomizer'), 'detail' => __('No antivirus reported', 'uxcustomizer')];
        $avUpToDate = false;
        try {
            // GLPI 11 ItemAntivirus table is `glpi_itemantiviruses` (no "items_").
            if ($DB->tableExists('glpi_itemantiviruses')) {
                foreach ($DB->request([
                    'FROM'  => 'glpi_itemantiviruses',
                    'WHERE' => ['itemtype' => 'Computer', 'items_id' => $id],
                    'ORDER' => ['is_active DESC'],
                    'LIMIT' => 1,
                ]) as $row) {
                    $active      = !empty($row['is_active']);
                    $avUpToDate  = !empty($row['is_uptodate']);
                    $av['ok']    = $active;
                    $av['label'] = $active ? __('Antivirus enabled', 'uxcustomizer') : __('Antivirus disabled', 'uxcustomizer');
                    $nm          = trim((string) ($row['name'] ?? ''));
                    $av['detail'] = $nm !== '' ? $nm : '—';
                }
            }
        } catch (\Throwable $e) { /* keep placeholder */ }

        // ── Firewall: no native GLPI source — needs an inventory/agent feed ──
        $firewall = ['ok' => null, 'label' => __('Firewall', 'uxcustomizer'), 'detail' => __('No data source', 'uxcustomizer')];

        // ── Tickets: breakdown by status (join glpi_tickets) ──
        $ticketsLinked = (int) countElementsInTable('glpi_items_tickets', ['itemtype' => 'Computer', 'items_id' => $id]);
        $tOpen = 0; $tPending = 0;
        try {
            foreach ($DB->request([
                'SELECT'     => ['glpi_tickets.status AS status'],
                'FROM'       => 'glpi_items_tickets',
                'INNER JOIN' => ['glpi_tickets' => ['ON' => ['glpi_items_tickets' => 'tickets_id', 'glpi_tickets' => 'id']]],
                'WHERE'      => ['glpi_items_tickets.itemtype' => 'Computer', 'glpi_items_tickets.items_id' => $id],
            ]) as $t) {
                $s = (int) $t['status'];
                if (in_array($s, [1, 2, 3, 4], true)) { $tOpen++; }   // new / assigned / planned / waiting
                if ($s === 4) { $tPending++; }                         // waiting
            }
        } catch (\Throwable $e) { $tOpen = null; $tPending = null; }

        // ── Contracts: type + summed cost ──
        $contractsLinked = (int) countElementsInTable('glpi_contracts_items', ['itemtype' => 'Computer', 'items_id' => $id]);
        $cType = '—'; $cValue = null;
        try {
            foreach ($DB->request([
                'SELECT'     => ['glpi_contracts.id AS cid', 'glpi_contracts.contracttypes_id AS ctype'],
                'FROM'       => 'glpi_contracts_items',
                'INNER JOIN' => ['glpi_contracts' => ['ON' => ['glpi_contracts_items' => 'contracts_id', 'glpi_contracts' => 'id']]],
                'WHERE'      => ['glpi_contracts_items.itemtype' => 'Computer', 'glpi_contracts_items.items_id' => $id],
                'ORDER'      => ['glpi_contracts.id DESC'],
                'LIMIT'      => 1,
            ]) as $row) {
                $cType = $name('glpi_contracttypes', $row['ctype']);
                if ($DB->tableExists('glpi_contractcosts')) {
                    foreach ($DB->request(['SELECT' => ['cost'], 'FROM' => 'glpi_contractcosts', 'WHERE' => ['contracts_id' => $row['cid']]]) as $cc) {
                        $cValue = (float) ($cValue ?? 0) + (float) $cc['cost'];
                    }
                }
            }
        } catch (\Throwable $e) { /* keep defaults */ }
        $cValueStr = $cValue !== null ? ('$' . number_format($cValue, 2)) : null;

        // ── Details rows (native fields) ──
        $details = [];
        if (!empty($f['serial']))                { $details[] = ['label' => __('Serial'), 'value' => $f['serial']]; }
        if (!empty($f['otherserial']))           { $details[] = ['label' => __('Inventory number'), 'value' => $f['otherserial']]; }
        if (!empty($f['comment']))               { $details[] = ['label' => __('Description'), 'value' => $f['comment']]; }
        if (!empty($f['last_inventory_update'])) { $details[] = ['label' => __('Last inventory', 'uxcustomizer'), 'value' => substr((string) $f['last_inventory_update'], 0, 16)]; }

        // ── Lifecycle: purchase / warranty (Infocom) + retention policy ──
        $buyDate = null; $warrantyMonths = null; $warrantyEnd = null;
        try {
            if ($DB->tableExists('glpi_infocoms')) {
                foreach ($DB->request(['FROM' => 'glpi_infocoms', 'WHERE' => ['itemtype' => 'Computer', 'items_id' => $id], 'LIMIT' => 1]) as $ic) {
                    $buyDate        = !empty($ic['buy_date']) ? $ic['buy_date'] : ($ic['use_date'] ?? null);
                    $warrantyMonths = isset($ic['warranty_duration']) ? (int) $ic['warranty_duration'] : null;
                    $wStart         = !empty($ic['warranty_date']) ? $ic['warranty_date'] : $buyDate;
                    if (!empty($wStart) && $warrantyMonths !== null && $warrantyMonths > 0) {
                        $warrantyEnd = date('Y-m-d', strtotime($wStart . ' +' . $warrantyMonths . ' months'));
                    }
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

        $retYears   = Lifecycle::yearsForType((int) ($f['computertypes_id'] ?? 0));
        $retireDate = null; $remaining = null; $overdue = false;
        if (!empty($buyDate) && $retYears > 0) {
            $retireDate = date('Y-m-d', strtotime($buyDate . ' +' . $retYears . ' years'));
            $months     = (int) floor((strtotime($retireDate) - time()) / (30 * 86400));
            $overdue    = $months < 0;
            if ($overdue) {
                $remaining = sprintf(__('Overdue by %d months', 'uxcustomizer'), abs($months));
            } else {
                $y = intdiv($months, 12); $m = $months % 12;
                $remaining = $y > 0 ? sprintf(__('%1$d y %2$d m left', 'uxcustomizer'), $y, $m)
                                    : sprintf(__('%d months left', 'uxcustomizer'), $m);
            }
        }
        $lifecycle = [
            'buy_date'        => $buyDate,
            'warranty_months' => $warrantyMonths,
            'warranty_end'    => $warrantyEnd,
            'retention_years' => $retYears,
            'retire_date'     => $retireDate,
            'remaining'       => $remaining,
            'overdue'         => $overdue,
        ];

        // ── Health: computed from the native signals (incl. lifecycle) ──
        $checks = [
            $conn['ok'] === true,    // agent seen recently
            $av['ok'] === true,      // antivirus active
            $avUpToDate,             // antivirus up to date
            $tOpen === 0,            // no open tickets
            $os['name'] !== '—',     // OS inventoried
        ];
        if ($retireDate !== null) {
            $checks[] = !$overdue;   // within retention period (not overdue for replacement)
        }
        $passing = count(array_filter($checks));
        $total   = count($checks);
        $health  = [
            'ok'     => $passing === $total ? true : ($passing >= $total - 1 ? null : false),
            'label'  => $passing === $total ? __('Health: good', 'uxcustomizer')
                      : ($passing >= $total - 1 ? __('Health: warning', 'uxcustomizer') : __('Health: critical', 'uxcustomizer')),
            'detail' => sprintf(__('%1$d of %2$d checks passing', 'uxcustomizer'), $passing, $total),
        ];

        // ── Hardware summary (model + native inventory devices) ──
        $hw = ['model' => $name('glpi_computermodels', $f['computermodels_id'] ?? 0), 'cpu' => '—', 'ram' => '—', 'disk' => '—'];
        try {
            if ($DB->tableExists('glpi_items_deviceprocessors') && $DB->tableExists('glpi_deviceprocessors')) {
                $n = 0; $cpu = '';
                foreach ($DB->request([
                    'SELECT'     => ['glpi_deviceprocessors.designation AS d'],
                    'FROM'       => 'glpi_items_deviceprocessors',
                    'INNER JOIN' => ['glpi_deviceprocessors' => ['ON' => ['glpi_items_deviceprocessors' => 'deviceprocessors_id', 'glpi_deviceprocessors' => 'id']]],
                    'WHERE'      => ['glpi_items_deviceprocessors.itemtype' => 'Computer', 'glpi_items_deviceprocessors.items_id' => $id],
                ]) as $r) { $n++; if ($cpu === '') { $cpu = (string) $r['d']; } }
                if ($n > 0) { $hw['cpu'] = $cpu . ($n > 1 ? ' (×' . $n . ')' : ''); }
            }
            if ($DB->tableExists('glpi_items_devicememories')) {
                $mb = 0;
                foreach ($DB->request(['SELECT' => ['size'], 'FROM' => 'glpi_items_devicememories', 'WHERE' => ['itemtype' => 'Computer', 'items_id' => $id]]) as $r) { $mb += (int) $r['size']; }
                if ($mb > 0) { $hw['ram'] = round($mb / 1024, 1) . ' GB'; }
            }
            if ($DB->tableExists('glpi_items_deviceharddrives')) {
                $mb = 0;
                foreach ($DB->request(['SELECT' => ['capacity'], 'FROM' => 'glpi_items_deviceharddrives', 'WHERE' => ['itemtype' => 'Computer', 'items_id' => $id]]) as $r) { $mb += (int) $r['capacity']; }
                if ($mb > 0) { $hw['disk'] = $mb >= 1024 ? round($mb / 1024, 1) . ' GB' : $mb . ' MB'; }
            }
        } catch (\Throwable $e) { /* ignore */ }

        // ── Volumes (disk usage: mount point + used %) from glpi_items_disks ──
        $volumes = [];
        try {
            if ($DB->tableExists('glpi_items_disks')) {
                foreach ($DB->request([
                    'SELECT' => ['name', 'mountpoint', 'totalsize', 'freesize'],
                    'FROM'   => 'glpi_items_disks',
                    'WHERE'  => ['itemtype' => 'Computer', 'items_id' => $id],
                    'ORDER'  => ['mountpoint ASC'],
                ]) as $d) {
                    $total = (int) ($d['totalsize'] ?? 0);
                    $free  = (int) ($d['freesize'] ?? 0);
                    $pct   = $total > 0 ? (int) round((($total - $free) / $total) * 100) : null;
                    if ($pct !== null) { $pct = max(0, min(100, $pct)); }   // clamp 0..100 (used in CSS width)
                    $mount = trim((string) ($d['mountpoint'] ?? ''));
                    if ($mount === '') { $mount = trim((string) ($d['name'] ?? '')); }
                    $volumes[] = [
                        'mount'    => $mount !== '' ? $mount : '—',
                        'used_pct' => $pct,
                        'total_gb' => $total > 0 ? round($total / 1024, 1) : null,
                    ];
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

        // ── Activity (recent history from glpi_logs) ──
        $activity = [];
        try {
            if ($DB->tableExists('glpi_logs')) {
                foreach ($DB->request([
                    'FROM'  => 'glpi_logs',
                    'WHERE' => ['itemtype' => 'Computer', 'items_id' => $id],
                    'ORDER' => ['date_mod DESC'],
                    'LIMIT' => 6,
                ]) as $l) {
                    $who = trim((string) ($l['user_name'] ?? ''));
                    $who = $who !== '' ? trim(preg_replace('/\s*\(\d+\)\s*$/', '', $who)) : '';
                    $new = trim((string) ($l['new_value'] ?? ''));
                    $old = trim((string) ($l['old_value'] ?? ''));
                    $activity[] = [
                        'date' => $l['date_mod'] ?? null,
                        'who'  => $who,
                        'text' => $new !== '' ? $new : ($old !== '' ? $old : __('updated', 'uxcustomizer')),
                    ];
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

        return [
            // ── Top bar ──
            'name'        => $f['name'] ?? ('#' . $id),
            'type_label'  => Computer::getTypeName(1),
            'updated'     => $f['date_mod'] ?? null,
            'status'      => $name('glpi_states', $f['states_id'] ?? 0),
            'location'    => $name('glpi_locations', $f['locations_id'] ?? 0),
            'owner'       => $owner,
            'edit_url'    => Computer::getFormURLWithID($id),
            // Create a new ticket already linked to this computer; and a link to
            // the item's native Tickets tab.
            'new_ticket_url' => \Ticket::getFormURL() . '?_add_fromitem=1&itemtype=Computer&items_id=' . $id,
            'tickets_url'    => Computer::getFormURLWithID($id) . '&forcetab=Item_Ticket$1',

            // ── Security cards (native data; firewall needs an external feed) ──
            'connectivity' => $conn,
            'antivirus'    => $av,
            'firewall'     => $firewall,
            'health'       => $health,

            // ── Software summary ── (unlicensed/uptime not available natively)
            'software' => [
                'installed'    => $softwareInstalled,
                'unlicensed'   => null,
                'uptime'       => null,
                'os'           => $os['name'],
                'build'        => $os['version'],
                'install_date' => $os['install_date'],
            ],

            // ── Details / tags ── (tags come from a plugin; not native)
            'custom_fields' => $details,
            'tags'          => [],

            // ── Tickets ──
            'tickets' => ['linked' => $ticketsLinked, 'open' => $tOpen, 'pending' => $tPending],

            // ── Contracts ──
            'contracts' => ['assigned' => $contractsLinked, 'type' => $cType, 'value' => $cValueStr],

            // ── Lifecycle / Hardware / Volumes / Activity ──
            'lifecycle' => $lifecycle,
            'hardware'  => $hw,
            'volumes'   => $volumes,
            'activity'  => $activity,
        ];
    }
}
