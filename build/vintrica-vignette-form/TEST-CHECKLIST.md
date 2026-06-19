# VINTRICA Vignette Form — Production Test Checklist

Use this checklist after installing `vintrica-vignette-form-1.3.2.zip` on a staging WordPress site.

## Stripe Configuration

- [ ] Configure **Secret Key**, **Publishable Key**, and **Stripe Webhook Secret** under **VINTRICA FORM → Stripe**
- [ ] Copy webhook URL shown in admin to Stripe Dashboard
- [ ] Subscribe webhook to `checkout.session.completed`, `checkout.session.expired`, and `payment_intent.payment_failed`

## Checkout Flow

- [ ] Step 3 **Zaplatiť** does not reload the page
- [ ] Valid order redirects to Stripe Checkout when Secret Key is configured
- [ ] Missing Secret Key shows Slovak error on step 3
- [ ] Stripe API failure shows safe Slovak error (details only in debug log)

## Webhook

- [ ] Completed Stripe payment marks order as **Uhradená**
- [ ] `paid_at` and payment intent ID are stored
- [ ] Expired session marks order as **Zrušená**
- [ ] Failed payment keeps or sets order as **Neuhradená**

## Build

```bash
php bin/verify.php
bash bin/build.sh
```

Expected ZIP: `dist/vintrica-vignette-form-1.3.2.zip`
