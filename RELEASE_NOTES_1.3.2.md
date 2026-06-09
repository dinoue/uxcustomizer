# UX Customizer 1.3.2

Super-administrator UI customization for **GLPI 11**, as independently toggleable modules.

## Changed in 1.3.2
- **System info card breathes** — real space between the Software / Hardware / Lifecycle columns, generous padding around the column divider, proper row gap in the key/value lists, and a less-cramped OS vendor pill.
- **Volumes section compacted** — empty / unknown / sub-1 GB volumes are hidden (no more "—" filler rows); the rest flow into an auto-fit grid (2–3 per row) with a slimmer bar. The section is now a small band, not a tall column.

## Recent highlights
- **1.3.1:** Edit button on the dashboard now jumps to the main form tab (was a no-op).
- **1.3.0:** consolidated System info card + OS vendor icons.

## Modules
- **Menu Order**, **Color Palette** (light + dark), **Tab Order** (reorder + hide/unhide), **Computer Dashboard**.

## Requirements
- GLPI **11.0.0 – 11.0.x** · PHP **≥ 8.1** · MySQL 8.0+ / MariaDB 10.5+

## Installation
1. Extract `glpi-uxcustomizer-1.3.2.tar.bz2` into `glpi/plugins/` — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable**.
3. Configure under **Setup → UX Customizer**.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
