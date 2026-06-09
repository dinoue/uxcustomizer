# UX Customizer 1.3.3

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## Changed in 1.3.3
- **Details section removed** from the dashboard. **Serial** and **Last inventory** now live under **Hardware** in the System info card.
- Description and Inventory number are no longer surfaced on the dashboard (they remain visible on GLPI's main form).

## Recent highlights
- **1.3.2:** System info breathes (column spacing, slimmer Volumes band).
- **1.3.1:** Edit button on the dashboard now jumps to the main form tab.
- **1.3.0:** consolidated System info card + OS vendor icons.

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide), **Computer Dashboard**.

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.3.3.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Configure under **Setup → UX Customizer**.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
