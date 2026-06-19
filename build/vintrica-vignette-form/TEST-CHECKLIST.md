# VINTRICA Vignette Form — Production Test Checklist

Use this checklist after installing `vintrica-vignette-form-1.1.0.zip` on a staging WordPress site.

## Installation

- [ ] Upload and activate the plugin without PHP warnings or fatal errors
- [ ] Confirm **Plugins → VINTRICA Vignette Form** shows version **1.1.0**
- [ ] Confirm **VINTRICA FORM** appears in the admin menu
- [ ] Open the admin page and verify the shortcode `[vintrica_vignette_form]` is displayed
- [ ] Confirm **Objednávky** submenu lists stored orders

## Shortcode & Assets

- [ ] Create a test page containing `[vintrica_vignette_form]`
- [ ] View the page while logged out and confirm the two-step builder renders
- [ ] View page source and confirm `assets/css/frontend.css` is loaded
- [ ] View page source and confirm `assets/js/frontend.js` is loaded
- [ ] Confirm **jQuery is not** a dependency of `vintrica-frontend` in page source
- [ ] Confirm `vintricaConfig` is present in a localized script block

## Step 1 — Vignette Builder

- [ ] Validity dropdown is disabled until a country is selected
- [ ] Changing country updates validity options and prices
- [ ] Adding a vignette places it in the summary list
- [ ] Summary shows correct vignette count
- [ ] Subtotal matches server-defined prices
- [ ] Service fee appears when at least one vignette is added
- [ ] Total equals subtotal + service fee
- [ ] **Edit** loads the vignette back into the form
- [ ] **Update vignette** saves changes to the summary
- [ ] **Cancel edit** restores add mode without corrupting the list
- [ ] **Remove** deletes the selected vignette and updates totals
- [ ] **Pokračovať k platbe** stays disabled until at least one vignette exists
- [ ] Clicking **Pokračovať k platbe** advances to step 2 without a page reload

## Step 2 — Billing & Order

- [ ] Billing form shows all required fields (Meno, Priezvisko, E-mail, Telefón, Ulica, Mesto, PSČ, Krajina)
- [ ] Optional fields (Firma, IČO, DIČ, IČ DPH) accept input
- [ ] All three consent checkboxes are required
- [ ] **Späť na známky** returns to step 1 without losing vignettes
- [ ] Submitting with missing billing data shows a client-side validation message
- [ ] Submitting a valid order redirects with `?vintrica_order=` and shows a success notice
- [ ] Order appears in **VINTRICA FORM → Objednávky** with correct total and e-mail
- [ ] Reloading after success does not resubmit the order

## Security & Validation

- [ ] Tampering with hidden JSON is rejected server-side
- [ ] Invalid country / validity combinations are rejected server-side
- [ ] Nonce failure shows a security error message
- [ ] Invalid e-mail is rejected server-side

## Regression Checks

- [ ] Deactivate plugin without errors
- [ ] Reactivate plugin without errors
- [ ] WooCommerce is **not** required; plugin works without it
- [ ] No JavaScript console errors on the form page
- [ ] No PHP notices in `debug.log` when using `WP_DEBUG_LOG`

## Stripe (Prepared, Not Live)

- [ ] Order is stored with status `pending_payment`
- [ ] Stripe session payload is prepared in the database (no live payment redirect yet)

## Build Verification (Developer)

Run before creating a release ZIP:

```bash
php bin/verify.php
bash bin/build.sh
```

Expected result:

- All verification checks pass
- ZIP created at `dist/vintrica-vignette-form-1.1.0.zip`
- ZIP root folder is `vintrica-vignette-form/`
