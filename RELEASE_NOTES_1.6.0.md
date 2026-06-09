# UX Customizer 1.6.0

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## New in 1.6.0 — Impact Map on Computer & Appliance

The Faddom-style topology view from the config page is now also a **tab on Computer and Appliance form pages**, alongside GLPI's native "Impact Analysis" tab. The two coexist — the native tab keeps working as before.

- **Scoped to the asset's neighborhood** — when you open the new tab from a CI, the graph is restricted to the subgraph connected to that asset (BFS over impact relations). You see what's around _this_ machine, not the entire org.
- All the polish from 1.5.0 carries over: **collapsible groups** with `(N)` counts, **"N conn." edge labels**, **legend filter pills**, **Tree / Force layout toggle**, **search dim**, **side panel** with "Open in GLPI".
- **Additive** — registered via `Plugin::registerClass(addtabon=[Computer, Appliance])`. It never replaces or modifies GLPI's tabs. Use the **Tab Order** module to position the new tab next to (or above) the native "Impact Analysis" tab.
- **Read-only** — never writes to `glpi_impactrelations` / `glpi_impactitems` / `glpi_impactcompounds`. The native page remains the source of truth for editing.

## Recent highlights
- **1.5.0:** Impact Map polish — edge counts, filter pills, tree layout, search dim.
- **1.4.0:** Impact Map module — vis-network topology view of GLPI's native impact relations.

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide), **Computer Dashboard**, **Impact Map** (config page + on-asset tab).

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.6.0.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. The new "Impact Map" tab appears on every Computer and Appliance form. Toggle the module on/off from **Setup → UX Customizer → General**.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
