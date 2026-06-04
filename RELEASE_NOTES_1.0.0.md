# UX Customizer 1.0.0

First release — super-administrator UI customization for **GLPI 11**, as independently toggleable modules. Configure everything under **Setup → Plugins → UX Customizer**.

## ✨ Modules

- **Menu Order** — drag-and-drop reorder of the left navigation menu, saved **per profile**. Uses GLPI's official `redefine_menus` hook; newly installed plugins always append at the bottom.
- **Color Palette** — define a custom color theme (primary, accent, page background, sidebar) and offer it — plus a matching **dark** variant — as a **selectable** palette in *My Settings*. It does **not** override anyone's chosen theme; it installs as a native GLPI palette.
- **Tab Order** — **reorder and hide/unhide** the tabs on asset detail pages (Computer, Monitor, Network equipment, Peripheral, Phone, Printer, Software, Rack, Enclosure, PDU, Cluster). Global per asset type; new tabs append at the bottom; one-click reset.

Each module can be enabled/disabled independently from the **General** tab.

## ✅ Requirements

- GLPI **11.0.0 – 11.0.x**
- PHP **≥ 8.1**
- MySQL 8.0+ / MariaDB 10.5+

## 📦 Installation

1. Download `glpi-uxcustomizer-1.0.0.tar.bz2` (attached below) and extract it into your GLPI `plugins/` directory — the folder **must** be named `uxcustomizer`.
2. **Setup → Plugins** → **Install** → **Enable** UX Customizer.
3. Open the config page (wrench icon) to set up each module.

## 🔐 Notes

- CSRF-compliant (GLPI 11 `CheckCsrfListener`), PSR-4 autoloaded, no external CDN dependencies.
- The **Tab Order** module reorders the rendered tab bar client-side (GLPI 11 exposes no server hook for tab order); it degrades gracefully if GLPI's markup changes in a future release.

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
