# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-06-10

### Added
- **Impact Map on Ticket, Change and Problem forms** (SysAid-style "business impact in the ticket") — a new "Impact Map" tab on ITIL objects shows the **combined neighborhood of every asset linked to the object**: a technician triaging an incident, or a change manager assessing risk, sees the blast radius without leaving the form. Multi-seed bounded BFS (Forward/Backward depth selectors work as on assets); assets linked to the ticket but absent from the impact graph still appear as isolated nodes so nothing linked is invisible. **Seed assets are emphasized** (thicker dark border, larger label) — health colors still take precedence so problem nodes stay red/amber. No GLPI plugin offers this today.

### Changed
- **Per-scope rights model on the data endpoint** (was: super-admin for everything, which silently broke the asset tab for technicians):
  - org-wide view (Setup page) — still requires `config UPDATE`;
  - asset scope — requires **READ on that asset**;
  - ITIL scope — requires **READ on that Ticket/Change/Problem** (entity and visibility rules respected); the linked-asset seed list is resolved **server-side**, never accepted from the client.

## [1.9.0] - 2026-06-10

### Added
- **Health overlay on the map** (SysAid/ServiceNow-style) — nodes with open tickets (status new/assigned/planned/waiting) or a silent inventory agent (>2 days) get a thick **amber** (one issue) or **red** (both) status border. The side panel shows the detail: health level, open-ticket count, agent last-seen. Tooltips carry the same info. Nodes with no signal stay clean — no "unknown" noise. Data comes from two **batched** queries (`glpi_items_tickets`+`glpi_tickets`, `glpi_agents`) — never per-node.
- **Auto-group by type** (iTop-style) — a toolbar switch that collapses loose nodes into one dashed-border cluster per itemtype ("Computer (14)") whenever a type has more than 8 nodes on the canvas. Double-click expands, "N conn." edge labels and the side panel work on type groups exactly like on compounds. Default **on** for the org-wide config view (where hairballs live), **off** on the asset tab (depth-scoped graphs are small).
- **Export PNG** — toolbar button downloads the current view as `impact-map-<date>.png`, composited onto a solid background so it pastes cleanly into documents.

## [1.8.0] - 2026-06-10

### Added
- **"Flow" layout — native Impact Analysis look with our interactions.** The Impact Map gains a layout selector (Flow / Force / Tree). **Flow** computes a left-to-right layered layout with **dagre** — the exact algorithm GLPI's native Impact Analysis uses (cytoscape-dagre, rankdir LR) — so the map reads like the native page (sources left, impacted right), while keeping everything native lacks: collapsible compound groups with member counts, "N conn." merged-edge labels, legend filter pills, search dim, side panel, and persistent positions. Cycles are handled by dagre's greedy acyclicer. dagre 0.8.5 is bundled locally (no CDN).
- **Per-mode edge styling** — horizontal beziers in Flow, vertical in Tree, organic in Force.

### Changed
- The on-asset tab (Computer / Appliance) now **defaults to Flow** so it feels like the native Impact Analysis at first glance; the org-wide config-page view keeps **Force** as default (better for large, disconnected graphs). Switching modes re-lays out in place without re-fetching, and repositions collapsed groups to the average of their members.
- Saved positions (1.7.0) still win over any computed layout in Flow/Force modes — your manual arrangement is never discarded. Tree mode always recomputes (the hierarchical engine owns coordinates there) and never overwrites your saved arrangement.

## [1.7.0] - 2026-06-10

### Added
- **Stable, persistent node positions (ServiceNow-style)** — the Impact Map now remembers where nodes are. After the first layout (and after any manual drag, group expand, or "Expand all"), positions are saved per scope (asset + depths) in the browser's localStorage. The next load applies them as preset coordinates and **skips physics entirely** — instant render, zero drift. Saved scopes are capped at 20 (oldest pruned).

### Changed
- **Deterministic layout** — `layout.randomSeed` is now fixed, so even a first-time render of the same graph produces the same picture on every load (previously each load used a random seed and the layout drifted).
- **Faster first loads** — physics stabilization cut from 220 to 120 iterations, and the costly `improvedLayout` pre-pass is skipped above 150 nodes. Subsequent loads of a known graph skip physics entirely via saved positions.

### Notes
- Evaluated (and rejected) patching GLPI's native Impact Analysis with dagre: GLPI 11's native impact page uses **Cytoscape.js** (not vis.js), already lays out with **dagre LR**, and already persists positions via `glpi_impactcontexts` — the drift/speed issues were in our vis-network module, fixed here without touching core.

## [1.6.1] - 2026-06-09

### Fixed
- **On-asset Impact Map was effectively a global view** on dense CMDBs (629 nodes on ScorScomSQLP01). Cause: the BFS scope from 1.6.0 was undirected and unbounded, so it walked the entire connected component of the asset. Replaced with a **bounded directed BFS** — `forward` hops along arrows OUT (impacts) and `backward` hops along arrows IN (impacted by), independently. Defaults to **2 each** (matches GLPI's native Impact Analysis density). If the start node has no relations, the tab now returns an empty graph instead of silently falling back to the global view.

### Added
- **Depth selectors in the on-asset tab toolbar** — two compact dropdowns (Forward 1–5, Backward 1–5). Changing either re-fetches and re-renders. The config-page (org-wide) view is unchanged and has no depth limit.
- `ajax/impactmap.php` accepts `&forward=<int>&backward=<int>` (clamped 0–10 server-side).

## [1.6.0] - 2026-06-09

### Added
- **Impact Map tab on assets** — the same vis-network topology view is now available as an additional tab on **Computer** and **Appliance** form pages, alongside GLPI's native "Impact Analysis" tab. The new tab is **scoped to the subgraph connected to the current asset** (BFS over impact relations), so you immediately see this CI's neighborhood with collapsible compounds, edge counts, filter pills, tree layout and search dim. Registered via `Plugin::registerClass(ImpactMapTab::class, addtabon=[Computer, Appliance])` — the tab is additive; the native Impact Analysis tab keeps working untouched. Use Tab Order to move it next to or above the native tab.

## [1.5.0] - 2026-06-09

### Added — Impact Map polish
- **Edge merge labels** ("N conn.") — cluster edges now show how many real relations they merge, Faddom-style. Driven by vis-network's `getBaseEdges` API.
- **Filter pills in the legend** — each itemtype swatch is now a clickable pill. Click to hide every raw node of that type (and its edges); click again to bring them back. Cluster nodes are unaffected so groups stay visible while you focus the rest. Hidden state visually mutes the pill (faded + line-through).
- **Hierarchical layout toggle** — new toolbar button switches between force-directed and a top-down tree layout (`layout.hierarchical`, `sortMethod: 'directed'`). Great for "what depends on this DB" walks. Physics auto-disables in tree mode.
- **Search dims non-matches** — typing in the search box now fades non-matching nodes to 15% opacity instead of just selecting them (Faddom behaviour). The first match still gets focused.

## [1.4.0] - 2026-06-09

### Added
- **Impact Map module** — new tab in the UX Customizer config page that renders an org-wide topology view of GLPI's native impact relations using **vis-network** (Faddom-style). Reads `glpi_impactrelations` / `glpi_impactitems` / `glpi_impactcompounds` directly — no new tables. Compounds (named groups) start **collapsed** as a single node showing the member count (e.g. "Management Servers (3)"); double-click expands. Single-click a node opens a side panel with type / id / "Open in GLPI" link. Color-coded by itemtype, automatic legend from nodes actually present, search/highlight box, expand-all / collapse-all / fit buttons. Capped at 750 nodes per render (truncation surfaced in the status line). vis-network is bundled locally (no CDN). New `module_impactmap_enabled` toggle in General.

## [1.3.5] - 2026-06-09

### Changed
- **Vertical gap between dashboard sections** — the System info card, the Tickets/Contracts row, and the Activity timeline were sitting flush against each other. They now have a consistent 1 rem gap (driven by a flex column on `.uxc-ci-detail`), so the page reads as separate stacked sections.

## [1.3.4] - 2026-06-09

### Changed
- More breathing room around the **Volumes** section — extra space above (between Hardware/Lifecycle and the Volumes subhead) and below (before the card's bottom edge), plus a slightly larger gap between volume rows.

## [1.3.3] - 2026-06-09

### Changed
- **Details section removed.** Serial number and Last inventory move under **Hardware** in the System info card. Description and Inventory number are no longer shown on the dashboard (still on GLPI's main form).

## [1.3.2] - 2026-06-09

### Changed
- **System info layout breathes** — more space between the Software / Hardware / Lifecycle columns, the column divider has real padding around it, kv lists have a proper row gap, and the OS vendor pill has its own breathing room next to the "OS" label.
- **Volumes compacted** — empty / unknown / sub-1 GB volumes are now hidden (those produced lots of "—" filler rows); remaining volumes flow into an auto-fit grid (2–3 per row depending on width) with a slimmer bar. The Volumes section is now a small band, not a tall column.

## [1.3.1] - 2026-06-08

### Fixed
- **Dashboard "Edit" button did nothing** — it pointed at the same `computer.form.php?id=<id>` URL the Dashboard tab is already on, so clicking it appeared to do nothing. The button now forces `Computer$main` so it goes to the main (form) tab where you can edit fields.

## [1.3.0] - 2026-06-08

### Changed
- **Dashboard layout consolidated** — Software summary, Hardware, Lifecycle and Details are now one single **System info** card (three columns: Software / Hardware / Lifecycle, with Volumes and Details as full-width sections below). Tickets + Contracts move to a separate 2-column row underneath, keeping the page tidier.

### Added
- **OS vendor brand icons** next to the OS line (Windows / Red Hat / Ubuntu / Debian / Apple / Android / SUSE / Linux / generic) using Tabler brand glyphs with vendor-coloured icons, no bundled logo files.

## [1.2.3] - 2026-06-08

### Changed
- **Health score now includes a lifecycle check** — when a retirement date is known, "past retirement" (overdue for replacement) counts as a failing check (so the total becomes "X of 6"; it stays "X of 5" when there's no purchase date / retention to evaluate).

## [1.2.2] - 2026-06-08

### Fixed
- Dashboard top-bar **CI name was unreadable** (light text on GLPI's light page background). The name/subtitle now use GLPI's own theme text colour (`--tblr-body-color` / `--tblr-secondary`), so they're dark on light themes and light on dark themes.

## [1.2.1] - 2026-06-08

### Added
- **Volumes in the Hardware card** — mount point + used % with a usage bar (green / amber ≥75% / red ≥90%) and total size, from `glpi_items_disks`. Used % is clamped to an integer 0–100 before it drives the bar width (no CSS injection); mount points are HTML-escaped.

## [1.2.0] - 2026-06-08

### Added
- **Asset retention policy** — new **Lifecycle** config tab to set retention years per Computer type (e.g. Laptop 5y, Server 7y) with a default. The Computer Dashboard uses it with the purchase date (Infocom / Management tab) to show a **retirement date** and time remaining (or "overdue").
- **Lifecycle card** on the dashboard — purchase date, warranty end (from `glpi_infocoms`), retention, computed retirement + status pill.
- **Hardware summary card** — model, processor, memory (Σ), disk (Σ) from native inventory device tables.
- **Recent activity timeline** — last changes from `glpi_logs` (date / user / change).

### Changed
- Dashboard polish: status dots on security cards, card-title icons, tidier headers, timeline + status-pill styling (all scoped to `.uxc-ci-detail`).

## [1.1.2] - 2026-06-08

### Fixed
- **Antivirus card was always empty** — queried the wrong table. Corrected to GLPI 11's `glpi_itemantiviruses` (it had been `glpi_items_antiviruses`).

### Added
- **Tickets card: "New ticket"** button that opens a ticket pre-linked to the computer, plus a "View all tickets" link to the native Tickets tab.
- Clearer dashboard template — status dots on the security cards, icons on card titles, tidier card headers.

## [1.1.1] - 2026-06-08

### Changed
- **Computer Dashboard now shows real data** from standard GLPI 11 tables instead of placeholders: Connectivity from the native inventory **agent** (`glpi_agents` last-contact + version), Antivirus from `glpi_items_antiviruses` (active + up-to-date), Tickets broken down by status (open / pending), Contracts type + summed cost, and a **Health** score computed from those native signals. Serial / inventory number / description / last-inventory shown as detail rows. All queries are defensive (table-exists + try/catch).
- Fields with **no native source** (firewall status, uptime, unlicensed count, tags) are labelled honestly ("No data source") rather than shown as blanks — wire them from the lcornoc02 inventory mapping when available.

## [1.1.0] - 2026-06-08

### Added
- **Computer Dashboard module** — adds a card-based **"Dashboard"** tab to the Computer form (additive; native tabs untouched). Registered via `Plugin::registerClass(..., ['addtabon' => ['Computer']])`; data from existing GLPI tables (no new schema). Scaffolded with real basic fields + clear `TODO(lcornoc02)` markers for inventory/security data. Toggle: `module_dashboard_enabled`.
- **Setup menu entry** — UX Customizer now appears under **Setup** (via `menu_toadd` → `Menu::getMenuContent()`), in addition to the Setup → Plugins wrench icon. *(Menu is session-cached: appears after re-login / cache clear.)*
- **Color Palette: matching dark theme** — optional dark variant generated alongside the light palette (both selectable in My Settings).
- **Tab Order: hide/unhide** — eye toggle per tab to hide tabs from asset pages; Reset clears order and hidden state.

### Changed
- Default palette **name** is now the generic **"Custom"** (was "TC Transcontinental") — appropriate for a public-catalog plugin. Default colors are unchanged.

### Fixed
- `build.ps1` infinite `.build` recursion (staging dir copied into itself); bundle SortableJS locally (no CDN); plugin static assets served from `public/`.

## [1.0.0] - 2026-06-01

### Added
- Initial release as a modular super-admin UI customizer (rebranded and expanded from the standalone `taborder` plugin).
- **Menu Order module** — per-profile drag-and-drop reordering of the left navigation menu via the official `redefine_menus` hook. New menu items append at the bottom.
- **Color Palette module** — define a custom color palette that GLPI lists as a **selectable** theme (My Settings + Setup→General). It writes an SCSS file into `GLPI_THEMES_DIR` using GLPI 11's native theme system; it does **not** override users' chosen themes.
- **Tab Order module** — global, per-itemtype reordering of asset detail-page tabs; client-side (`tabreorder.js`) since GLPI 11 exposes no server hook for tab order.
- Independently toggleable modules (General tab) backed by a key/value config table.
- **Absorbs the former `taborder` plugin.** On fresh install, legacy `glpi_plugin_taborder_order` rows are migrated into `glpi_plugin_uxcustomizer_menuorders`, preserving saved per-profile menu order.
- CSRF handled by GLPI 11's `CheckCsrfListener` middleware (no explicit `validateCSRF`).
