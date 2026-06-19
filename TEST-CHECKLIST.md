# VINTRICA Vignette Form — Production Test Checklist

Use this checklist after installing `vintrica-vignette-form-1.3.0.zip` on a staging WordPress site.

## Installation

- [ ] Upload and activate the plugin without PHP warnings or fatal errors
- [ ] Confirm **Plugins → VINTRICA Vignette Form** shows version **1.3.0**
- [ ] Confirm **VINTRICA FORM** appears in the admin menu
- [ ] Confirm **Objednávky** and **Stripe** submenus are available

## Shortcode & Assets

- [ ] Create a test page containing `[vintrica_vignette_form]`
- [ ] View the page while logged out and confirm the four-step indicator renders
- [ ] Confirm `assets/css/frontend.css` and `assets/js/frontend.js` are loaded
- [ ] Confirm **jQuery is not** a dependency of `vintrica-frontend`

## Step 1 — Výber známok

- [ ] Validity dropdown is disabled until a country is selected
- [ ] Adding a vignette places it in the summary list
- [ ] Totals update correctly
- [ ] **Pokračovať k fakturačným údajom** stays disabled until at least one vignette exists

## Step 2 — Fakturačné údaje

- [ ] All billing fields and consent checkboxes render
- [ ] **Pokračovať na kontrolu** validates billing client-side
- [ ] **Späť na známky** returns to step 1 without losing data

## Step 3 — Kontrola objednávky

- [ ] All vignettes show country, validity, vehicle type, plate, registration country, start date, and price
- [ ] Service fee and total are shown
- [ ] Billing review shows name, email, phone, company fields, tax IDs, and address
- [ ] **Odstrániť** removes a vignette from review
- [ ] **Upraviť známky** returns to step 1
- [ ] **Späť na fakturačné údaje** returns to step 2

## Step 4 — Stripe platba

- [ ] **Zaplatiť** creates an internal order only after review confirmation
- [ ] With Stripe keys configured, user is redirected to Stripe Checkout
- [ ] Without Stripe keys, order is stored and confirmation message appears
- [ ] Successful return with `?vintrica_paid=1` marks order as **Uhradená**
- [ ] Cancelled return marks order as **Zrušená**

## Admin — Objednávky

- [ ] Orders list shows order number, date, customer name, email, vignette count, total, status badge, and actions
- [ ] Clicking an order opens the detail page
- [ ] Detail page shows billing, vignettes, price breakdown, Stripe session ID, and notes placeholder
- [ ] Status can be changed manually with nonce-protected form
- [ ] Only administrators can access order pages

## Build Verification (Developer)

```bash
php bin/verify.php
bash bin/build.sh
```

Expected ZIP: `dist/vintrica-vignette-form-1.3.0.zip`
