# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
