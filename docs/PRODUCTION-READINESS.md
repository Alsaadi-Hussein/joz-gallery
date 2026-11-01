# Joz Gallery: production readiness report

Audit date: 2026-11-01, with a follow-up security audit on 2026-11-02 (see the end of this report). Everything marked measured or tested was run, on a local bench unless it says otherwise. Nothing has been deployed to a real cPanel host yet.

## Verdict

**Readiness score: 72%. Ready for staging. Not ready for production.**

The code is in good shape: every defect found is fixed and has a test. The remaining 28% is mostly work that can only happen on the real host, plus launch tasks that are yours.

| Area | Weight | Score | Why it is not full marks |
|---|---:|---:|---|
| Application security | 20 | 18 | No two-factor login for the owner. Filament forces a weaker CSP in the admin |
| Dependencies | 5 | 5 | Clean |
| Data integrity under load | 15 | 14 | Proven on MariaDB 13, and the host's database version is unknown |
| Performance and capacity | 15 | 10 | Your 500-user latency target is not met. The real host is unmeasured |
| Host configuration and deployment | 15 | 11 | Tested on Apache with cPanel-style limits, never on real cPanel or LiteSpeed |
| Monitoring and backups | 15 | 9 | The 24-hour trial has not run. Backups stay on the same server |
| Launch prerequisites | 15 | 5 | No host, no Git remote, no real content, email and Telegram unproven |
| **Total** | **100** | **72** | |

### Blockers before going live

1. Commit this work and add a private Git remote. The only copy of the project is on one PC.
2. Confirm the host offers PHP 8.4.1 or newer. The project will not run on anything older.
3. Deploy to staging and run the 24-hour trial in Phase 5.
4. Copy backups off the server. Right now a lost server loses the site and its backups together.
5. Add the real products, delivery fees and page text, then finish the checklist in `DEPLOY.md` section 9.

---

## Phase 1: security and code quality

**Scanners:** `composer audit` and `npm audit` report 0 vulnerabilities. No secrets in tracked files or anywhere in Git history.

**Checked and clean:** SQL injection (3 raw queries, all static or bound), XSS (3 unescaped outputs: two are JSON with `JSON_HEX_TAG`, one is sanitized and tested with live payloads), CSRF (no exclusions), IDOR (signed links, policies, number plus phone for tracking), mass assignment (allow-lists on every model).

All findings below are fixed and have regression tests, unless marked otherwise.

| Severity | File | Line | Finding | Fix |
|---|---|---:|---|---|
| **Critical** | `app/Models/Order.php` | 48 | Simultaneous checkouts of different products failed: 49 of 60 orders returned 500 (deadlocks and MariaDB error 1020) | Counter row taken first in the transaction. Now 0 of 108 fail |
| **High** | `routes/web.php` | 25 | All rate limits shared one counter per IP: 12 search keystrokes blocked order tracking, and browsing could stop checkout totals updating | Each feature has its own counter |
| **High** | `config/livewire.php` | 18 | Uploads sat on the public disk before validation, with their original extension, and could be downloaded | Private disk, images only on arrival |
| **High** | `app/Filament/Exports/OrderExporter.php` | 61 | A name starting with `=` became a live formula in Excel | Risky values are escaped |
| **High** | `app/Filament/Exports/OrderExporter.php` | 77 | `?format=csv` bypassed the xlsx-only rule | Download is always xlsx |
| **High** | `app/Filament/Support/ImageUpload.php` | 15 | A 12 MP phone photo crashed uploads at `memory_limit=128M`, and 54 MP crashed at 256M | Shrunk in the browser to 2400 px, refused above 6000 px |
| **High** | `routes/console.php` | 22 | One killed queue process silenced order alerts for 24 hours | Lock now lasts 2 minutes |
| Medium | `app/Models/Order.php` | 48 | Deleting the newest order reused its number, so an old signed link opened the next customer's details | Numbers never reused |
| Medium | `app/Policies/OrderPolicy.php` | 23 | Deleting an open order lost its stock and coupon use for good | Only finished orders can be deleted |
| Medium | `app/Providers/Filament/AdminPanelProvider.php` | 55 | Staff names were sent to ui-avatars.com on every admin page | Avatar drawn locally |
| Medium | `app/Http/Middleware/SecurityHeaders.php` | 41 | No Content Security Policy | Strict policy on the shop, Filament's minimum on the admin |
| Medium | `storage/app/public/.htaccess` | 15 | An uploaded SVG or HTML file could run as a page | Sandboxed, tested on Apache |
| Medium | `app/Services/Catalog.php` | 46 | The home page ran one category query per section and loaded the whole catalog 3 times | One query, cached ID lists |
| Low | `routes/web.php` | 25 | `/search` had no rate limit | 60 a minute |
| Low | `app/helpers.php` | 26 | `shop_link` passed `javascript:` and `//` links. The banner form already blocked most of these | Only `/` and `http(s)` allowed |
| Low | `config/filesystems.php` | 37 | Laravel registered signed read and write routes for the disk holding backups | Routes removed |
| Low | `.gitignore` | 4 | Only some `.env.*` files were ignored | All except the example |
| Low | `bootstrap/app.php` | 20 | Any Host header was accepted | Only the site's own host |
| Low | `.env.example` | 51 | Sessions held a refused shopper's name and phone unencrypted | `SESSION_ENCRYPT=true` |

**Architecture:** notification calls have 5 second timeouts and run on the queue, and each channel fails on its own. Image conversions are synchronous by your spec, so uploads are the slowest request (about 1.4 s).

### Accepted, or your decision

- **No two-factor login.** Recommended soon, because the owner account can read every customer's details.
- **Staff can export all orders.** It matches what they can already see. Restrict it to the owner if you prefer.
- **Thanks page links never expire.** They are signed and unguessable, and they show the customer's own details.
- **The admin CSP allows inline scripts and `eval`.** Filament requires this. The shop has no such allowance.
- **`TRUSTED_PROXIES=*` is only safe behind Cloudflare with the origin locked down.** Otherwise a visitor can fake their address and dodge rate limits.

---

## Phase 2: storage and memory

**Memory:** peak is 38 to 43 MB per page at 500 products. PHP frees everything after each request, and there are no long-running processes or held connections, so there is nothing to leak. The queue worker exits after 50 seconds at most.

**Redis and Memcached: not implemented.** Your spec excludes them on shared hosting. Instead:

- `app/Services/CatalogCache.php` caches lists of product IDs and search text for 60 seconds, cleared on any edit. It never holds prices or stock, so shoppers always see live figures.
- It uses the site's cache store, so a host with Redis gets it in memory by changing `CACHE_STORE` alone.
- Sessions stay in the database, which is safer than one file per visitor on a host with an inode limit.

| Measured, in process, 500 products | Before | After |
|---|---:|---:|
| Home page | 178 ms | 69 ms |
| Offers | 105 ms | 29 ms |
| Search | 75 ms | 45 ms |
| Search suggestions | 48 ms | 9 ms |

**Uploads, tested with hostile files in production mode:** `shell.php`, `shell.phtml`, `shell.php.jpg`, `photo.jpg.phar` and `.htaccess`, each with JPEG bytes and a PHP payload, were all stored as a random name ending in `.jpg`, chosen from the file's real contents. A 6000×4000 photo reached the server as 2400×1600 and 0.53 MB. Conversions produce WebP.

**Disk clean-up, all on the schedule:**

| Task | When | Why |
|---|---|---|
| `logs:trim` | Hourly | Daily rotation alone is not enough: one run wrote 81 MB in 10 minutes |
| `cache:sweep` | Daily | Laravel never removes expired files: 11,361 appeared in 10 minutes |
| `queue:prune-failed`, `queue:prune-batches` | Daily | Keep 30 days |
| `exports:prune` | Daily | Customer data, keep 30 days |
| `backup:clean` | 01:00 | Keep 7 days |

---

## Phase 3: load testing

**Script:** `tests/load/store.k6.js`. It places real orders, tracks them, and signs in to the admin through Livewire. It refuses to write to any host that is not local or `staging.` unless told otherwise.

**Bench:** Apache 2.4 with 8 PHP workers and OPcache, MariaDB 13, 500 products, 5,000 orders, on a Windows machine with 16 threads. k6 ran on the same machine.

| Stage | p95 | Failed | Failure target (under 1%) | Latency target (under 500 ms) |
|---|---:|---:|---|---|
| Warm-up, 10 users | 135 ms | 0.00% | **Pass** | **Pass** |
| Peak, 100 to 500 users | 4,134 ms | 0.00% | **Pass** | **Fail** |
| Stress, 500 to 1,500 users | 7,764 ms | 67.12% | Fail, expected | Fail, expected |

| Concurrent users | 8 workers, 16 threads | PHP pinned to 2 cores |
|---:|---:|---:|
| 50 | not measured separately | **395 ms** |
| 60 | not measured separately | 823 ms |
| 100 | **99 ms** | 2,825 ms |
| 200 | **433 ms** | 10,260 ms |
| 300 | 3,145 ms | 14,146 ms |
| 500 | 5,729 ms | 26,457 ms |
| 600 | 7,278 ms | not tested |
| 700 and up | 13% or more refused | not tested |

**Breaking points**

- **8 workers:** latency passes 500 ms between 200 and 300 users, at about 96 requests a second. Failures start around 700 users.
- **2 cores, the better guide for shared hosting:** latency passes 500 ms between 50 and 60 users, at about 30 requests a second. It never failed: 0.00% errors and every flow completed at 500 users, just slowly.

**What this means.** Your 500-user target is not met, and that is not a bug. It is a limit of how many PHP workers fit on the machine. These runs handled about 15 requests a second per core, so holding 500 active users under 500 ms would need roughly 12 to 16 dedicated cores, by my estimate. 50 people clicking at the same moment is a lot for a furniture shop, so shared hosting should be enough to launch. If traffic outgrows that, move to a VPS.

**Under the heaviest load,** 2,434 test orders left 0 duplicate numbers, 0 orders without items and 0 failed jobs, and the Laravel error log stayed empty.

**Limits of these numbers.** Windows PHP and a local database are not your host. The bench used persistent database connections, because Windows runs out of TCP ports at about 130 new connections a second and a Linux host has no such limit. The refused connections at 700 users are partly Apache on Windows. Run the test on staging for the figures that count.

---

## Phase 4: cPanel files

| File | What it does | Tested |
|---|---|---|
| `public/.htaccess` | HTTPS, headers, blocks, compression, caching | 80 checks on Apache 2.4 with cPanel-style `AllowOverride`, with real decoy files in the web root |
| `.htaccess` | 404s everything if the document root is set wrongly | 11 sensitive paths on a misconfigured root |
| `storage/app/public/.htaccess` | No scripts, sandboxed uploads | Included in the 80 checks |
| `public/.user.ini` | PHP limits where the host uses PHP-FPM | Not tested |
| `.cpanel.yml` | The "Deploy HEAD Commit" button | Not tested: needs real cPanel |
| `scripts/deploy.sh` | The deployment itself | 32 checks on a real run |

Compression took the home page from 61 KB to 9 KB with gzip and 8 KB with Brotli.

**Two deliberate differences from your request:**

- **"Compile assets" is skipped.** The host has no Node.js, so `public/build` is built on your computer and committed. The script stops if it is missing.
- **"Reload processes" is not needed.** There are no workers to restart. Cron starts a fresh queue run every minute, and the script still signals `queue:restart`.

**`deploy.sh` was tested** on a real clone with a real Composer install, database backup and migrations. It never touches `.env` or `storage/`, removes files deleted from Git, and brings the site back up after a failed migration. It refuses `APP_DEBUG=true`, a placeholder or empty `APP_URL` or `MAIL_FROM_ADDRESS`, a second deployment running at once, and a missing `.env`.

---

## Phase 5: health and monitoring

**`GET /api/health`** answers `{"status":"ok"}` or `503 {"status":"down"}` to anyone. With the `X-Health-Token` header it adds database latency, memory current and peak, disk space, uptime since the last deployment, cron heartbeat, queue, and backup age. It reports `degraded` if cron stops or the disk passes 90%. Failure details never reach the public.

**`scripts/monitor.sh`** checks 13 URLs, including that `.env` and `.git` stay closed. Latency goes to `staging-latency.csv` and problems to `staging-audit.log`.

It was tested against real failures: it caught a real `.env` exposed in the web root, a slow page, the site down, and cron stopped.

**The 24-hour trial has not been run.** It needs a staging site.

---

## Phase 6: verification

### Run on your computer

Automated tests, expect 636 passed:

```bash
php artisan test --compact
```

Dependency scans:

```bash
composer audit
```

```bash
npm audit
```

Code style:

```bash
vendor/bin/pint --test
```

Start the local bench, then run the staged load test (about 10 minutes):

```bash
powershell -ExecutionPolicy Bypass -File C:\dev\tools\bench-start.ps1
```

```bash
C:\dev\tools\k6\k6-v2.3.0-windows-amd64\k6.exe run -e BASE_URL=http://127.0.0.1:8080 -e PROFILE=full -e ALLOW_WRITES=1 -e SPOOF_IPS=1 tests/load/store.k6.js
```

Find the limit of a small server, with PHP pinned to 2 cores (about 7 minutes):

```bash
powershell -ExecutionPolicy Bypass -File C:\dev\tools\bench-start.ps1 -Cores 2
```

```bash
C:\dev\tools\k6\k6-v2.3.0-windows-amd64\k6.exe run -e BASE_URL=http://127.0.0.1:8080 -e PROFILE=capacity -e STEPS=20,30,40,50,60,80 -e BUCKET=10 -e ALLOW_WRITES=1 -e SPOOF_IPS=1 tests/load/store.k6.js
```

Stop the bench:

```bash
powershell -ExecutionPolicy Bypass -File C:\dev\tools\bench-start.ps1 -Stop
```

### Run against staging

Replace the address with yours. Each check is listed with the answer to expect.

http redirects to https, expect `301`:

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://staging.example.com/
```

Secrets stay closed, expect `404`:

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://staging.example.com/.env
```

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://staging.example.com/.git/config
```

Security headers are present:

```bash
curl -sI https://staging.example.com/ | grep -iE 'strict-transport|content-security|x-frame|x-content-type'
```

Compression works, expect `br` or `gzip`:

```bash
curl -s -o /dev/null -D - -H 'Accept-Encoding: br, gzip' https://staging.example.com/ | grep -i content-encoding
```

TRACE is off, which `.htaccess` cannot control, expect `403` or `405`:

```bash
curl -s -o /dev/null -w '%{http_code}\n' -X TRACE https://staging.example.com/
```

Health figures, expect `"status":"ok"`:

```bash
curl -H "X-Health-Token: YOUR_TOKEN" https://staging.example.com/api/health
```

Load test, read only, from another machine:

```bash
k6 run -e BASE_URL=https://staging.example.com -e PROFILE=smoke tests/load/store.k6.js
```

24-hour trial, from a machine other than the server:

```bash
MONITOR_URL=https://staging.example.com MONITOR_TOKEN=YOUR_TOKEN bash scripts/monitor.sh --loop
```

Trial summary:

```bash
bash scripts/monitor.sh --report
```

### Confirmation checklist

**Done and verified**

- [x] 636 automated tests pass, 1,889 assertions
- [x] Dependency scans and secret scan clean
- [x] Every finding fixed, with a regression test
- [x] Concurrent checkouts: 0 of 108 fail, all numbers unique
- [x] Failure rate under 1% up to 600 users on the bench
- [x] p95 under 500 ms up to 200 users on 8 workers, and 50 users on 2 cores
- [x] 80 Apache checks pass under cPanel-style limits
- [x] 32 deployment checks pass on a real run
- [x] Health endpoint and monitor proven against real failures
- [x] CSP clean on 15 page types in a real browser, with a real image upload

**Not met**

- [ ] p95 under 500 ms at 500 users. Needs more CPU than shared hosting offers

**Cannot be verified until a host exists**

- [ ] PHP 8.4.1 or newer, and the host's MariaDB or MySQL version
- [ ] `.htaccess` on the real server, especially if it runs LiteSpeed
- [ ] `.cpanel.yml` through Git Version Control
- [ ] OPcache enabled, and `memory_limit` at 256M
- [ ] Cron, AutoSSL renewal, and backups working on Linux
- [ ] Real SMTP email, Telegram alerts and the WhatsApp preview
- [ ] 24-hour staging trial passes with no errors
- [ ] k6 smoke test on staging

**Yours to do**

- [ ] Commit, and add a private Git remote
- [ ] Off-site backups, then test one restore
- [ ] Real products, delivery fees and page text
- [ ] Real wording for the About, Contact, Delivery, Returns and Privacy pages
- [ ] Confirm the store phone, WhatsApp number and Instagram in Settings
- [ ] Remove the demo `JOZ10` coupon if demo data is loaded
- [ ] Decide on two-factor login and who may export orders

**Is it 100% ready for production? No.** It is ready for staging today. Move to production once the "cannot be verified" list passes on the real host.

---

## Follow-up security audit, 2026-11-02

**Result: 0 critical issues and no exposed secrets.** A scan of 327 tracked files and all of Git history found no credentials, and the browser bundle holds no environment values or source maps. The readiness score stays at 72%, because the open items are still host and content work.

The first audit missed the crash below, because it never sent array-shaped input or followed the redirect afterwards. These were all fixed, and each has a test unless it says otherwise.

| Severity | Finding | Fix |
|---|---|---|
| Warning | 14 kinds of malformed request crashed with a 500 error. A crafted field such as `name[]=x` also crashed the form page that came back | Arrays are treated as empty fields, and category filters come from the checked values. 22 hostile requests on the bench: 0 server errors |
| Warning | `SEED_DEMO` defaulted to on, which could add demo data and the working `JOZ10` coupon | Off unless asked for |
| Warning | `.gitignore` allowed database dumps, keys and archives | Now ignored |
| Warning | `/api/health` allowed any website to read it, which is Laravel's default | `config/cors.php` allows no site |
| Warning | Customer details stayed in the browser for ever | Removed after 30 days. Checked in a real browser, not by an automated test |
| Warning | Home page text implied stock photos were customers' homes, and claimed best sellers before any sales | Reworded in both languages |
| Cleanup | 14 external links had no `noreferrer` | Added |
| Cleanup | 107 optional settings were missing from `.env.example` | Added, commented out |
| Cleanup | Cart HTML printed server integers unescaped. Not exploitable | Escaped |
| Cleanup | `DEPLOY.md` printed a literal example password | Replaced with a hidden prompt |
| Cleanup | Placeholder addresses could reach production | `deploy.sh` refuses them |
| Cleanup | `07701234567` could be a real person's number | Shown as `07XX XXX XXXX` |
| Cleanup | The README was Laravel's default | Replaced |

**Stored XSS:** an order with script payloads in every field stayed escaped on the thanks page, tracking page, invoice and order email. In a real browser, an order with a script in its notes placed cleanly with no console errors. The admin order screens were not rendered in this test, but their code has no raw HTML output.

**Still open, and yours to supply:**

- The wording for the 5 public pages, which still show placeholder text. See `database/seeders/PageSeeder.php`.
- The real delivery fees. `database/seeders/GovernorateSeeder.php` seeds 5,000 and 8,000 dinars as placeholders.
- Real product text and photos to replace the demo content, including `public/assets/img/demo/`.
- Whether the seeded store phone, WhatsApp number and Instagram are correct.
- Whether to keep `AGENTS.md` and `CLAUDE.md`.
