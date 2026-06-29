# Changelog

All notable changes to **e-vignetta.eu – Elektronické diaľničné známky** (`vintrica-vignette-form`) are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).  
Versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

_No changes yet._

## [1.6.3] - 2026-06-29

### Changed
- Redesigned HTML email templates with accent color `#02B79C`, white header, `#F8FAFC` body background, white cards (12px radius, subtle shadow), and updated button styling.
- Admin order and test notification emails now use the same HTML template as customer emails (subjects and sending hooks unchanged).
- Email footer shows **e-vignetta.eu** only; optional logo via `vintrica_email_logo_url` filter.

### Fixed
- N/A

## [1.6.2] - 2026-06-29

### Changed
- User-visible branding updated from VINTRICA to **e-vignetta.eu** (admin UI, email headers/footers, Stripe product name, activator messages).
- Internal class names, hooks, database tables, and plugin slug unchanged for backward compatibility.

## [1.6.1] - 2026-06-29

### Added
- Customer HTML emails on order created and order paid (`Vintrica_Customer_Emails`, `Vintrica_Email_Template`).
- Admin settings toggles: `vintrica_customer_email_created`, `vintrica_customer_email_paid` (default ON).

### Changed
- Order-created customer email queued on `shutdown` so Stripe Checkout URL is available before send.

## [1.6.0] - 2026-06-29

### Added
- `Vintrica_Country_Registry` with 43 European countries (ISO code + SK/EN labels).
- Registration and billing country dropdowns use the shared registry.

### Changed
- Vignette catalog country labels resolved through the registry with backward-compatible `resolve_label()` / `normalize_registration_value()`.

## [1.5.5] - 2026-06-26

### Added
- Server-side pricing validation with `vehicle_type`.
- Rate limiting (5 requests / 10 minutes / IP).
- Honeypot field on checkout.
- Stripe secret key masking in admin.
- No-cache headers on sensitive responses.

### Security
- SQL injection and XSS audit hardening across checkout and order handling.

## [1.5.0] - 2026-06-26

### Added
- Editable vignette catalog (`Vintrica_Catalog`) with admin CRUD UI (**Cenník známok**).
- Database tables for countries, validities, and prices.
- Cascading country → validity → vehicle type selects on the frontend.

### Changed
- `Vintrica_Pricing` and `Vintrica_Security` use catalog data instead of hardcoded prices.

## [1.3.2] and earlier

### Added
- Multi-step vignette order form with custom checkout (no WooCommerce dependency).
- Stripe Checkout integration and webhook handling.
- Admin orders list and order detail view.
- Admin email notifications for new and paid orders.
- Custom Stripe success/cancel redirect URLs.
- Slovak localization throughout the plugin.

### Fixed
- Safari icon rendering and review card layout.
- Field icons and step navigation.
- Stripe checkout nonce verification and initialization.
- PHP parse error in orders class.
- WooCommerce SKU fatal error when WooCommerce is inactive.

---

## Release policy (Feature Freeze)

From **1.6.3** onward the plugin is in **Feature Freeze**. Allowed changes:

- Bug fixes
- Security fixes
- UI improvements
- Performance improvements
- Compatibility fixes
- Email improvements

**Not allowed without explicit approval:**

- Architecture redesign or broad refactors
- Renaming classes, functions, hooks, or database structures
- Changes to Stripe, checkout, order, or webhook flows

Every release must update this changelog and preserve backward compatibility. Prefer extending existing code over rewriting stable logic.
