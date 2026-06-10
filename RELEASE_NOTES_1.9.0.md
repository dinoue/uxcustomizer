# UX Customizer 1.9.0

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## New in 1.9.0 — competitive parity pass on the Impact Map

Three features picked from the competitive review (ServiceNow / SysAid / iTop / Device42):

- **Health overlay** — the map now shows *status*, not just topology. Nodes with **open tickets** (new / assigned / planned / waiting) or a **silent inventory agent** (no contact for more than 2 days) get a thick amber border (one issue) or red border (both). Click the node: the side panel shows the health level, open-ticket count and agent last-seen; tooltips carry the same info. Healthy/no-signal nodes stay visually clean. All signals come from native GLPI tables in two batched queries — no per-node load.
- **Auto-group types** (iTop-style) — a toolbar switch that collapses loose nodes into one dashed cluster per itemtype, e.g. "Computer (14)", whenever a type has more than 8 nodes on the canvas. Double-click expands; merged-edge "N conn." labels and the side panel work on type groups just like on named compounds. Default **on** for the org-wide view, **off** on the asset tab.
- **Export PNG** — download the current view as `impact-map-<date>.png` on a solid background, ready to paste into a change request or runbook.

## Recent highlights
- **1.8.0:** dagre "Flow" layout — native Impact Analysis look with our interactions.
- **1.7.0:** persistent node positions + deterministic seed + faster loads.
- **1.6.x:** Impact Map tab on Computer & Appliance, bounded directional depth.

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide), **Computer Dashboard**, **Impact Map**.

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.9.0.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Open a Computer/Appliance → Impact Map: unhealthy CIs now stand out at a glance.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
