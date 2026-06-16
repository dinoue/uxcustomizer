# UX Customizer 2.1.0

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## New in 2.1.0 — Impact Map analysis tools

Four additions that turn the Impact Map from a viewer into an analysis surface — all client-side, no new data, no NetstatConnections dependency.

- **What-if failure simulation** — toggle **What-if**, click any CI: everything it impacts (downstream, following the arrows out) flashes red, and the status line shows "N affected if this fails". Answers "if this server dies, what goes with it?" in one click. Works through collapsed groups.
- **Path highlighting** — toggle **Path**, click two CIs: the shortest dependency chain between them is highlighted and everything else dims. Answers "how does app X reach database Y?". Says so plainly when there's no path.
- **Mini-map overview** — a toggleable inset (bottom-left) of the whole graph with a live rectangle marking your current viewport; click anywhere on it to recentre. Stays aligned across Flow / Force / Tree layouts.
- **SVG + PDF export** — alongside PNG. **SVG** is rebuilt from the graph as real shapes/lines/labels, so it opens *editable* in Visio or Illustrator (Device42 parity) — no bundled library. **PDF** opens a clean print view to save from the browser.

## Recent highlights
- **2.0.x:** Impact Map on Ticket/Change/Problem forms; entity-isolation security fix.
- **1.9.0:** health overlay, type auto-grouping, PNG export.
- **1.8.0:** dagre "Flow" layout (native Impact Analysis look).

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide), **Computer Dashboard**, **Impact Map** (org-wide + assets + ITIL objects).

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-2.1.0.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Open any Impact Map (Setup page, asset tab, or ticket) — the new What-if / Path / Mini-map / export controls are in the toolbar.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
