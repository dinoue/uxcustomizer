# UX Customizer 1.2.3

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules. Configure under **Setup → UX Customizer**.

## Changed in 1.2.3
- **Health score now factors lifecycle** — if the asset is past its retirement date (overdue for replacement), that counts as a failing health check.

## Recent highlights (1.2.x)
- Dashboard top-bar CI name now uses GLPI's theme text colour (readable on light/dark).
- **Volumes** in the Hardware card — mount point + used % bars.
- **Asset retention policy** (Lifecycle tab) + dashboard retirement date.
- **Hardware summary**, **Recent activity timeline**, dashboard polish.

## Modules
- **Menu Order** — per-profile left-menu reorder.
- **Color Palette** — selectable custom theme (light + dark); never overrides a user's choice.
- **Tab Order** — global per-itemtype reorder + hide/unhide of asset-page tabs.
- **Computer Dashboard** — a card-based "Dashboard" tab: status (connectivity / antivirus / firewall / health), software, hardware + volumes, lifecycle, details, tickets (with New ticket), contracts, recent activity. Pair with **Tab Order** to make it the first tab.

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.2.3.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Configure under **Setup → UX Customizer**.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
