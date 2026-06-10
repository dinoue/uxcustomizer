# UX Customizer 1.7.0

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## New in 1.7.0 — Stable positions & faster Impact Map

Two long-standing annoyances fixed: the Impact Map no longer re-shuffles itself on every visit, and repeat loads are near-instant.

- **Positions persist (ServiceNow-style)** — after the first layout, and after any manual drag / group expand / "Expand all", node positions are saved per scope (asset + forward/backward depths) in the browser. The next load applies them directly and **skips the physics simulation entirely**: instant render, nodes exactly where you left them. Up to 20 scopes are kept (oldest pruned automatically).
- **Deterministic layout** — even a first-time render now uses a fixed layout seed, so the same graph always produces the same picture (previously every load randomised the starting layout and the map drifted).
- **Faster first loads** — stabilization iterations cut roughly in half (220 → 120), and the expensive layout pre-pass is skipped above 150 nodes.

### Why not patch GLPI's native Impact Analysis?

We evaluated injecting a dagre layout into the native page and rejected it after checking GLPI 11's source: the native impact page uses **Cytoscape.js** (not vis.js), **already lays out with dagre (LR)**, and **already persists positions** in `glpi_impactcontexts`. The drift and slowness were specific to this plugin's vis-network view — fixed here with zero core modifications.

## Recent highlights
- **1.6.1:** on-asset scope fixed — bounded directed BFS with Forward/Backward depth selectors (default 2/2).
- **1.6.0:** Impact Map tab on Computer & Appliance forms.
- **1.5.0:** edge counts, filter pills, tree layout, search dim.
- **1.4.0:** Impact Map module (vis-network, collapsible groups).

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide), **Computer Dashboard**, **Impact Map**.

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.7.0.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Open a Computer/Appliance → Impact Map. Arrange nodes once; they stay put.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
