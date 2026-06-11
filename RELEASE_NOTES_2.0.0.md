# UX Customizer 2.0.0

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## New in 2.0.0 — Impact Map inside the ticket

The headline from our competitive review: SysAid puts the dependency graph **on the service-record form**, where triage actually happens. As of 2.0.0, so do we — and no other GLPI plugin does.

- **"Impact Map" tab on Ticket, Change and Problem forms** — seeded by **every asset linked to the object** (its Items tab). A technician opening an incident sees the blast radius of all affected CIs at once; a change manager sees what a maintenance window will really touch.
- **Multi-seed scoping** — the bounded directed BFS now walks forward/backward from all linked assets simultaneously (depth selectors 1–5 each, default 2/2). Linked assets with **no impact relations still appear** as isolated nodes, so nothing on the ticket is invisible.
- **Seed emphasis** — the ticket's own assets get a thicker dark border and larger label; health overlays (red/amber) still take precedence so problem nodes stand out first.
- **Everything from 1.x carries over** — Flow/Force/Tree layouts, collapsible compounds, type auto-grouping, "N conn." edge labels, filter pills, search dim, health overlay, position persistence, PNG export.

## Rights model (breaking-ish change, for the better)

The data endpoint previously required super-admin for every request — which silently broke the asset tab for technicians. Now rights match the scope:

| Scope | Requirement |
|---|---|
| Org-wide view (Setup page) | `config UPDATE` (unchanged) |
| Asset tab | **READ on that asset** |
| Ticket/Change/Problem tab | **READ on that ITIL object** (entity + visibility rules respected) |

The ITIL seed list is resolved **server-side** from the object's links — the client can't request arbitrary assets.

## Recent highlights
- **1.9.0:** health overlay, iTop-style type auto-grouping, PNG export.
- **1.8.0:** dagre "Flow" layout — native Impact Analysis look with our interactions.
- **1.7.0:** persistent positions + deterministic layout.

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide), **Computer Dashboard**, **Impact Map** (org-wide + assets + ITIL objects).

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-2.0.0.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Open any ticket with linked assets → **Impact Map** tab.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
