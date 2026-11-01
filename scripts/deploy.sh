#!/bin/bash
#
# Deploys the commit checked out in this repository to the live folder.
#
#   bash scripts/deploy.sh [folder]        The folder defaults to ~/joz.
#
# Run by .cpanel.yml, or by hand over SSH. Settings, all optional:
#   PHP_BIN=/opt/cpanel/ea-php84/root/usr/bin/php    Which PHP to use.
#   COMPOSER_BIN=/opt/cpanel/composer/bin/composer   Which Composer to use.
#   SKIP_BACKUP=1     Skip the database backup taken before migrating.
#   SKIP_COMPOSER=1   Keep the current vendor folder.
#
# It never touches .env or storage/, which hold the settings, the product images
# and the backups. Assets are not compiled here: the host has no Node.js, so
# public/build is built on your computer and committed. Nothing needs restarting
# either, because there are no workers: cron starts a fresh queue run every minute.

set -euo pipefail

SOURCE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET="${1:-$HOME/joz}"
MIN_PHP=80401
MIN_FREE_MB=300
MANIFEST=.deployed-files
LOCK="$TARGET/storage/framework/deploy.lock"

say() { printf '\n==> %s\n' "$1"; }
stop() { printf '\nDEPLOY STOPPED: %s\n' "$1" >&2; exit 1; }

find_php() {
    local candidate

    for candidate in "${PHP_BIN:-}" /opt/cpanel/ea-php84/root/usr/bin/php /opt/alt/php84/usr/bin/php /usr/local/bin/ea-php84 php; do
        if [ -n "$candidate" ] && command -v "$candidate" >/dev/null 2>&1; then
            printf '%s' "$candidate"
            return 0
        fi
    done

    return 1
}

find_composer() {
    local candidate

    for candidate in "${COMPOSER_BIN:-}" /opt/cpanel/composer/bin/composer /usr/local/bin/composer composer; do
        if [ -n "$candidate" ] && command -v "$candidate" >/dev/null 2>&1; then
            printf '%s' "$candidate"
            return 0
        fi
    done

    return 1
}

env_value() {
    sed -n "s/^$1=//p" "$TARGET/.env" | tail -n 1 | tr -d '"\r'
}

artisan() {
    "$PHP" "$TARGET/artisan" "$@" --no-interaction
}

in_maintenance=0
locked=0

finish() {
    local code=$?

    if [ "$in_maintenance" = 1 ]; then
        printf '\nBringing the site back up after the failure above.\n' >&2
        "$PHP" "$TARGET/artisan" up --no-interaction || true
    fi

    if [ "$locked" = 1 ]; then
        rmdir "$LOCK" 2>/dev/null || true
    fi

    exit "$code"
}

trap finish EXIT

say "Checking before anything changes"

PHP="$(find_php)" || stop "PHP was not found. Set PHP_BIN."
php_id="$("$PHP" -r 'echo PHP_VERSION_ID;')"
[ "$php_id" -ge "$MIN_PHP" ] || stop "PHP 8.4.1 or newer is needed. $PHP is $("$PHP" -r 'echo PHP_VERSION;'). Set PHP_BIN."

[ -d "$SOURCE/.git" ] || stop "$SOURCE is not a Git repository."
[ -f "$TARGET/.env" ] || stop "$TARGET/.env is missing. Do the first install in DEPLOY.md, then deploy."
[ "$(git -C "$SOURCE" ls-files public/build/manifest.json)" = "public/build/manifest.json" ] \
    || stop "public/build is not committed. Run npm run build on your computer and commit it."

[ "$(env_value APP_DEBUG)" = "false" ] || stop "APP_DEBUG must be false in $TARGET/.env."
[ "$(env_value APP_ENV)" = "production" ] || stop "APP_ENV must be production in $TARGET/.env."

# Left as they are, links in emails and customer mail would point at the placeholders.
case "$(env_value APP_URL)" in
    "" | *example.com* | *example.org*) stop "Set APP_URL in $TARGET/.env to the site's real address." ;;
esac

case "$(env_value MAIL_FROM_ADDRESS)" in
    "" | *example.com* | *example.org*) stop "Set MAIL_FROM_ADDRESS in $TARGET/.env to a real address." ;;
esac

free_mb="$(df -Pm "$TARGET" | awk 'NR==2 {print $4}')"
[ "${free_mb:-0}" -ge "$MIN_FREE_MB" ] || stop "Only ${free_mb:-0} MB of disk is free. At least $MIN_FREE_MB MB is needed."

mkdir -p "$(dirname "$LOCK")"
mkdir "$LOCK" 2>/dev/null || stop "Another deployment is running. If it crashed, delete $LOCK."
locked=1

revision="$(git -C "$SOURCE" rev-parse --short=12 HEAD)"
previous="$(cat "$TARGET/REVISION" 2>/dev/null || echo none)"
say "Deploying $revision over $previous"

# A first deployment has no packages yet, so artisan cannot run until Composer has.
installed=0
[ -f "$TARGET/artisan" ] && [ -f "$TARGET/vendor/autoload.php" ] && installed=1

if [ "${SKIP_BACKUP:-0}" != 1 ] && [ "$installed" = 1 ]; then
    say "Backing up the database"
    artisan backup:run --only-db --disable-notifications || stop "The backup failed, so nothing was changed. Fix it, or set SKIP_BACKUP=1."
fi

if [ "$installed" = 1 ]; then
    say "Maintenance mode"
    artisan down --retry=30
    in_maintenance=1
fi

say "Copying files"

new_manifest="$(mktemp)"
git -C "$SOURCE" ls-files | LC_ALL=C sort > "$new_manifest"

# Files deleted in Git are removed here too, and only those. A list is kept, rather
# than mirroring the folder, because anything Git never tracked must survive.
if [ -f "$TARGET/$MANIFEST" ]; then
    LC_ALL=C sort "$TARGET/$MANIFEST" | LC_ALL=C comm -23 - "$new_manifest" | while IFS= read -r stale; do
        rm -f -- "$TARGET/$stale"
    done
fi

tar_options=(--keep-directory-symlink)

# The fallback install in DEPLOY.md has a hand-edited index.php in public_html.
if [ -L "$TARGET/public" ]; then
    tar_options+=(--exclude=public/index.php)
fi

git -C "$SOURCE" archive --format=tar HEAD | tar -x -C "$TARGET" "${tar_options[@]}"
mv "$new_manifest" "$TARGET/$MANIFEST"

if [ "${SKIP_COMPOSER:-0}" != 1 ]; then
    say "Installing production packages"
    COMPOSER="$(find_composer)" || stop "Composer was not found. Set COMPOSER_BIN."

    # Composer is often a shell wrapper here, so it cannot be handed to PHP as a file.
    # Putting the right PHP first means it runs on 8.4 whatever the system default is.
    (cd "$TARGET" && PATH="$(dirname "$(command -v "$PHP")"):$PATH" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist)
fi

say "Updating the database"
artisan migrate --force

say "Rebuilding caches"
artisan optimize:clear
artisan filament:assets
artisan optimize
artisan filament:optimize

if [ ! -e "$TARGET/public/storage" ] && [ -z "$(env_value PUBLIC_DISK_ROOT)" ]; then
    artisan storage:link
fi

artisan sitemap:generate
artisan queue:restart

printf '%s\n' "$revision" > "$TARGET/REVISION"

say "Back online"
artisan up
in_maintenance=0

url="$(env_value APP_URL)"

if [ -n "$url" ] && command -v curl >/dev/null 2>&1; then
    say "Checking the live site"
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "${url%/}/api/health" || true)"

    if [ "$code" != 200 ]; then
        printf 'WARNING: %s/api/health answered %s.\n' "${url%/}" "$code" >&2
        printf 'To go back, deploy %s the same way. Restore the backup too if migrations changed the data.\n' "$previous" >&2
        exit 1
    fi

    printf 'Health check passed.\n'
fi

say "Done: $revision is live"
