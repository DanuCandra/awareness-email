#!/usr/bin/env bash
set -Eeuo pipefail
umask 022

# Run an immutable copy: git may replace this script during deployment.
if [[ "${AWARENESS_DEPLOY_SNAPSHOT:-0}" != "1" ]]; then
    snapshot=$(mktemp /tmp/awareness-deploy-script.XXXXXX)
    cp -- "${BASH_SOURCE[0]}" "$snapshot"
    chmod 600 "$snapshot"
    export AWARENESS_DEPLOY_SNAPSHOT=1
    exec bash "$snapshot"
fi

export BOOTSTRAP_HAS_COLOR=0 FORCE_COLOR=0 NO_COLOR=1
export COMPOSER_ALLOW_SUPERUSER=1 GIT_TERMINAL_PROMPT=0

APP_DIR="${APP_DIR:-/www/wwwroot/awareness.ahost.my.id}"
PHP_BIN="${PHP_BIN:-/www/server/php/85/bin/php}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
SUPERVISORCTL_BIN="${SUPERVISORCTL_BIN:-supervisorctl}"
SUPERVISOR_CONFIG="${SUPERVISOR_CONFIG:-}"
SUPERVISOR_TARGET="${SUPERVISOR_TARGET:-awareness:awareness_00}"
WEB_OWNER="${WEB_OWNER:-www}"
WEB_GROUP="${WEB_GROUP:-www}"
BUILD_FRONTEND="${BUILD_FRONTEND:-0}"

phase=preflight
active=0
cookie_file=''
previous_commit='unknown'

log() { printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S %z')" "$*"; }
die() { log "ERROR: $*" >&2; return 1; }
artisan() { "$PHP_BIN" artisan "$@" --no-interaction --no-ansi; }

cleanup() {
    [[ -z "$cookie_file" ]] || rm -f -- "$cookie_file"
    rm -f -- "${BASH_SOURCE[0]}"
}

failure() {
    local result="$1" line="$2"
    trap - ERR INT TERM
    set +e
    log "DEPLOY FAILED: phase=$phase, line=$line, exit=$result"
    if [[ "$active" == "1" ]]; then
        "${supervisor[@]}" stop "$SUPERVISOR_TARGET"
        artisan down --render=errors::503 --retry=60
        log 'Application remains in maintenance mode. Keep workers stopped until the deployment is repaired.'
        log 'Database migrations are NOT rolled back automatically; successful DDL may already have been applied.'
    fi
    log "Commit before update: $previous_commit"
    exit "$result"
}
trap cleanup EXIT
trap 'failure "$?" "$LINENO"' ERR
trap 'failure 130 "$LINENO"' INT
trap 'failure 143 "$LINENO"' TERM

runtime_check() {
    "$PHP_BIN" -r '
        try {
            require "vendor/autoload.php";
            $app = require "bootstrap/app.php";
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            if (! $app->isProduction() || config("app.debug") !== false || config("app.maintenance.driver", "file") !== "file") {
                fwrite(STDERR, "Require APP_ENV=production, APP_DEBUG=false, and file-based maintenance.\n"); exit(1);
            }
            $url = rtrim((string) config("app.url"), "/");
            $parts = parse_url($url);
            if (! $parts || ($parts["scheme"] ?? "") !== "https" || empty($parts["host"]) || isset($parts["user"]) || isset($parts["pass"]) || isset($parts["query"]) || isset($parts["fragment"])) {
                fwrite(STDERR, "APP_URL must be the public HTTPS application URL.\n"); exit(1);
            }
            Illuminate\Support\Facades\DB::connection()->getPdo();
            echo $url;
        } catch (Throwable $exception) {
            fwrite(STDERR, "Runtime/database validation failed. Check PHP configuration and Laravel logs.\n"); exit(1);
        }
    '
}

supervisor_state() {
    local output name state rest
    output=$("${supervisor[@]}" status "$SUPERVISOR_TARGET" 2>&1) || :
    read -r name state rest <<< "$output"
    [[ "$name" == "$SUPERVISOR_TARGET" ]] || { log "$output" >&2; return 1; }
    printf '%s\n' "$state"
}

permissions() {
    if [[ "$EUID" == "0" ]]; then
        chown -R "$WEB_OWNER:$WEB_GROUP" storage bootstrap/cache
    fi
    find storage bootstrap/cache -type d -exec chmod 2770 {} +
    find storage bootstrap/cache -type f -exec chmod 660 {} +
}

http_health() {
    local code
    code=$(curl --silent --show-error --fail --connect-timeout 5 --max-time 20 \
        --retry 2 --retry-delay 2 --cookie "$cookie_file" --cookie-jar "$cookie_file" \
        --output /dev/null --write-out '%{http_code}' "$base_url/up")
    [[ "$code" == "200" ]] || die "HTTP health check returned $code instead of 200."
}

log 'Deployment started.'
[[ -d "$APP_DIR" && "$APP_DIR" != '/' ]] || die 'Invalid APP_DIR.'
cd -- "$APP_DIR"
APP_DIR=$(pwd -P)
[[ -f artisan && -f .env && -f vendor/autoload.php ]] || die 'This script requires an installed Laravel application with .env and vendor dependencies.'
[[ -x "$PHP_BIN" ]] || die "PHP CLI not found: $PHP_BIN"
export PATH="$(dirname "$PHP_BIN"):$PATH"
for executable in git flock curl find; do
    command -v "$executable" >/dev/null || die "Required executable is missing: $executable"
done
git() { command git -c "safe.directory=$APP_DIR" "$@"; }
# Lock Git metadata without creating a predictable writable file in /tmp.
exec 9<"$APP_DIR/.git"
flock --wait 300 9 || die 'Another deployment still holds the lock.'

COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer || true)}"
[[ -f "$COMPOSER_BIN" ]] || die 'Set COMPOSER_BIN to the Composer PHP script or PHAR.'
if ! command -v "$SUPERVISORCTL_BIN" >/dev/null; then
    SUPERVISORCTL_BIN=/www/server/panel/pyenv/bin/supervisorctl
fi
supervisor_path=$(command -v "$SUPERVISORCTL_BIN" || true)
[[ -x "$supervisor_path" ]] || die 'Set SUPERVISORCTL_BIN to the aaPanel supervisorctl executable.'
supervisor=("$SUPERVISORCTL_BIN")
if [[ -n "$SUPERVISOR_CONFIG" ]]; then supervisor+=(-c "$SUPERVISOR_CONFIG"); fi
state=$(supervisor_state)
[[ "$state" == 'RUNNING' || "$state" == 'STOPPED' ]] || die "Supervisor is in state $state. Resolve it before deploying."
"$PHP_BIN" -r 'foreach (["pcntl", "posix", "zip"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP CLI extension: $extension\n"); exit(1); } }'
command -v setsid >/dev/null || die 'setsid is required by simulations:work.'
[[ "$(git symbolic-ref --short HEAD)" == "$DEPLOY_BRANCH" ]] || die "Checkout must be on $DEPLOY_BRANCH."
git diff --quiet && git diff --cached --quiet || die 'Tracked server files contain local changes. Review them before deploying.'
previous_commit=$(git rev-parse HEAD)
base_url=$(runtime_check)

phase=fetch
git fetch --prune origin "$DEPLOY_BRANCH"
target_commit=$(git rev-parse FETCH_HEAD)
git merge-base --is-ancestor "$previous_commit" "$target_commit" || die 'Deployment requires a fast-forward update; branches diverged.'
if git cat-file -e "$target_commit:.env" 2>/dev/null; then die 'The target commit must not track .env.'; fi
git cat-file -e "$target_commit:composer.lock" || die 'Commit composer.lock before deploying.'

phase=maintenance
active=1
secret=$("$PHP_BIN" -r 'echo bin2hex(random_bytes(24));')
artisan down --render=errors::503 --retry=60 --secret="$secret" >/dev/null
if [[ "$state" != 'STOPPED' ]]; then "${supervisor[@]}" stop "$SUPERVISOR_TARGET"; fi
[[ "$(supervisor_state)" == 'STOPPED' ]] || die 'Supervisor did not stop cleanly.'

phase=checkout
log "Updating $previous_commit -> $target_commit"
git merge --ff-only "$target_commit"

phase=dependencies
"$PHP_BIN" "$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-ansi

# This application currently serves committed Vuexy assets. Enable only when Vite is used.
if [[ "$BUILD_FRONTEND" == '1' ]]; then
    phase=frontend
    [[ -f package-lock.json ]] || die 'BUILD_FRONTEND=1 requires a committed package-lock.json.'
    command -v npm >/dev/null || die 'npm is not available in the webhook PATH.'
    npm ci --include=dev --no-audit --no-fund
    npm run build
    [[ -f public/build/manifest.json ]] || die 'Vite build manifest is missing.'
fi
if [[ -f public/hot ]]; then rm -f -- public/hot; fi

phase=migrations
artisan config:clear
base_url=$(runtime_check)
artisan migrate --force

phase=cache
artisan route:clear
artisan view:clear
artisan event:clear
artisan config:cache
artisan route:cache
artisan view:cache
artisan event:cache
permissions
base_url=$(runtime_check)

phase=health
cookie_file=$(mktemp /tmp/awareness-deploy-cookie.XXXXXX)
curl --silent --show-error --fail --connect-timeout 5 --max-time 20 \
    --cookie-jar "$cookie_file" --output /dev/null "$base_url/$secret"
http_health

phase=supervisor
"${supervisor[@]}" start "$SUPERVISOR_TARGET"
sleep 4
[[ "$(supervisor_state)" == 'RUNNING' ]] || die 'Supervisor failed to remain RUNNING.'

phase=online
artisan up
# A fresh request must succeed without the maintenance bypass cookie.
: > "$cookie_file"
http_health

phase=record
(umask 077; printf '%s\n' "$target_commit" > storage/app/private/.last-successful-deploy.tmp)
mv -- storage/app/private/.last-successful-deploy.tmp storage/app/private/.last-successful-deploy
active=0
log "Deployment successful: $target_commit. HTTP health=200; Supervisor=RUNNING."
