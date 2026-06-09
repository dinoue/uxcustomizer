# UX Customizer 1.4.0

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## New in 1.4.0 — Impact Map module

A Faddom-style **org-wide topology view** of GLPI's native impact relations, rendered with [vis-network](https://github.com/visjs/vis-network).

- **Collapsible groups** — compounds (the named groups you create on an asset's Impact Analysis tab) start collapsed as a single colored node with the member count, e.g. "Management Servers (3)". Double-click to expand.
- **Click for details** — single-click a node to see a side panel (type, id, "Open in GLPI" link).
- **Search** — type a name to highlight and zoom to matching nodes.
- **Toolbar** — Collapse groups · Expand all · Fit.
- **Color-coded by itemtype** with an auto-generated legend from the nodes actually present in the graph.
- **Read-only** — never writes to GLPI's impact tables. Uses `glpi_impactrelations` / `glpi_impactitems` / `glpi_impactcompounds` as the single source of truth.
- **Performance** — capped at 750 nodes per render (truncation surfaced in the status line). vis-network is bundled locally (no CDN).

Find it under **Setup → UX Customizer → Impact Map**. Toggle the module on/off from the General tab.

## Recent highlights
- **1.3.5:** vertical gap between dashboard sections.
- **1.3.4:** breathing room around Volumes.
- **1.3.3:** Details section removed; Serial + Last inventory moved under Hardware.
- **1.3.2:** System info card breathes (column spacing, slimmer Volumes band).
- **1.3.0:** consolidated System info card + OS vendor icons.

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide), **Computer Dashboard**, **Impact Map**.

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.4.0.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Configure under **Setup → UX Customizer**.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
