# Security

UX Customizer is a super-administrator UI plugin for GLPI 11. This document records
the plugin's security posture and the disposition of past security reviews.

## Reporting a vulnerability

Open a private security advisory or an issue at
<https://github.com/bacus99/GLPI_UXCustomizer/issues>. Please do not include
exploit details in a public issue until a fix is released.

## Controls in place

| Area | Control |
|------|---------|
| **SQL injection** | All queries use the `$DB->request()/insert()/update()/delete()` query builder. Install DDL uses `doQueryOrDie()` with static strings only. |
| **XSS** | Every DB-sourced string echoed in `front/config.php`, `src/ImpactMapTab.php` and the dashboard template is passed through `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. Client-side, `impactmap.js` escapes all values via an `escapeHtml()` helper before insertion. |
| **CSRF** | Plugin declares `Hooks::CSRF_COMPLIANT`; state-mutating forms carry `_glpi_csrf_token`; GLPI 11's `CheckCsrfListener` validates. Read-only endpoints (e.g. the Impact Map GET) require no token. |
| **Path traversal** | `ColorPalette::keyFromName()` slugifies to `[a-z0-9-]` before building any theme file path. |
| **Itemtype whitelist** | `ImpactMap::knownItemtypes()` and `TabOrder::resolveItemtype()` whitelist every incoming itemtype string before it reaches `getTableForItemType()` or a query. |
| **Authorization (per scope)** | `ajax/impactmap.php` enforces: org-wide view → `config UPDATE`; asset scope → `READ` on the asset; ITIL scope → `READ` on the Ticket/Change/Problem. ITIL seed lists are resolved **server-side** — never accepted from the client. |
| **Entity isolation** | `ImpactMap::getGraph()` filters every resulting node through `getEntitiesRestrictCriteria()` for the session's active entities (see SEC-1 below). |
| **Input validation** | BFS depths clamped to `[0..10]`; profile/item IDs cast to `int`; colors validated against `#[0-9a-fA-F]{3,6}`. |

## Review history

### 2026-06-16 — full audit (against 1.9.0)

No critical/high findings. Disposition of the flagged items, re-assessed against the
**2.0.0** code (which introduced the per-scope rights model the review anticipated):

| ID | Severity | Status | Notes |
|----|----------|--------|-------|
| **SEC-1** — Impact Map returned cross-entity data without entity filtering | Medium | **Fixed in 2.0.1** | Was latent under 1.9.0's super-admin-only gate. 2.0.0 relaxed authorization to per-asset / per-ITIL `READ`, making it live. `ImpactMap::getGraph()` now runs every node through `getEntitiesRestrictCriteria()` (`filterByEntity()`), failing closed on error. Applies to all scopes. |
| **SEC-2** — `Session::checkLoginUser()` redundant on AJAX endpoints | Low | **Won't fix (by design)** | The review notes this does not weaken security. Removing it would only help if GLPI 11's request firewall is *guaranteed* to authenticate every plugin AJAX path; that assumption is not verified here, and the asymmetric risk (anonymous access if the assumption is wrong) outweighs the cosmetic benefit. Kept as defense-in-depth. |
| **SEC-3** — Impact Map tab visible to non-admins but AJAX always 403 | Low | **Fixed in 2.0.0** | The 2.0.0 per-scope rights model means anyone who can open the asset/ITIL form has the `READ` the AJAX now requires, so the tab works for them instead of erroring. |
| **Q-1 / Q-4** — migrate `echo`/PHP-include HTML to Twig templates | Quality | **Roadmap** | Tracked for a future release; large mechanical change, no security impact (output is escaped today). |
| **Q-2** — `setup.php` / `hook.php` role split differs from GLPI docs | Quality | **Acknowledged** | Functional; revisit alongside the Twig migration. |
| **Q-3** — AJAX error strings not wrapped in `__()` | Quality | **Roadmap** | Developer-facing strings; low priority. |

Items the review verified as already correct (SQLi, XSS, CSRF, path traversal, itemtype
whitelist, input validation, install/uninstall cleanliness, `front/config.php`
authorization) are summarised in the controls table above.
