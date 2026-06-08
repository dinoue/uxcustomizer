# UX Customizer 1.2.0

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules. Configure under **Setup → UX Customizer**.

## ✨ New in 1.2.0

- **Asset retention policy** — a new **Lifecycle** config tab to set how many years each Computer type is kept (e.g. Laptop 5y, Server 7y) with a default.
- **Computer Dashboard — Lifecycle card** — purchase date + warranty (from the Management/Infocom tab), your retention period, and a computed **retirement date** with time remaining (or an "overdue" warning).
- **Hardware summary card** — model, processor, memory and disk pulled from GLPI's native inventory.
- **Recent activity timeline** — the latest changes from the item history.
- Dashboard polish — status dots, card icons, tidier layout.

## Modules

- **Menu Order** — per-profile left-menu reorder.
- **Color Palette** — selectable custom theme (light + dark); never overrides a user's choice.
- **Tab Order** — global per-itemtype reorder + hide/unhide of asset-page tabs.
- **Computer Dashboard** — a card-based "Dashboard" tab on the Computer form: status (connectivity / antivirus / firewall / health), software, hardware, lifecycle, details, tickets (with **New ticket**), contracts, and recent activity. Pair with **Tab Order** to make it the first tab.

## Requirements

- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation

1. Extract `glpi-uxcustomizer-1.2.0.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Configure under **Setup → UX Customizer** (set retention years on the **Lifecycle** tab). *(The Setup menu entry appears after the next login — GLPI caches the menu.)*

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
