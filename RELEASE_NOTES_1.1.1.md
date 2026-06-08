# UX Customizer 1.1.1

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules. Configure under **Setup → UX Customizer**.

## ✨ Highlights of the 1.1 line

- **Computer Dashboard** — a card-based **"Dashboard"** tab on the Computer form: top bar (name, status, location, owner), security cards (connectivity / antivirus / firewall / health), and a software / details / tickets / contracts grid. Additive — all native GLPI tabs stay intact. Pair with **Tab Order** to make it the first tab.
  - **1.1.1:** the dashboard now reads **real data** from standard GLPI tables — connectivity from the native inventory **agent**, antivirus from inventory, tickets by status, contracts type/cost, and a computed **Health** score. Fields with no native source (firewall, uptime, unlicensed, tags) are labelled honestly.
- **Setup menu entry** — the plugin appears under **Setup** (not just the Setup → Plugins wrench icon).
- **Color Palette: dark theme** — a matching dark variant alongside the light palette; both selectable per user.
- **Tab Order: hide/unhide** — hide individual tabs from asset pages (one-click Reset).

### Changed
- Default palette name is the generic **"Custom"** (colors unchanged).

### Fixed
- Build script no longer recurses into its own staging dir; SortableJS is bundled (no CDN); static assets served from `public/`.

## Modules
- **Menu Order** — per-profile left-menu reorder.
- **Color Palette** — selectable custom theme (light + dark); never overrides a user's choice.
- **Tab Order** — global per-itemtype reorder + hide/unhide of asset-page tabs.
- **Computer Dashboard** — the "Dashboard" tab (see above).

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.1.1.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Configure under **Setup → UX Customizer**. *(The Setup menu entry appears after the next login — GLPI caches the menu.)*

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
