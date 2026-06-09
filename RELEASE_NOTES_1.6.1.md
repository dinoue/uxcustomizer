# UX Customizer 1.6.1

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## Fixed in 1.6.1

- **On-asset Impact Map showed a near-global view on dense CMDBs.** On a Computer with many impact relations (e.g. ScorScomSQLP01 with 629 reachable nodes), the 1.6.0 BFS scope walked the entire connected component — effectively showing the whole org. Cause: undirected, unbounded BFS.

## How it's fixed

The on-asset scope now uses a **bounded directed BFS** that mirrors GLPI's native Impact Analysis:

- **Forward** hops follow arrows **OUT** of the asset (what this CI impacts).
- **Backward** hops follow arrows **IN** to the asset (what impacts this CI).

Both default to **2 hops** (matches the native impact analysis density). If the asset has no impact relations, the tab now shows an empty graph instead of falling back to the global view.

## New depth selectors in the on-asset toolbar

Two compact dropdowns are added — **Forward (1–5)** and **Backward (1–5)** — so you can dial the scope without leaving the page. The graph re-fetches and re-renders when either changes. The config-page (org-wide) view is unchanged and has no depth limit.

```
[ Search… ] [ Collapse ] [ Expand ] [ Fit ] [ Tree ] [ Forward 2 ] [ Backward 2 ]
```

The AJAX endpoint accepts `&forward=<int>&backward=<int>` (clamped server-side to 0–10 to prevent runaway queries).

## Recent highlights
- **1.6.0:** Impact Map tab on Computer & Appliance forms (alongside native Impact Analysis).
- **1.5.0:** Impact Map polish — edge counts, filter pills, tree layout, search dim.
- **1.4.0:** Impact Map module — vis-network topology view of GLPI's native impact relations.

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide), **Computer Dashboard**, **Impact Map**.

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.6.1.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Open any Computer or Appliance form → the new "Impact Map" tab now shows a scoped view (defaults to 2 hops forward, 2 hops backward).

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
