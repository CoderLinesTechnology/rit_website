# Online donations — integration guide

**Status: not configured.** No payment provider has been selected. With `PAYMENT_PROVIDER=none` the
donation page shows the administrator's notice (Settings › Donations) and optional offline giving
instructions. No payment form is displayed, nothing can be charged, and the webhook URL answers 404.

## How the flow works once a provider is added

```
Donor ─▶ POST /donate (amount, optional name/email)
          │  DonationService::start(): row in `donations` (status initiated → pending)
          ▼
        Provider's hosted checkout page (card / mobile money entered THERE, never on our site)
          │
          ├─▶ GET /donate/callback?reference=RIT-DON-…   (donor's browser returns)
          │      DonationService::confirm(): gateway->verify() asks the provider's API
          │
          └─▶ POST /webhooks/payments/{provider}          (provider's server)
                 DonationService::webhook(): signature check → event stored once
                 (payment_events UNIQUE provider+event_id) → gateway->verify() → status update
```

A donation is shown as **successful only** after `verify()` (a server-to-server call to the
provider) reports success for the **same amount and currency** we created. A browser redirect or
a webhook body alone never marks a donation as paid. Final states cannot be downgraded by a late or
replayed event (`Donation::applyVerifiedStatus`).

## Adding a provider (developer task)

1. Choose a provider that supports the Foundation's needs (e.g. Ghanaian cards and mobile money),
   with a hosted checkout and signed webhooks. Read its official API documentation.
2. Create `app/Services/Payments/<Name>Gateway.php` implementing `PaymentGateway`:
   - `name()`: short key, e.g. `examplepay`. It is the `{provider}` in the webhook URL.
   - `isConfigured()`: true only when `config('payments.secret_key')` (and the webhook secret, if
     the provider uses a separate one) are non-null. `Env::secret()` already treats blanks and
     placeholders such as `REPLACE_ME` as missing.
   - `initialize($donation, $callbackUrl)`: call the provider's "create transaction" endpoint over
     HTTPS with the secret key in a header, amount in minor units, currency `GHS`, our
     `reference` as the provider reference/metadata, and the callback URL. Return the provider's
     `https://` checkout URL.
   - `verify($donation)`: call the provider's "verify transaction" endpoint and map its status to
     `VerificationResult` (successful / failed / abandoned / pending / refunded) with the amount
     and currency **as reported by the provider**.
   - `parseWebhook($raw, $headers)`: compute the provider's signature (usually HMAC-SHA256/512 of
     the raw body with the secret) and compare it with `hash_equals()`; return `null` on mismatch.
     Return the provider's unique event id and our donation reference.
   - Use cURL with TLS verification on (the default), a timeout of 10–15 s, and never log keys,
     full payloads or donor details.
3. Register it in `GatewayFactory::PROVIDERS`.
4. Set in `.env`: `PAYMENT_PROVIDER=<key>`, `PAYMENT_PUBLIC_KEY`, `PAYMENT_SECRET_KEY`,
   `PAYMENT_WEBHOOK_SECRET` (if separate). Secret keys stay on the server; nothing secret is ever
   sent to the browser.
5. In the provider's dashboard, set the webhook URL to
   `https://www.ritfoundationglobal.com/webhooks/payments/<key>` and the callback URL to
   `https://www.ritfoundationglobal.com/donate/callback`.
6. Test in the provider's **test mode** first: a successful payment, a failed one, an abandoned
   checkout, a replayed webhook (must be ignored), a webhook with a wrong signature (must get 401),
   and an amount mismatch (must not be marked successful). `tests/Unit/DonationServiceTest.php`
   shows the expected behaviour with a fake gateway.
7. Update the privacy notice to name the provider as a processor, and the compliance register.

## What is stored

`donations`: our reference, provider name, provider reference, amount (pesewas), currency, optional
donor name/email, anonymous flag, status, provider status text, verification time.
`payment_events`: provider event id, type, signature result, SHA-256 of the payload (not the
payload), processed time. **No card numbers, CVVs, PINs, wallet credentials or raw payloads.**
