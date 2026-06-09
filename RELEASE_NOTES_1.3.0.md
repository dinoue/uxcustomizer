# UX Customizer 1.3.0

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules. Configure under **Setup → UX Customizer**.

## ✨ What's new in 1.3.0

- **Consolidated dashboard layout** — Software summary, Hardware, Lifecycle and Details are merged into one single **System info** card (three columns: Software / Hardware / Lifecycle, with Volumes and Details as full-width sections below). Tickets and Contracts move to their own 2-column row underneath. Less scrolling, easier scanning.
- **OS vendor icons** next to the OS name — Windows, Red Hat, Ubuntu, Debian, Apple, Android, SUSE, generic Linux, … using Tabler brand glyphs with vendor-coloured icons (no bundled logo files, no trademark issues).

## Recent highlights (1.2.x)
- **Health score** now includes a lifecycle check (penalises assets overdue for replacement).
- **Volumes** in the Hardware section — mount point + used % usage bars.
- **Asset retention policy** (Lifecycle tab) — retention years per Computer type → retirement date on the dashboard.

## Modules
- **Menu Order** — per-profile left-menu reorder.
- **Color Palette** — selectable custom theme (light + dark); never overrides a user's choice.
- **Tab Order** — global per-itemtype reorder + hide/unhide of asset-page tabs.
- **Computer Dashboard** — the consolidated "Dashboard" tab on the Computer form. Pair with **Tab Order** to make it the first tab.

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.3.0.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Configure under **Setup → UX Customizer**.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
