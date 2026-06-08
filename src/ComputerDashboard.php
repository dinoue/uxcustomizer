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
        global $DB;

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

        // Counts from existing relation tables.
        $softwareInstalled = (int) countElementsInTable('glpi_items_softwareversions',
            ['itemtype' => 'Computer', 'items_id' => $id]);
        $ticketsLinked = (int) countElementsInTable('glpi_items_tickets',
            ['itemtype' => 'Computer', 'items_id' => $id]);
        $contractsLinked = (int) countElementsInTable('glpi_contracts_items',
            ['itemtype' => 'Computer', 'items_id' => $id]);

        return [
            // ── Top bar ──
            'name'        => $f['name'] ?? ('#' . $id),
            'type_label'  => Computer::getTypeName(1),
            'updated'     => $f['date_mod'] ?? null,
            'status'      => $name('glpi_states', $f['states_id'] ?? 0),
            'location'    => $name('glpi_locations', $f['locations_id'] ?? 0),
            'owner'       => $owner,
            'edit_url'    => Computer::getFormURLWithID($id),

            // ── Security cards ── TODO(lcornoc02): wire real inventory/agent data
            'connectivity' => ['ok' => null, 'label' => __('Connectivity', 'uxcustomizer'), 'detail' => __('No agent data', 'uxcustomizer')],
            'antivirus'    => ['ok' => null, 'label' => __('Antivirus', 'uxcustomizer'),    'detail' => '—'],
            'firewall'     => ['ok' => null, 'label' => __('Firewall', 'uxcustomizer'),     'detail' => '—'],
            'health'       => ['ok' => null, 'label' => __('Health', 'uxcustomizer'),       'detail' => '—'],

            // ── Software summary ──
            'software' => [
                'installed'    => $softwareInstalled,
                'unlicensed'   => null,   // TODO(lcornoc02)
                'uptime'       => null,   // TODO(lcornoc02)
                'os'           => $os['name'],
                'build'        => $os['version'],
                'install_date' => $os['install_date'],
            ],

            // ── Details / custom fields / tags ── TODO(lcornoc02)
            'custom_fields' => [],   // [ ['label'=>..,'value'=>..], ... ]
            'tags'          => [],   // [ 'all-computers', 'windows', ... ]

            // ── Tickets ──
            'tickets' => [
                'linked'  => $ticketsLinked,
                'open'    => null,   // TODO(lcornoc02): breakdown by status
                'pending' => null,
            ],

            // ── Contracts ──
            'contracts' => [
                'assigned' => $contractsLinked,
                'type'     => '—',   // TODO(lcornoc02)
                'value'    => null,  // TODO(lcornoc02)
            ],
        ];
    }
}
