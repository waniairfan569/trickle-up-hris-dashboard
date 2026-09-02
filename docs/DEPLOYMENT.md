# Deployment & Go-Live Runbook

Everything needed to deploy Trickle Hub and switch on the SaaS features. All the
paid/third-party integrations are **guarded** — the app runs fine before you set
them, so you can go live in stages.

---

## 1. Deploying

### Option A — traditional host (Plesk / VPS) — recommended for now
On the server, in the project root:

```bash
./deploy.sh
```

`deploy.sh` puts the app in maintenance mode, pulls `main`, runs
`composer install --no-dev`, migrates, rebuilds the production caches, restarts
the queue worker, and lifts maintenance mode again (even if a step fails).

First-time setup on the server:

```bash
cp .env.example .env      # then fill it in (see §3)
php artisan key:generate
chmod +x deploy.sh
```

### Option B — containerised (staging / future)
A production image (nginx + php-fpm + queue worker + scheduler) and a prod-like
stack are provided:

```bash
docker compose up -d --build      # app on http://localhost:8080 + MySQL + Redis
```

Build just the image: `docker build -f docker/Dockerfile -t trickle-hub .`

### Option C — automated (CD)
`.github/workflows/deploy.yml` deploys over SSH on a version tag
(`git tag v1.0.0 && git push --tags`) or manually. Add the repo secrets
`SSH_HOST`, `SSH_USER`, `SSH_KEY`, `DEPLOY_PATH` first. It just runs `deploy.sh`
on the server.

---

## 2. Background processing (required)

Two long-running things must be set up or scheduled jobs and queued work silently
stop:

- **Scheduler** — a cron entry, or the container's `schedule:work`:
  ```
  * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
  ```
  Drives: daily backups, `subscriptions:check-trials`, the `/health` scheduler
  heartbeat, attendance reports, reminders.
- **Queue worker** — `php artisan queue:work` under Supervisor (or Horizon on
  Redis). The Docker image runs this for you.

Confirm both are healthy on the **`/health`** page (scheduler shows *Operational*
once the heartbeat runs) and the public **`/status`** page.

---

## 3. Environment / go-live checklist

Set these in `.env` (all optional except the first block):

| Area | Keys | Notes |
|------|------|-------|
| **Core** | `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`, `DB_*` | Required. |
| **Payments** | `STRIPE_KEY/SECRET/WEBHOOK_SECRET` | `composer require stripe/stripe-php`, add a webhook to `/stripe/webhook`, set each plan's Stripe price id in the operator console. |
| **Email** | `MAIL_MAILER` + provider creds | Use SES/Postmark/Resend with SPF/DKIM/DMARC. Test: `php artisan mail:test you@domain.com`. |
| **File storage** | `FILESYSTEM_DISK=s3` + `AWS_*` | `composer require league/flysystem-aws-s3-v3`, then `php artisan storage:test s3`. |
| **Captcha** | `TURNSTILE_SITE_KEY/SECRET` | Cloudflare Turnstile on signup. |
| **Backups** | `BACKUP_UPLOAD_DISK=s3`, `BACKUP_KEEP_DAYS` | Off-site backups. |
| **Legal** | `LEGAL_COMPANY/ENTITY/CONTACT_EMAIL/JURISDICTION`, `LEGAL_IS_TEMPLATE=false` | Have the templated ToS/Privacy/DPA reviewed by counsel first. |
| **Scaling** | `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`, `REDIS_*` | Config-only switch once Redis is available. |
| **CDN** | `ASSET_URL` | Serve compiled assets from a CDN. |
| **Errors** | `SENTRY_LARAVEL_DSN` | `composer require sentry/sentry-laravel` — auto-registers. |

---

## 4. Go-live hardening

- `APP_DEBUG=false` and `APP_ENV=production`.
- HTTPS enforced (the app's `ForceHttps` respects the reverse proxy) + HSTS.
- Config/route/view **cached** (`deploy.sh` does this).
- Secrets only in `.env` (never committed); rotate the `APP_KEY` only with care.
- Point an **uptime monitor** at `/health` and alert on non-200.
- Take one **restore test** from a `backup:run` archive.

---

## 5. Operator provisioning

Create the platform owner (company-less):

```bash
php artisan operator:create you@yourco.com --name="Your Name" --role=owner
```

Then sign in and manage plans, subscriptions and companies from the Operator
Console.
