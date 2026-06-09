# UX Customizer 1.3.1

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## Fixed in 1.3.1
- **Dashboard "Edit" button did nothing** — it pointed at the same URL the Dashboard tab is already on, so clicking it appeared inert. The button now jumps to the main (form) tab where fields are editable.

## 1.3.0 highlights
- Consolidated dashboard layout: Software / Hardware / Lifecycle / Details merged into one **System info** card; Tickets + Contracts in a separate row.
- **OS vendor icons** next to the OS name (Windows / Red Hat / Ubuntu / Debian / Apple / Android / SUSE / Linux / generic) with vendor colours.

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide), **Computer Dashboard**.

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.3.1.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Configure under **Setup → UX Customizer**.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
