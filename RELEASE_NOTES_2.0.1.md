# UX Customizer 2.0.1

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## Security patch

- **SEC-1 (Medium) — entity isolation on the Impact Map.** Following the 2026-06-16 security review, `ImpactMap::getGraph()` now filters every node it returns through GLPI's `getEntitiesRestrictCriteria()` for the session's active entities. The map's BFS neighborhood and the ITIL (Ticket/Change/Problem) seed expansion can no longer surface item names or health status from entities the current user can't access. The guard **fails closed** and applies to every scope.

  This was harmless under 1.9.0 (the endpoint was super-admin-only) but became a live risk in **2.0.0**, which intentionally relaxed authorization so technicians can view the Impact Map for assets and tickets they have `READ` on. If you deployed 2.0.0 in a multi-entity install, upgrading to 2.0.1 is recommended.

See `SECURITY.md` for the full review disposition — SEC-3 was already resolved by 2.0.0's per-scope rights model; SEC-2 is intentionally kept as defense-in-depth; the Twig-migration quality items are on the roadmap.

## Everything from 2.0.0 (unchanged)
- **Impact Map on Ticket / Change / Problem forms** — seeded by every asset linked to the object (multi-seed BFS, server-side resolution, `READ` enforced). Seed assets emphasized; blast radius during triage.
- Per-scope rights model: org-wide → `config UPDATE`; asset → `READ` on the asset; ITIL → `READ` on the object.
- Plus the full 1.x Impact Map feature set: Flow/Force/Tree layouts, collapsible compounds, type auto-grouping, "N conn." edge labels, filter pills, search dim, health overlay, persistent positions, PNG export.

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide), **Computer Dashboard**, **Impact Map** (org-wide + assets + ITIL objects).

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-2.0.1.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
