# Joz Gallery for Home

The online store for Joz Gallery (جوز كاليري للمنزل), a furniture and home decor shop in Iraq. Shoppers browse in Arabic or English and pay cash on delivery, with no customer accounts. Staff run the store from an Arabic admin panel.

Built with Laravel 13, Filament 5 and MariaDB, for shared cPanel hosting. It needs no Redis, no background workers and no Node.js on the server.

## Run it locally

You need PHP 8.4.1 or newer, Composer, Node.js and MariaDB.

```bash
composer install
```

```bash
npm ci
```

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

Fill in the database and `ADMIN_` settings in `.env`, and set `SEED_DEMO=true` if you want the demo catalog. Then:

```bash
php artisan migrate --seed
```

```bash
npm run build
```

```bash
php artisan serve
```

The shop opens at `http://127.0.0.1:8000`, and the admin panel at `/admin`.

## Before you commit

Run the tests and the style check:

```bash
php artisan test --compact
```

```bash
vendor/bin/pint --test
```

The server has no Node.js, so `public/build` is committed. Run `npm run build` after any change to `resources/js` or `resources/css`, and commit the result with it.

## Documents

- [DEPLOY.md](DEPLOY.md): how to put the site on cPanel, in Arabic.
- [docs/PRODUCTION-READINESS.md](docs/PRODUCTION-READINESS.md): the security audit, load test results and launch checklist.
