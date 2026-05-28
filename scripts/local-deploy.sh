#!/usr/bin/env bash
# =============================================================================
# local-deploy.sh — Install or clean-update Repetio to a local Nextcloud AIO
# =============================================================================
# Works for both first-time install and upgrades.
# For upgrades it does a clean wipe of old app code first so no stale files
# from previous versions can linger. User data (card files in Nextcloud Files,
# settings in the database) is never touched.
#
# Usage:
#   ./scripts/local-deploy.sh [--no-build] [--container <name>]
#
#   --no-build       Skip "npm run build" (use when you already built)
#   --container      Override container name (default: nextcloud-aio-nextcloud)
#
# Requirements:
#   - Docker CLI accessible
#   - Node + npm for the build step (skip with --no-build if not needed)
# =============================================================================

set -euo pipefail

# ── Defaults ─────────────────────────────────────────────────────────────────
APP_ID="flashcards"
CONTAINER="${NEXTCLOUD_CONTAINER:-nextcloud-aio-nextcloud}"
REMOTE_APP_BASE="/var/www/html/custom_apps"
REMOTE_APP_DIR="${REMOTE_APP_BASE}/${APP_ID}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(dirname "${SCRIPT_DIR}")"
DO_BUILD=true

# ── Colours ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()  { echo -e "${GREEN}[+]${NC} $*"; }
step()  { echo -e "${CYAN}[→]${NC} $*"; }
warn()  { echo -e "${YELLOW}[!]${NC} $*"; }
fail()  { echo -e "${RED}[✗]${NC} $*"; exit 1; }

# ── Argument parsing ──────────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
    case "$1" in
        --no-build)    DO_BUILD=false; shift ;;
        --container)   CONTAINER="$2"; shift 2 ;;
        -h|--help)
            sed -n '4,22p' "$0"   # print the usage comment block
            exit 0 ;;
        *) fail "Unknown option: $1" ;;
    esac
done

echo ""
echo -e "${CYAN}══════════════════════════════════════════════════${NC}"
echo -e "${CYAN}  Repetio — Local Nextcloud AIO Deployer${NC}"
echo -e "${CYAN}══════════════════════════════════════════════════${NC}"
echo ""

# ── 1. Container health check ─────────────────────────────────────────────────
step "Checking container '${CONTAINER}'..."
RUNNING=$(docker inspect --format='{{.State.Running}}' "${CONTAINER}" 2>/dev/null || echo "false")
[[ "${RUNNING}" == "true" ]] \
    || fail "Container '${CONTAINER}' is not running. Start Nextcloud AIO first."
info "Container is running."

# ── 2. Build frontend (optional) ─────────────────────────────────────────────
if [[ "${DO_BUILD}" == "true" ]]; then
    step "Building frontend..."
    cd "${APP_DIR}"
    npm ci --prefer-offline --silent
    npm run build
    info "Frontend built successfully."
else
    warn "Skipping build (--no-build)."
    cd "${APP_DIR}"
fi

# ── 3. Disable the app gracefully ────────────────────────────────────────────
step "Disabling app in Nextcloud (if installed)..."
docker exec --user www-data "${CONTAINER}" \
    php occ app:disable "${APP_ID}" 2>/dev/null \
    && info "App disabled." \
    || warn "App was not enabled (first install?)."

# ── 4. Remove old app directory completely ───────────────────────────────────
#   This is a CLEAN INSTALL: removes all old app code to prevent stale files.
#   It does NOT touch:
#     - /var/www/html/data/          (user files and deck data)
#     - Nextcloud database           (user settings, schedules)
#     - Other custom_apps/           (other installed apps)
step "Removing old app files from container..."
docker exec "${CONTAINER}" rm -rf "${REMOTE_APP_DIR}"
docker exec "${CONTAINER}" mkdir -p "${REMOTE_APP_DIR}"
info "Old app directory cleared."

# ── 5. Copy app files ─────────────────────────────────────────────────────────
step "Copying app files to container..."

# Directories that make up the app package
COPY_DIRS=(appinfo css img js l10n lib templates)
COPIED=()
for d in "${COPY_DIRS[@]}"; do
    SRC="${APP_DIR}/${d}"
    if [[ -d "${SRC}" ]]; then
        docker cp "${SRC}/." "${CONTAINER}:${REMOTE_APP_DIR}/${d}/"
        COPIED+=("${d}/")
    fi
done

info "Copied: ${COPIED[*]}"

# ── 6. Fix ownership ──────────────────────────────────────────────────────────
step "Fixing file ownership (www-data)..."
docker exec "${CONTAINER}" \
    chown -R www-data:www-data "${REMOTE_APP_DIR}"
info "Ownership set."

# ── 7. Enable the app ─────────────────────────────────────────────────────────
step "Enabling app..."
docker exec --user www-data "${CONTAINER}" \
    php occ app:enable "${APP_ID}"
info "App enabled."

# ── 8. Run database migrations ────────────────────────────────────────────────
step "Running upgrade / DB migrations..."
docker exec --user www-data "${CONTAINER}" \
    php occ upgrade --no-interaction 2>&1 \
    | grep -E "(upgrade|migration|error)" || true
info "Upgrade step done."

# ── 9. Clear server-side caches ───────────────────────────────────────────────
step "Clearing caches..."
docker exec --user www-data "${CONTAINER}" \
    php occ maintenance:repair --quiet 2>/dev/null || true
info "Caches cleared."

# ── 10. Done ─────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}══════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  ✓ Repetio installed and enabled successfully!${NC}"
echo -e "${GREEN}══════════════════════════════════════════════════${NC}"
echo ""
echo "  App ID   : ${APP_ID}"
echo "  Container: ${CONTAINER}"
echo "  App path : ${REMOTE_APP_DIR}"
echo ""

# Show current app version for confirmation
APP_VER=$(docker exec --user www-data "${CONTAINER}" \
    php occ app:list --shipped=false --output=json 2>/dev/null \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('enabled',{}).get('${APP_ID}','?'))" \
    2>/dev/null || echo "?")
echo "  Version  : ${APP_VER}"
echo ""
warn "NOTE: If you had the app installed before with default folder '/ObsidianSync',"
warn "      your existing user settings and card files are untouched."
warn "      The new default for fresh accounts is '/StudySync'."
warn "      Long-time users: go to Settings → Repetio and verify your deck folder."
echo ""
