# UX Customizer 1.2.1

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules. Configure under **Setup → UX Customizer**.

## ✨ New in 1.2.1
- **Volumes in the Hardware card** — each disk's **mount point** and **used %** with a usage bar (turns amber ≥ 75 %, red ≥ 90 %) and total size, from GLPI inventory.

## 1.2.0 highlights
- **Asset retention policy** (Lifecycle tab) — retention years per Computer type; the dashboard shows purchase date, warranty, and a computed **retirement date** with time remaining.
- **Hardware summary**, **Recent activity timeline**, and dashboard polish.

## Modules
- **Menu Order** — per-profile left-menu reorder.
- **Color Palette** — selectable custom theme (light + dark); never overrides a user's choice.
- **Tab Order** — global per-itemtype reorder + hide/unhide of asset-page tabs.
- **Computer Dashboard** — a card-based "Dashboard" tab: status (connectivity / antivirus / firewall / health), software, hardware + **volumes**, lifecycle, details, tickets (with New ticket), contracts, and recent activity. Pair with **Tab Order** to make it the first tab.

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.2.1.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Configure under **Setup → UX Customizer** (set retention years on the **Lifecycle** tab).

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
