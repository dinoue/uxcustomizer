# UX Customizer 1.1.0

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules. Configure under **Setup → UX Customizer**.

## ✨ What's new in 1.1.0

- **Computer Dashboard** *(new module)* — adds a card-based **"Dashboard"** tab to the Computer form: top bar (name, status, location, owner), security status cards (connectivity / antivirus / firewall / health), and a software / details / tickets / contracts grid. Additive — all native GLPI tabs are untouched. Pair it with **Tab Order** to make Dashboard the first tab.
- **Setup menu entry** — the plugin now appears under **Setup**, not just as the Setup → Plugins wrench icon.
- **Color Palette: dark theme** — generate a matching dark variant alongside your light palette; both are selectable per user.
- **Tab Order: hide/unhide** — hide individual tabs from asset pages (with a one-click Reset).

### Changed
- Default palette name is now the generic **"Custom"** (colors unchanged).

### Fixed
- Build script no longer recurses into its own staging dir; SortableJS is bundled (no CDN); static assets served from `public/`.

## Modules

- **Menu Order** — per-profile left-menu reorder (`redefine_menus`).
- **Color Palette** — selectable custom theme (light + dark) via GLPI's native theme system; never overrides a user's choice.
- **Tab Order** — global per-itemtype reorder + hide/unhide of asset-page tabs.
- **Computer Dashboard** — the new "Dashboard" tab (see above).

## Requirements

- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation

1. Extract `glpi-uxcustomizer-1.1.0.tar.bz2` (attached) into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Configure under **Setup → UX Customizer**. *(The Setup menu entry appears after the next login — GLPI caches the menu.)*

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
