# UX Customizer 2.1.1

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## Changed in 2.1.1

- **Tab Order now skips self-service users.** The tab order is still a single global setting that applies to all standard (central-interface) users — editing stays super-admin only, but the result is what everyone sees. It is no longer served to profiles on the simplified/self-service (`helpdesk`) interface: the reorder endpoint checks the requesting user's active profile interface and returns an empty order for non-central profiles. Self-service users never opened the central asset forms where these tabs live, so this turns an incidental exclusion into a guaranteed one.

## From 2.1.0 — Impact Map analysis tools
- What-if failure simulation, path highlighting, mini-map overview, and SVG/PDF export.

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide, central-interface users), **Computer Dashboard**, **Impact Map** (org-wide + assets + ITIL objects).

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-2.1.1.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
