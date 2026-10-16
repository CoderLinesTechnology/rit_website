# Compliance register — RIT Foundation Ghana website

Engineering register of the obligations that apply to this website and where the code meets them.
It is **not legal advice**; items marked **Decision needed** must be confirmed by the Foundation
(or its adviser) before launch.

## What the site does

- Public information about a Ghanaian nonprofit (RIT Foundation Ghana LBG, reg. no. CG173310421).
- Collects personal data through forms: training applications (applicants may be **under 18**),
  volunteer applications, partnership enquiries, contact messages, and later donations.
- Staff administer the data in a password-protected dashboard.
- No analytics, advertising, tracking pixels or third-party scripts. Fonts and icons are self-hosted.

## Obligations

| Law / standard | Requirement | Where it is met in the code |
|---|---|---|
| **Ghana Data Protection Act, 2012 (Act 843)**: lawful processing, purpose limitation, minimality | Collect only what is needed for each form; state the purpose | Form fields limited to what's needed (`app/Controllers/Site/*Controller.php`); age **range**, not date of birth; region, not full address; staff notification emails carry no personal details (`app/Services/Notifier.php`) |
| Act 843: consent / transparency | Privacy notice at the point of collection; explicit acknowledgement | Privacy checkbox (`privacy_accepted_at`) on every form; `/privacy` page from setting `privacy.policy` |
| Act 843: children's data | Parent/guardian consent for applicants under 18 | Age-range select; under-18 applicants must give a guardian name, phone and consent (`training_applications.guardian_*`) |
| Act 843: security safeguards | Appropriate technical measures | Hashed passwords, CSRF, prepared statements, output escaping, role permissions, private attachments outside the web root, HTTPS/HSTS, security headers, rate limits, audit log (see README "Security") |
| Act 843: retention & data-subject rights (access, correction, deletion) | Keep data no longer than necessary; be able to delete | `scripts/purge-old-records.php` (default 24 months, `RETENTION_*`); admins can delete a submission and its attachment; delete covers `email_outbox` rows |
| Act 843: registration with the Data Protection Commission | Data controllers must register with the DPC | **Confirmed by the Foundation (2026-10-16):** registered with the Data Protection Commission. Keep the registration renewed |
| Act 843 / ePrivacy practice: cookies | Only strictly necessary cookies without consent | One session cookie, set only on pages with forms and in the admin area (`app/Core/Session.php`, lazy start); no banner needed while this stays true |
| **Processors / data flows** | Declare every third party that receives personal data | Hosting provider; email provider (SMTP or the host's `mail()`); Google Maps only if `contact.map_embed_url` is set; a payment provider once chosen. **Decision needed:** confirm the processors and name them in the privacy notice |
| **Photographs of identifiable people** (Act 843 + image rights) | Permission before publishing photos, names or stories of participants, especially minors | `consent_confirmed` on gallery images, news (success stories) and team members; the importer keeps photos unpublished without `--consent-confirmed`; image re-encoding strips GPS/EXIF data. **Confirmed by the Foundation (2026-10-16):** the photographer's licence and the participants' consent (parents/guardians for minors) are in place for the supplied Tech Skills Challenge photos. New photos still need the consent tick per photo |
| **PCI DSS** (payments) | Never handle card data; hosted checkout only | No card, CVV, PIN or wallet fields anywhere; `donations` stores reference/amount/status only; gateway interface for a hosted checkout with server-side verification and signed webhooks (`app/Services/Payments/`) |
| Payment provider terms / AML | Verify transactions with the provider's official API; idempotent webhooks | Not active: no provider selected (`PAYMENT_PROVIDER=none`). **Decision needed:** choose a provider |
| **WCAG 2.2 AA** | Accessible public pages and forms | Semantic landmarks, skip link, labels, error summary, `aria-invalid`/`aria-describedby`, focus styles, contrast-checked tokens, reduced motion, keyboard lightbox, no-JS fallbacks |
| **OWASP ASVS L2** (security baseline) | Authentication, session, access control, validation, output encoding, file upload, logging | `app/Core/Auth.php`, `Session.php`, `Csrf.php`, `Validator.php`, `RateLimiter.php`, `app/Services/ImageUploader.php`, `DocumentStore.php`, `AuditLog.php` |
| Consumer protection / honesty | Do not misrepresent: placements, donations, email delivery | Confirmation text says submitting does not guarantee a place; donation page shows an admin notice while payments are off; email status is "handed off", never "delivered" |
| Legal pages | Privacy notice and terms | Drafts in `config/settings.php` (`privacy.policy`, `terms.content`). **Confirmed by the Foundation (2026-10-16):** both reviewed. The "reviewed" ticks in Settings › Privacy & terms only clear the dashboard reminder; the pages show the current text either way |

## Checks for every future change

1. New field or table holding personal data? Justify the need; add it to the privacy notice, the
   retention purge and the delete path.
2. New outbound data flow (an API, an embed, a CDN)? Add it to "Processors" above and to the privacy notice.
3. New public UI? Labels, contrast, keyboard, no-JS behaviour.
4. New admin action on private data? Permission check, audit log entry, no personal data in the log summary.
