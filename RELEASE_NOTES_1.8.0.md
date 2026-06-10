# UX Customizer 1.8.0

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## New in 1.8.0 — "Flow" layout: native look, our interactions

You liked the **layout** of GLPI's native Impact Analysis but not how it groups and moves. 1.8.0 combines both: the Impact Map now offers the exact layered left-to-right layout the native page uses, on top of all the interactions native lacks.

- **Flow layout (new default on the asset tab)** — positions are computed with **dagre** (rankdir LR), the same algorithm behind native Impact Analysis (cytoscape-dagre). Sources flow in from the left, impacted items fan out to the right, ranks are aligned — the familiar native waterfall.
- **Everything our module adds stays on top of it** — collapsible compound groups with `(N)` member counts, "N conn." merged-edge labels, legend filter pills, search dim, side panel with "Open in GLPI", and persistent positions.
- **Layout selector** — Flow (left-right) / Force (organic) / Tree (top-down) in the toolbar. Switching re-lays out in place, no re-fetch; collapsed groups are repositioned to the average of their members. Edges restyle per mode (horizontal beziers in Flow, vertical in Tree).
- **Your arrangement still wins** — positions saved in 1.7.0 (after drags, expands, first layout) take priority over any computed layout in Flow/Force. Tree never overwrites them.
- **dagre 0.8.5 bundled locally** — no CDN, same policy as vis-network and SortableJS.

Defaults: **on-asset tab = Flow** (feels like native at first glance), **org-wide config page = Force** (reads better on large, disconnected graphs).

## Recent highlights
- **1.7.0:** persistent node positions + deterministic seed + faster loads.
- **1.6.1:** on-asset scope — bounded directed BFS with Forward/Backward depth selectors.
- **1.6.0:** Impact Map tab on Computer & Appliance forms.
- **1.5.0:** edge counts, filter pills, tree layout, search dim.

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide), **Computer Dashboard**, **Impact Map**.

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.8.0.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Open a Computer/Appliance → Impact Map: it now opens in Flow layout, native-style.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
