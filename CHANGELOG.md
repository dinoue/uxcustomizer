# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-06-01

### Added
- Initial release as a modular super-admin UI customizer (rebranded and expanded from the standalone `taborder` plugin).
- **Menu Order module** — per-profile drag-and-drop reordering of the left navigation menu via the official `redefine_menus` hook. New menu items append at the bottom.
- **Color Palette module** — define a custom color palette that GLPI lists as a **selectable** theme (My Settings + Setup→General). It writes an SCSS file into `GLPI_THEMES_DIR` using GLPI 11's native theme system; it does **not** override users' chosen themes.
- Independently toggleable modules (General tab) backed by a key/value config table.
- **Absorbs the former `taborder` plugin.** On fresh install, legacy `glpi_plugin_taborder_order` rows are migrated into `glpi_plugin_uxcustomizer_menuorders`, preserving saved per-profile menu order. The standalone `taborder` plugin is retired — do not run both at once.
- CSRF handled by GLPI 11's `CheckCsrfListener` middleware (no explicit `validateCSRF`).

- **Tab Order module** — global, per-itemtype reordering **and hide/unhide** of the tabs on asset detail pages (Computer, Monitor, NetworkEquipment, Peripheral, Phone, Printer, Software, Rack, Enclosure, PDU, Cluster). GLPI 11 exposes no server hook for tab order, so a globally-loaded script (`tabreorder.js`) reorders the rendered Bootstrap `.nav-tabs` client-side per the saved order; the config UI enumerates an itemtype's tabs via `defineAllTabs()`. New tabs append at the bottom. (Client-side/DOM-dependent — `uxcTabDebug()` console helper included.)
