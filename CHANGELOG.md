# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.5] - 2026-06-09

### Changed
- **Vertical gap between dashboard sections** — the System info card, the Tickets/Contracts row, and the Activity timeline were sitting flush against each other. They now have a consistent 1 rem gap (driven by a flex column on `.uxc-ci-detail`), so the page reads as separate stacked sections.

## [1.3.4] - 2026-06-09

### Changed
- More breathing room around the **Volumes** section — extra space above (between Hardware/Lifecycle and the Volumes subhead) and below (before the card's bottom edge), plus a slightly larger gap between volume rows.

## [1.3.3] - 2026-06-09

### Changed
- **Details section removed.** Serial number and Last inventory move under **Hardware** in the System info card. Description and Inventory number are no longer shown on the dashboard (still on GLPI's main form).

## [1.3.2] - 2026-06-09

### Changed
- **System info layout breathes** — more space between the Software / Hardware / Lifecycle columns, the column divider has real padding around it, kv lists have a proper row gap, and the OS vendor pill has its own breathing room next to the "OS" label.
- **Volumes compacted** — empty / unknown / sub-1 GB volumes are now hidden (those produced lots of "—" filler rows); remaining volumes flow into an auto-fit grid (2–3 per row depending on width) with a slimmer bar. The Volumes section is now a small band, not a tall column.

## [1.3.1] - 2026-06-08

### Fixed
- **Dashboard "Edit" button did nothing** — it pointed at the same `computer.form.php?id=<id>` URL the Dashboard tab is already on, so clicking it appeared to do nothing. The button now forces `Computer$main` so it goes to the main (form) tab where you can edit fields.

## [1.3.0] - 2026-06-08

### Changed
- **Dashboard layout consolidated** — Software summary, Hardware, Lifecycle and Details are now one single **System info** card (three columns: Software / Hardware / Lifecycle, with Volumes and Details as full-width sections below). Tickets + Contracts move to a separate 2-column row underneath, keeping the page tidier.

### Added
- **OS vendor brand icons** next to the OS line (Windows / Red Hat / Ubuntu / Debian / Apple / Android / SUSE / Linux / generic) using Tabler brand glyphs with vendor-coloured icons, no bundled logo files.

## [1.2.3] - 2026-06-08

### Changed
- **Health score now includes a lifecycle check** — when a retirement date is known, "past retirement" (overdue for replacement) counts as a failing check (so the total becomes "X of 6"; it stays "X of 5" when there's no purchase date / retention to evaluate).

## [1.2.2] - 2026-06-08

### Fixed
- Dashboard top-bar **CI name was unreadable** (light text on GLPI's light page background). The name/subtitle now use GLPI's own theme text colour (`--tblr-body-color` / `--tblr-secondary`), so they're dark on light themes and light on dark themes.

## [1.2.1] - 2026-06-08

### Added
- **Volumes in the Hardware card** — mount point + used % with a usage bar (green / amber ≥75% / red ≥90%) and total size, from `glpi_items_disks`. Used % is clamped to an integer 0–100 before it drives the bar width (no CSS injection); mount points are HTML-escaped.

## [1.2.0] - 2026-06-08

### Added
- **Asset retention policy** — new **Lifecycle** config tab to set retention years per Computer type (e.g. Laptop 5y, Server 7y) with a default. The Computer Dashboard uses it with the purchase date (Infocom / Management tab) to show a **retirement date** and time remaining (or "overdue").
- **Lifecycle card** on the dashboard — purchase date, warranty end (from `glpi_infocoms`), retention, computed retirement + status pill.
- **Hardware summary card** — model, processor, memory (Σ), disk (Σ) from native inventory device tables.
- **Recent activity timeline** — last changes from `glpi_logs` (date / user / change).

### Changed
- Dashboard polish: status dots on security cards, card-title icons, tidier headers, timeline + status-pill styling (all scoped to `.uxc-ci-detail`).

## [1.1.2] - 2026-06-08

### Fixed
- **Antivirus card was always empty** — queried the wrong table. Corrected to GLPI 11's `glpi_itemantiviruses` (it had been `glpi_items_antiviruses`).

### Added
- **Tickets card: "New ticket"** button that opens a ticket pre-linked to the computer, plus a "View all tickets" link to the native Tickets tab.
- Clearer dashboard template — status dots on the security cards, icons on card titles, tidier card headers.

## [1.1.1] - 2026-06-08

### Changed
- **Computer Dashboard now shows real data** from standard GLPI 11 tables instead of placeholders: Connectivity from the native inventory **agent** (`glpi_agents` last-contact + version), Antivirus from `glpi_items_antiviruses` (active + up-to-date), Tickets broken down by status (open / pending), Contracts type + summed cost, and a **Health** score computed from those native signals. Serial / inventory number / description / last-inventory shown as detail rows. All queries are defensive (table-exists + try/catch).
- Fields with **no native source** (firewall status, uptime, unlicensed count, tags) are labelled honestly ("No data source") rather than shown as blanks — wire them from the lcornoc02 inventory mapping when available.

## [1.1.0] - 2026-06-08

### Added
- **Computer Dashboard module** — adds a card-based **"Dashboard"** tab to the Computer form (additive; native tabs untouched). Registered via `Plugin::registerClass(..., ['addtabon' => ['Computer']])`; data from existing GLPI tables (no new schema). Scaffolded with real basic fields + clear `TODO(lcornoc02)` markers for inventory/security data. Toggle: `module_dashboard_enabled`.
- **Setup menu entry** — UX Customizer now appears under **Setup** (via `menu_toadd` → `Menu::getMenuContent()`), in addition to the Setup → Plugins wrench icon. *(Menu is session-cached: appears after re-login / cache clear.)*
- **Color Palette: matching dark theme** — optional dark variant generated alongside the light palette (both selectable in My Settings).
- **Tab Order: hide/unhide** — eye toggle per tab to hide tabs from asset pages; Reset clears order and hidden state.

### Changed
- Default palette **name** is now the generic **"Custom"** (was "TC Transcontinental") — appropriate for a public-catalog plugin. Default colors are unchanged.

### Fixed
- `build.ps1` infinite `.build` recursion (staging dir copied into itself); bundle SortableJS locally (no CDN); plugin static assets served from `public/`.

## [1.0.0] - 2026-06-01

### Added
- Initial release as a modular super-admin UI customizer (rebranded and expanded from the standalone `taborder` plugin).
- **Menu Order module** — per-profile drag-and-drop reordering of the left navigation menu via the official `redefine_menus` hook. New menu items append at the bottom.
- **Color Palette module** — define a custom color palette that GLPI lists as a **selectable** theme (My Settings + Setup→General). It writes an SCSS file into `GLPI_THEMES_DIR` using GLPI 11's native theme system; it does **not** override users' chosen themes.
- **Tab Order module** — global, per-itemtype reordering of asset detail-page tabs; client-side (`tabreorder.js`) since GLPI 11 exposes no server hook for tab order.
- Independently toggleable modules (General tab) backed by a key/value config table.
- **Absorbs the former `taborder` plugin.** On fresh install, legacy `glpi_plugin_taborder_order` rows are migrated into `glpi_plugin_uxcustomizer_menuorders`, preserving saved per-profile menu order.
- CSRF handled by GLPI 11's `CheckCsrfListener` middleware (no explicit `validateCSRF`).
