# UX Customizer 1.3.5

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## Changed in 1.3.5
- **Spacing between dashboard sections** — the System info card, the Tickets/Contracts row, and the Activity timeline were sitting flush against each other. They now have a consistent gap so the page reads as separate stacked sections.

## Recent highlights
- **1.3.4:** breathing room around Volumes.
- **1.3.3:** Details section removed; Serial + Last inventory moved under Hardware.
- **1.3.2:** System info card breathes (column spacing, slimmer Volumes band).
- **1.3.1:** Edit button on the dashboard now jumps to the main form tab.
- **1.3.0:** consolidated System info card + OS vendor icons.

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide), **Computer Dashboard**.

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.3.5.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Configure under **Setup → UX Customizer**.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
