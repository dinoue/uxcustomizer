# CLAUDE.md — UX Customizer plugin

## Shared conventions

Conventions for **all** of my GLPI 11 plugins live in [`../GLPI-Shared/`](../GLPI-Shared/CLAUDE.md). Read those rules first when starting any task. This file only covers what's specific to *this* plugin.

## Project goal

Super-admin **UI customization** for GLPI 11, as independently toggleable modules. Rebranded and expanded from the original single-purpose `taborder` plugin (now superseded — see "History").

## Plugin identity

| Field    | Value                                       |
|----------|---------------------------------------------|
| Slug     | `uxcustomizer` (deploy folder MUST match)   |
| Repo     | `bacus99/GLPI_UXCustomizer` (planned)       |
| Composer | `bacus99/glpi-uxcustomizer`                 |
| Namespace| `GlpiPlugin\Uxcustomizer\`                  |
| Tables   | `glpi_plugin_uxcustomizer_configs`, `glpi_plugin_uxcustomizer_menuorders` |

## Modules (independently toggleable)

| Module | Mechanism | Stability | Status |
|--------|-----------|-----------|--------|
| **Menu Order** | `redefine_menus` hook (per-profile) | ✅ official API | done |
| **Color Palette** | writes an SCSS palette into `GLPI_THEMES_DIR` (selectable theme) | ✅ uses GLPI's theme system | done |
| **Tab Order** | client-side DOM reorder of item-form tabs (global, per itemtype) | ⚠️ no GLPI hook exists | done (needs live verify) |
| **Computer Dashboard** | adds a "Dashboard" tab on the Computer form via `Plugin::registerClass(..., ['addtabon'])` | ✅ official API | in progress (lcornoc02) |

Toggles live in `glpi_plugin_uxcustomizer_configs` (`module_<name>_enabled`). `setup.php` only registers a module's hooks when it's enabled, wrapped in try/catch for early-boot safety.

## Architecture

```
uxcustomizer/
├── setup.php             module-gated hooks (redefine_menus; add_javascript for taborder)
├── hook.php              install: configs + menuorders + taborders tables, seed defaults
├── src/
│   ├── Config.php        key/value store + isModuleEnabled() (request-cached)
│   ├── MenuOrder.php     redefineMenus()/getOrder()/saveOrder() (ported from taborder)
│   ├── ColorPalette.php  get()/save()/reset()/removeThemeFile()/generateScss()
│   ├── TabOrder.php      ITEMTYPES, getTabs()/getDisplayTabs()/getSettings()/saveSettings()
│   └── Menu.php          Setup-menu entry (menu_toadd → getMenuContent → config.php)
├── front/
│   └── config.php        sectioned UI: General | Menu Order | Color Palette | Tab Order
├── ajax/
│   ├── menuorder.php     save/reset menu order (form POST, action field)
│   ├── palette.php       save/reset palette
│   └── taborder.php      get (GET, read-only) / save / reset tab order
├── public/
│   ├── js/Sortable.min.js   bundled (no CDN)
│   ├── js/menuorder.js      config drag → form POST
│   ├── js/palette.js        color inputs live preview + AJAX save/reset
│   ├── js/tabconfig.js      Tab Order config-page drag → save
│   ├── js/tabreorder.js     loaded globally; reorders .nav-tabs on asset pages
│   └── css/uxcustomizer.css
└── locales/uxcustomizer.pot
```

## Key mechanisms (all verified against GLPI 11 source)

- **Menu Order** — `redefine_menus` is fired in `Html::header()` as `$menu = Plugin::doHookFunction(Hooks::REDEFINE_MENUS, $menu)`; GLPI renders from the **returned** array (order = display order). We reorder and return; never mutate `$_SESSION['glpimenu']`. See [`../GLPI-Shared/rules/glpi-conventions.md`](../GLPI-Shared/rules/glpi-conventions.md) § "Altering the GLPI menu".
- **Color Palette** — adds a **selectable** GLPI theme; does **not** override anyone's choice (this was an explicit requirement — see `preference.php`). GLPI 11 discovers palettes as SCSS files in `GLPI_THEMES_DIR` (`Glpi\UI\ThemeManager`); there is **no** plugin API to register a theme in code. So `ColorPalette::save()` writes `GLPI_THEMES_DIR/<key>.scss` (key = slug of the palette name, ≤20 chars, no prefix) in GLPI's format: `:root[data-glpi-theme="<key>"] { --tblr-primary-rgb: …; --glpi-mainmenu-bg: …; --glpi-palette-color-1..4: …; }`. The file appears in My Settings + Setup→General. Disabling the module or uninstalling deletes the file.
  - **Writability:** `GLPI_THEMES_DIR` is under GLPI's var/files dir (`/usr/shared/glpifiles/_themes/`), which is writable by design. `writeThemeFile()` mkdirs it if missing and returns a clear error string (surfaced in the config UI) if not writable.
  - **Default palette name is "Custom"** (key `custom`). The default *colors* happen to match the TC Transcontinental brand (from tctranscontinental.com CSS): primary `#008fd5`, accent `#199ad9`, page bg `#f5f5f5`, sidebar `#000`/`#fff` — but the NAME is generic because this is a public-catalog plugin (it must not ship an org-specific default theme name). `generateScss()` maps the five colors to a fuller set of Tabler/GLPI vars (`--tblr-primary`(+rgb), `--tblr-link-color`(+rgb), `--tblr-link-hover-color`, `--tblr-active-bg`, `--tblr-body-bg`, `--glpi-mainmenu-bg/fg`, `--glpi-palette-color-1..4`) plus scoped sidebar nav-link rules.
  - **Theme key ≤ 20 chars** — `glpi_users.palette` is `char(20)`; `keyFromName()` slugifies + caps at 20 (no prefix). Default name "Custom" → key `custom`; dark sibling `custom-dark`.
  - No runtime hook — the palette module does its work entirely in the config page / ajax endpoint. There is **no** `add_css` injection (that would override users).
- **Tab Order** — there is **no** `redefine_tabs`-style hook in GLPI 11 (`CommonGLPI::defineAllTabs()` → `Ajax::createTabs()` renders a Bootstrap `<ul class="nav nav-tabs">`; tab keys live in each anchor's `_glpi_tab=`/`forcetab=` URL). So it's **client-side**:
  - **Config side:** `TabOrder::getTabs($class)` loads a sample item (lowest id, else `getEmpty()`) and calls `defineAllTabs()` to enumerate `tabKey => label`. The config UI (Tab Order section) shows them as a sortable list; `tabconfig.js` saves the global order per itemtype to `glpi_plugin_uxcustomizer_taborders` via `ajax/taborder.php`.
    - **Gotcha:** calling `defineAllTabs()` outside the normal item-display flow can make some tab-name renderers emit a non-fatal warning (observed: `Invalid callable 'Computers()'` from a Twig `call()` deep in a tab's name rendering). It's harmless for us (we only need the keys), so `getTabs()` wraps the call in a temporary `set_error_handler` + try/catch to swallow it. If a future need requires real tab labels for such a tab, switch to client-side enumeration (read the rendered `.nav-tabs` and POST them back) instead of `defineAllTabs()`.
  - **Hide/unhide + reset:** each tab in the config list has an eye toggle; hidden keys are stored in the `hidden_tabs` column alongside `tab_order`. `getSettings()`/`saveSettings()` handle both; the apply script sets `display:none` on hidden tabs. Reset deletes the row → clears order AND hidden (back to GLPI default, all tabs shown).
  - **Apply side:** `public/js/tabreorder.js` is loaded on **every** page (`add_javascript`). It detects the itemtype from the URL (`*.form.php` basename → `TabOrder::ITEMTYPES`), GETs the saved settings (`action=get`, read-only, no CSRF), reorders the tab `<li>` nodes (saved keys first, unknown/new tabs keep relative order at the bottom; the "All" tab has no key so it stays last), and hides any `hidden` keys.
  - **Confirmed GLPI 11 tab DOM (verified on a live Computer page):** the item tab rail is **`ul#tabspanel`** (`class="nav nav-tabs flex-row flex-md-column"`). Several *other* `.nav-tabs` exist (top navbar, debug toolbar), so target `#tabspanel` by id — NOT a bare `.nav-tabs`. Each `<li class="nav-item"><a class="nav-link" data-bs-toggle="tab">`; the tab key lives in `data-glpi-ajax-content` as `?_glpi_tab=<key>` (URL-encoded, `$`=`%24`) and in `href` as `&forcetab=<key>`. The key (`Computer$main`, `Item_OperatingSystem$1`, namespaced `Glpi\Asset\Asset_PeripheralAsset$1`, plugin `PluginFieldsContainer$31`, …) matches `defineAllTabs()` keys exactly — so saved order ↔ DOM align.
  - **Defensive:** `keyOf()` tries `data-glpi-ajax-content`, `href`, `data-bs-target`, parsing `_glpi_tab`/`forcetab`. If keys can't be read it bails silently. `window.uxcTabDebug()` dumps the detected tabs in the console.
  - **Scope:** main asset types (Computer, Monitor, NetworkEquipment, Peripheral, Phone, Printer, Software, Rack, Enclosure, PDU, Cluster) — `TabOrder::ITEMTYPES`. Global (one order per itemtype, all users). **NOT yet verified on a live page** — the tab-rail DOM/key attributes are from source research, not a real render; `uxcTabDebug()` is there to confirm/adjust.

- **Computer Dashboard** *(in progress — being built on `lcornoc02`)* — adds a **"Dashboard"** tab to the Computer form rendering a card-based CI overview. Files (per the lcornoc02 session): `src/ComputerDashboard.php`, `templates/computer_dashboard.html.php`, plus dashboard CSS/JS.
  - **Architecture decision (this supersedes a prior approach):** do **NOT** use a `pre_show_item` hook to replace the Computer form. Instead add a real tab — preserves all native GLPI tabs untouched. `ComputerDashboard` (a `CommonGLPI` subclass) implements `getTabNameForItem()` returning "Dashboard" and `displayTabContentForItem()` rendering the card view.
  - **⚠️ Registration correction (verified this session):** register the tab with `Plugin::registerClass(\GlpiPlugin\Uxcustomizer\ComputerDashboard::class, ['addtabon' => ['Computer']])` in `plugin_init_uxcustomizer()` — **not** `$PLUGIN_HOOKS['tabs']` (there is no `tabs` plugin hook; `addtabon` + registerClass is the GLPI 11 mechanism, as in tasksmanager's `TaskDashboard` on Ticket).
  - **⚠️ "First tab" caveat:** `addtabon` appends plugin tabs *after* core ones, and the numeric tab index only orders tabs *within* a class — it cannot force a plugin tab to be globally first. To actually make Dashboard the first tab, use **this plugin's own Tab Order module** to move `GlpiPlugin\Uxcustomizer\ComputerDashboard$1` (or whatever its key is) to the top. (Capture the real key with `uxcTabDebug()` once the tab renders.)
  - **⚠️ Asset paths:** browser-loaded CSS/JS must live under `public/` (`public/css/`, `public/js/`) — bare `css/`/`js/` 404 in GLPI 11 (verified). A server-side `templates/*.html.php` include is fine outside `public/`. The dashboard CSS is **scoped under `.uxc-ci-detail`** and includes dark-mode rules.
  - **Layout (from the mockup):** top bar (CI name, status badge, location, owner, Edit button); 4 security status cards (connectivity, antivirus, firewall, health score); a 2×2 card grid (Software summary, Details+Tags, Tickets metrics, Contracts). All data comes from existing GLPI tables — **no new schema for the MVP**; the data-source mapping already lives in `ComputerDashboard.php` and is kept as-is (only the rendering is wired into the tab hook instead of `pre_show_item`).
  - **Note on divergence:** the current published-prep tree here uses `public/css|js` and has no `templates/` dir yet; reconcile when porting the lcornoc02 work in (move assets under `public/`).

## Menu entry

The plugin exposes its config page two ways: the `config_page` hook (wrench icon on **Setup → Plugins**) and a **`menu_toadd`** entry under **Setup** (`['config' => Menu::class]`). `Menu::getMenuContent()` returns `title`/`page`(`/plugins/uxcustomizer/front/config.php`)/`icon`, gated by `config,UPDATE`. **Note:** the GLPI menu is session-cached, so a newly added `menu_toadd` entry only appears after the session menu rebuilds (re-login, profile switch, or cache clear) — not instantly after enabling the plugin.

## GLPI 11 gotchas applied here

- **Legacy file scope** — `front/`/`ajax/` files run in `LegacyFileLoadController` method scope; every one declares `global $CFG_GLPI, $DB;` after the include.
- **CSRF: middleware-only** — `CheckCsrfListener` validates the token (XHR: `X-Glpi-Csrf-Token` header; forms: hidden `_glpi_csrf_token` filled from `meta[property='glpi:csrf_token']`). No explicit `Session::validateCSRF()`.
- **Raw DDL** uses `$DB->doQueryOrDie()`.

## History

Originally built as the standalone `taborder` plugin (single-purpose menu reordering). It has been **merged into this plugin and removed** — its functionality is the Menu Order module (`TabOrder` → `MenuOrder`, table renamed). `plugin_uxcustomizer_install()` migrates legacy data: on fresh install it copies any rows from `glpi_plugin_taborder_order` into `glpi_plugin_uxcustomizer_menuorders`, so an admin upgrading from taborder keeps their saved per-profile order. The interim discovery that the original `change_profile`/`post_init` session-mutation approach was wrong (→ `redefine_menus`) is preserved in the shared rules.

**Deploy note:** uxcustomizer and the old taborder must not both be installed/active at once (both would register `redefine_menus`). Install uxcustomizer, confirm, then uninstall + remove the `taborder` plugin folder on the server.

## Open questions / future work

- **Logo** — `plugin.xml` references `logo.svg`, not yet created.
- **GitHub repo** — `bacus99/GLPI_UXCustomizer` not yet created.
- **Live install test** — not yet run end-to-end in a real GLPI 11. Confirm: (1) install creates both tables, (2) module toggles work, (3) menu reorder persists + applies, (4) saving a palette writes `GLPI_THEMES_DIR/uxc-*.scss`, the palette then appears in **My Settings → Color palette** and (when selected) recolors GLPI **without** changing other users, (5) disabling the palette module / uninstalling deletes the theme file. Watch for a "themes directory not writable" error in the palette UI — if so, fix perms on `/usr/shared/glpifiles/_themes/`.
- **Tab Order module** — not built; client-side approach scoped above.
- **i18n** — `.pot` only; no `.po`/`.mo` yet.
