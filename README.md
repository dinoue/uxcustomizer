# UX Customizer — GLPI 11 Plugin

[![GLPI](https://img.shields.io/badge/GLPI-11.0.x-blue.svg)](https://glpi-project.org/)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A5%208.1-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-green.svg)](LICENSE)

Super-administrator **interface customization** for GLPI 11, organised as independently toggleable modules. Reorder the navigation menu, ship a branded color theme, and reorder or hide the tabs on asset pages — all from one config screen, without touching GLPI core.

> Each module can be turned **on or off** independently from the **General** tab.

## Modules

| Module | What it does | Scope | Mechanism |
|--------|--------------|-------|-----------|
| **Menu Order** | Drag-and-drop reorder of the left navigation menu (Assets, Assistance, Management, Tools, Administration, Setup…). | Per **profile** | `redefine_menus` hook (official GLPI API) |
| **Color Palette** | Define a custom color theme (primary, accent, page background, sidebar) and offer it — plus a matching **dark** variant — as a **selectable** palette. | Opt-in per **user** | Native GLPI theme (SCSS in the themes directory) |
| **Tab Order** | Reorder **and hide/unhide** the tabs on asset detail pages (Computer, Monitor, Network equipment, Printer, Software…). | **Global** per itemtype | Client-side reorder of the rendered tab bar |
| **Computer Dashboard** | Card-based "Dashboard" tab on the Computer form — hardware summary, volumes, lifecycle, tickets, contracts, recent activity. | Added to each Computer | `Plugin::registerClass(addtabon=Computer)` |
| **Impact Map** | Org-wide topology view of GLPI's native impact relations, with **collapsible groups** (Faddom-style). Also adds an **"Impact Map" tab on Computer and Appliance** forms, scoped to that asset's neighborhood. Read-only — uses existing `glpi_impactrelations` / `glpi_impactitems` / `glpi_impactcompounds`. | Admin view + on-asset tab | `vis-network` (bundled) reading existing tables |

New items always append at the bottom — a freshly installed plugin's menu entry or asset tab never disrupts your saved order.

## Requirements

| Component     | Version            |
|---------------|--------------------|
| GLPI          | 11.0.0 – 11.0.x    |
| PHP           | ≥ 8.1              |
| MySQL/MariaDB | 8.0+ / 10.5+       |

## Installation

1. Download the latest release tarball from the [Releases page](https://github.com/bacus99/uxcustomizer/releases) and extract it into your GLPI `plugins/` directory — the folder **must** be named `uxcustomizer`:
   ```
   <glpi>/plugins/uxcustomizer/
   ```
   (Or clone this repository there directly.)
2. In GLPI, go to **Setup → Plugins**.
3. Find **UX Customizer**, click **Install**, then **Enable**.
4. Open the configuration page (the wrench icon next to the plugin) and set up each module.

## Usage

Open **Setup → Plugins → UX Customizer**. The page has one tab per module:

### General
Enable/disable each module with a switch. Disabling a module instantly removes its effect (e.g. disabling Color Palette removes the generated theme files).

### Menu Order
Pick a **profile**, drag the top-level menu items into the order you want — changes save instantly. Each profile keeps its own order; **Reset to default** restores GLPI's native order for that profile.

### Color Palette
Choose the colors (primary, accent, page background, sidebar background/text), name the palette, and **Save**. This writes a GLPI theme; it appears in every user's **My Settings → Color palette** picker (and the **Setup → General** site default). It is **opt-in** — it does **not** override anyone's chosen theme. Tick **"Also generate a matching dark theme"** to publish a dark variant alongside the light one.

### Tab Order
Pick an **asset type** (Computer, Monitor, Network equipment, Peripheral, Phone, Printer, Software, Rack, Enclosure, PDU, Cluster). Drag its tabs into the desired order, and use the **eye icon** to hide a tab from the asset page (hidden tabs stay listed here, greyed out, so you can restore them). The order applies **globally** to all users. **Reset** restores GLPI's default order and shows all tabs again.

### Impact Map
Org-wide topology view (Faddom-style) of GLPI's native impact relations. Compounds (the named groups you create on an asset's Impact Analysis tab) start **collapsed** — each shows as a single colored node with the member count, e.g. "Management Servers (3)". Double-click a group to expand. Single-click any node to see details in the side panel with a link to open it in GLPI.

**Toolbar:**
- **Search** — type a name; non-matching nodes fade to 15% opacity (the first match is focused).
- **Collapse groups / Expand all / Fit / Export PNG** — re-cluster, open all groups, zoom to fit, or download the current view as an image.
- **Layout selector** — **Flow** (left-right layered via dagre — the same algorithm and look as GLPI's native Impact Analysis), **Force** (organic physics), or **Tree** (top-down hierarchical). The on-asset tab defaults to Flow; the org-wide view defaults to Force. Switching re-lays out in place.
- **What-if failure simulation** — toggle on, click a CI; everything it impacts (downstream) lights up red with an "N affected" count. Pure client-side, no new data.
- **Path highlighting** — toggle on, click two CIs; the shortest dependency chain between them is highlighted and the rest dims.
- **Mini-map** — toggleable overview inset with a live viewport rectangle; click to recentre.
- **Export** — PNG (raster), **SVG** (editable vector, opens in Visio), or PDF (print view).
- **Auto-group types** — collapses loose nodes into one dashed cluster per itemtype ("Computer (14)") when a type exceeds 8 nodes. On by default in the org-wide view.

**Health overlay** — nodes with open tickets or a silent inventory agent (>2 days) get an amber/red status border; the side panel shows open-ticket count and agent last-seen. Signals come from native `glpi_items_tickets` and `glpi_agents` in batched queries.

**Legend pills** — color swatches double as on/off filters; click "Computer" to hide all Computer nodes (and their edges); click again to restore. Cluster nodes ignore the filter so groups stay visible.

**Edge counts** — when a group is collapsed, edges from it to outside nodes show "N conn." labels so you can see how dense each link is, without expanding.

Color-coded by itemtype with an auto-generated legend. Capped at 750 nodes per render to keep the browser responsive. Read-only — never writes to GLPI's impact tables.

**Stable positions** — the layout is deterministic (fixed seed), and node positions persist per browser (localStorage, keyed by asset + depths). Once a graph has been laid out — or you've dragged nodes where you want them — subsequent loads render instantly with physics disabled and everything exactly where you left it.

**Also available as an on-asset tab** — Computer and Appliance forms get an "Impact Map" tab next to GLPI's native "Impact Analysis" tab. The on-asset version is **scoped to the subgraph connected to the current CI** using a bounded directed BFS: `forward` hops along arrows OUT (impacts) and `backward` hops along arrows IN (impacted by), independently. Two depth selectors in the toolbar let you dial each from **1 to 5** (default 2/2, matching GLPI's native impact analysis density). The native Impact Analysis tab keeps working untouched. Use Tab Order to position the new tab where you want it.

**And on Ticket / Change / Problem forms** — the same tab on ITIL objects shows the combined neighborhood of **every asset linked to the object** (multi-seed BFS, server-side resolution, READ rights on the object enforced). Seed assets are emphasized; linked assets with no impact relations still appear as isolated nodes. Blast radius during triage, change-risk assessment during planning — without leaving the form.

## How it works

- **Menu Order** registers a `redefine_menus` callback. GLPI renders the sidebar from the array this hook returns, so the plugin re-keys it into the saved per-profile order. It never mutates session state directly.
- **Color Palette** writes an SCSS palette file into GLPI's themes directory (`GLPI_THEMES_DIR`). GLPI discovers it automatically and lists it as a selectable theme — there is no plugin API to register a theme in code, so this is the supported, non-intrusive route.
- **Tab Order** — GLPI 11 exposes no server hook to reorder item-form tabs, so a small script loaded on every page detects the asset type and reorders/hides the rendered Bootstrap tab bar (`#tabspanel`) according to the saved settings. It is DOM-based by necessity and degrades gracefully (does nothing if GLPI's markup changes).
- **Impact Map** queries `glpi_impactrelations`, `glpi_impactitems` and `glpi_impactcompounds` directly (the same tables GLPI's per-asset Impact Analysis tab writes to) and renders the resulting graph with [vis-network](https://github.com/visjs/vis-network). It's read-only — saved compounds and relations remain managed from GLPI's native UI. vis-network is bundled inside the plugin (no CDN).

## Compatibility & limitations

- Built and tested against **GLPI 11.0.x**. The Tab Order module depends on GLPI's rendered tab markup; a future GLPI release could change it and require a plugin update (the other two modules use stable APIs).
- Tab Order and Color Palette apply to **everyone** (global / opt-in per user respectively); Menu Order is **per profile**.

## Building a release

```powershell
.\build.ps1                  # uses the version from setup.php
.\build.ps1 -Version 1.0.1   # or override
```

Produces `dist/glpi-uxcustomizer-<VERSION>.tar.bz2`, excluding everything listed in `.glpiignore`. The tarball extracts to a folder named `uxcustomizer/`.

## Publishing to the GLPI plugin catalog

1. Replace the `LICENSE` stub with the full GPL-3.0 text:
   ```powershell
   Invoke-WebRequest https://www.gnu.org/licenses/gpl-3.0.txt -OutFile LICENSE
   ```
2. Push this repository to `github.com/bacus99/uxcustomizer` (must be public).
3. Tag and publish the build:
   ```bash
   git tag -a 2.1.0 -m "Release 2.1.0"
   git push --tags
   gh release create 2.1.0 dist/glpi-uxcustomizer-2.1.0.tar.bz2 \
       --title "2.1.0" --notes-from-tag
   ```
4. Verify every URL in `plugin.xml` resolves (logo, homepage, issues, readme, and the `download_url`).
5. Submit to the [GLPI plugin catalog](https://plugins.glpi-project.org/) by opening a Pull Request to [pluginsGLPI/data](https://github.com/pluginsGLPI/data) adding your `plugin.xml` URL to `xml/plugins.json`.

## Contributing

Issues and pull requests are welcome at [github.com/bacus99/uxcustomizer](https://github.com/bacus99/uxcustomizer/issues). Translations: the template is `locales/uxcustomizer.pot` (target languages `en_GB`, `fr_FR`).

## License

[GPL-3.0-or-later](LICENSE) — © Christian Bernard.

## Author

**Christian Bernard** — [github.com/bacus99](https://github.com/bacus99)
