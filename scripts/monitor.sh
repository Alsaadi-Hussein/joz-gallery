#!/bin/bash
#
# Synthetic monitor for the 24-hour staging trial. Each pass checks the pages a
# customer needs, records every response time and writes any problem to the audit log.
#
#   MONITOR_URL=https://staging.example.com bash scripts/monitor.sh            One pass, for cron.
#   MONITOR_URL=https://staging.example.com bash scripts/monitor.sh --loop     Every 5 minutes for 24 hours.
#   bash scripts/monitor.sh --report                                           Sums up the logs so far.
#
# Cron line, every 5 minutes:
#   */5 * * * * MONITOR_URL=https://staging.example.com /bin/bash $HOME/joz/scripts/monitor.sh
#
# Settings:
#   MONITOR_URL     The site. Required, except for --report.
#   MONITOR_TOKEN   The site's HEALTH_TOKEN. Adds the cron, disk and database checks.
#   MONITOR_DIR     Where the logs go. Defaults to the current folder.
#   SLOW_MS         Slower than this counts as a problem. Default 1500.
#   INTERVAL_SECONDS, TRIAL_HOURS   For --loop. Default 300 and 24.
#
# Exits 1 when a pass finds a problem. Run it from another machine as well: a monitor
# on the same server stays quiet about the very failures that take the server down.

set -uo pipefail

LOG_DIR="${MONITOR_DIR:-.}"
AUDIT_LOG="$LOG_DIR/staging-audit.log"
LATENCY_LOG="$LOG_DIR/staging-latency.csv"
SLOW_MS="${SLOW_MS:-1500}"
INTERVAL_SECONDS="${INTERVAL_SECONDS:-300}"
TRIAL_HOURS="${TRIAL_HOURS:-24}"
TIMEOUT_SECONDS=20
BASE_URL="${MONITOR_URL:-}"
BASE_URL="${BASE_URL%/}"
problems=0

now() { date -u +%Y-%m-%dT%H:%M:%SZ; }

problem() {
    printf '%s ERROR %s\n' "$(now)" "$1" >> "$AUDIT_LOG"
    problems=$((problems + 1))
}

warning() {
    printf '%s WARN  %s\n' "$(now)" "$1" >> "$AUDIT_LOG"
}

# check <path> <expected code, or several joined by |> [text the page must contain]
check() {
    local path="$1" expected="$2" needle="${3:-}" body result code seconds ms verdict

    body="$(mktemp)"
    result="$(curl -s -o "$body" -w '%{http_code} %{time_total}' --max-time "$TIMEOUT_SECONDS" "$BASE_URL$path" 2>/dev/null)" || true
    code="${result%% *}"
    seconds="${result##* }"
    ms="$(awk -v s="${seconds:-0}" 'BEGIN { printf "%d", s * 1000 }')"

    verdict=fail

    if ! [[ "|$expected|" == *"|$code|"* ]]; then
        problem "$path answered ${code:-000}, expected $expected (${ms} ms)"
    elif [ -n "$needle" ] && ! grep -q -- "$needle" "$body"; then
        problem "$path answered $code but does not contain \"$needle\""
    elif [ "$ms" -gt "$SLOW_MS" ]; then
        problem "$path took ${ms} ms, over the ${SLOW_MS} ms limit"
    else
        verdict=ok
    fi

    printf '%s,%s,%s,%s,%s\n' "$(now)" "$path" "${code:-000}" "$ms" "$verdict" >> "$LATENCY_LOG"
    rm -f "$body"
}

check_health_details() {
    local body code

    body="$(mktemp)"
    code="$(curl -s -o "$body" -w '%{http_code}' --max-time "$TIMEOUT_SECONDS" -H "X-Health-Token: $MONITOR_TOKEN" "$BASE_URL/api/health" 2>/dev/null)" || true

    if [ "$code" != 200 ]; then
        problem "/api/health with the token answered ${code:-000}"
    elif ! grep -q '"database"' "$body"; then
        problem "/api/health ignored the token. Check HEALTH_TOKEN on the site"
    elif ! grep -q '"status":"ok"' "$body"; then
        problem "/api/health reports a problem: $(tr -d '\n' < "$body" | cut -c1-300)"
    fi

    grep -q '"failed":0' "$body" || warning "the queue has failed jobs: $(tr -d '\n' < "$body" | grep -o '"queue":{[^}]*}')"
    rm -f "$body"
}

one_pass() {
    check /api/health 200 '"status":"ok"'
    check / 200 'csrf-token'
    check /en 200 'csrf-token'
    check /offers 200
    check /track 200
    check /checkout 200 '_token'
    check /admin/login 200
    check /robots.txt 200 'Sitemap'
    check /sitemap.xml 200 '<urlset'

    # These must stay closed. A 200 here is an emergency, not a slow page.
    check /.env '403|404'
    check /.git/config '403|404'
    check /composer.lock '403|404'
    check /storage/ '403|404'

    if [ -n "${MONITOR_TOKEN:-}" ]; then
        check_health_details
    fi

    printf '%s pass finished, %s problem(s)\n' "$(now)" "$problems"
}

report() {
    [ -f "$LATENCY_LOG" ] || { echo "No results yet in $LATENCY_LOG"; return 1; }

    local errors=0

    [ -f "$AUDIT_LOG" ] && errors="$(grep -c ' ERROR ' "$AUDIT_LOG")"

    printf 'Period: %s to %s\n' "$(head -n 1 "$LATENCY_LOG" | cut -d, -f1)" "$(tail -n 1 "$LATENCY_LOG" | cut -d, -f1)"
    printf 'Problems logged: %s\n\n' "$errors"
    printf '%-18s %7s %8s %8s %8s %8s\n' path checks 'pass %' 'p50 ms' 'p95 ms' 'max ms'

    cut -d, -f2 "$LATENCY_LOG" | LC_ALL=C sort -u | while IFS= read -r path; do
        awk -F, -v path="$path" '$2 == path { print $4, $5 }' "$LATENCY_LOG" | LC_ALL=C sort -n | awk -v path="$path" '
            { ms[NR] = $1; if ($2 == "ok") ok++ }
            END {
                p50 = int((NR + 1) * 0.50); p95 = int((NR + 1) * 0.95)
                if (p50 < 1) p50 = 1; if (p95 > NR) p95 = NR; if (p95 < 1) p95 = 1
                printf "%-18s %7d %8.2f %8d %8d %8d\n", path, NR, ok / NR * 100, ms[p50], ms[p95], ms[NR]
            }'
    done

    if [ "$errors" -gt 0 ]; then
        printf '\nLast problems:\n'
        grep ' ERROR ' "$AUDIT_LOG" | tail -n 10
    fi
}

mkdir -p "$LOG_DIR"

case "${1:-}" in
    --report)
        report
        exit $?
        ;;
    --loop)
        [ -n "$BASE_URL" ] || { echo "Set MONITOR_URL" >&2; exit 2; }
        printf '%s trial started on %s for %s hours\n' "$(now)" "$BASE_URL" "$TRIAL_HOURS" >> "$AUDIT_LOG"
        end=$(( $(date +%s) + TRIAL_HOURS * 3600 ))

        while [ "$(date +%s)" -lt "$end" ]; do
            problems=0
            one_pass
            sleep "$INTERVAL_SECONDS"
        done

        printf '%s trial finished\n' "$(now)" >> "$AUDIT_LOG"
        report
        ;;
    "")
        [ -n "$BASE_URL" ] || { echo "Set MONITOR_URL" >&2; exit 2; }
        one_pass
        [ "$problems" -eq 0 ]
        ;;
    *)
        echo "Usage: monitor.sh [--loop|--report]" >&2
        exit 2
        ;;
esac
