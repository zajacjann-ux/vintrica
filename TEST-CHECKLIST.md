# VINTRICA Vignette Form — Production Test Checklist

Use this checklist after installing `vintrica-vignette-form-1.0.2.zip` on a staging WordPress site with WooCommerce active.

## Installation

- [ ] Upload and activate the plugin without PHP warnings or fatal errors
- [ ] Confirm **Plugins → VINTRICA Vignette Form** shows version **1.0.2**
- [ ] Confirm **VINTRICA FORM** appears in the admin menu
- [ ] Open the admin page and verify the shortcode `[vintrica_vignette_form]` is displayed

## Shortcode & Assets

- [ ] Create a test page containing `[vintrica_vignette_form]`
- [ ] View the page while logged out and confirm the builder renders
- [ ] View page source and confirm `assets/css/frontend.css` is loaded
- [ ] View page source and confirm `assets/js/frontend.js` is loaded
- [ ] Confirm **jQuery is not** a dependency of `vintrica-frontend` in page source
- [ ] Confirm `vintricaConfig` is present in a localized script block

## Builder Behaviour

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
- [ ] **Continue** stays disabled until at least one vignette exists

## Security & Submission

- [ ] Submitting with zero vignettes shows a client-side validation message
- [ ] Submitting a valid order shows a success notice
- [ ] Reloading after success does not resubmit the order (POST-redirect pattern optional; verify no duplicate notices on refresh if applicable)
- [ ] Tampering with hidden JSON is rejected server-side
- [ ] Invalid country / validity combinations are rejected server-side
- [ ] Nonce failure shows a security error message

## Regression Checks

- [ ] Deactivate plugin without errors
- [ ] Reactivate plugin without errors
- [ ] WooCommerce can remain inactive; no dependency errors appear
- [ ] No JavaScript console errors on the form page
- [ ] No PHP notices in `debug.log` when using `WP_DEBUG_LOG`

## WooCommerce Integration

- [ ] Add vignettes and click **Pokračovať k platbe**
- [ ] Confirm redirect to WooCommerce Checkout
- [ ] Confirm each vignette appears as a separate cart line
- [ ] Confirm cart shows ŠPZ, krajina registrácie, dátum, typ vozidla, krajina známky, platnosť
- [ ] Confirm **Servisný poplatok** appears once in cart totals
- [ ] Complete a test order and verify meta in admin order details and customer email

## Build Verification (Developer)

Run before creating a release ZIP:

```bash
php bin/verify.php
bash bin/build.sh
```

Expected result:

- All verification checks pass
- ZIP created at `dist/vintrica-vignette-form-1.0.2.zip`
- ZIP root folder is `vintrica-vignette-form/`
