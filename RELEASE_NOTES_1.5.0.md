# UX Customizer 1.5.0

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## New in 1.5.0 — Impact Map polish

Four upgrades to the Impact Map module, closing the gap with Faddom-style topology tools.

- **Edge merge labels** ("N conn.") — when a group is collapsed, its edges to outside nodes now show how many real relations they bundle. So "Management Servers" linked to "SCOM" with 3 conn. tells you at a glance that all three servers feed the same dependency.
- **Filter pills** — click any color swatch in the legend to hide every raw node of that itemtype (and its edges); click again to restore. Cluster nodes ignore the filter, so groups stay visible while you focus on, say, just databases and apps.
- **Tree-layout toggle** — new toolbar button switches between force-directed and a directed top-down tree (`layout.hierarchical`, `sortMethod: 'directed'`). Tree mode makes "what depends on this DB" walks trivial. Physics auto-disables in tree mode.
- **Search dims non-matches** — type a name and the non-matching nodes fade to 15% opacity (Faddom behaviour) instead of just getting selected. First match is still focused for you.

## Recent highlights
- **1.4.0:** Impact Map module — vis-network topology view of GLPI's native impact relations with collapsible groups.
- **1.3.x:** Computer Dashboard polish (System info layout, OS vendor icons, Volumes, lifecycle health check).

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide), **Computer Dashboard**, **Impact Map**.

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.5.0.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Configure under **Setup → UX Customizer**.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
