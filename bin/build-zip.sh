#!/usr/bin/env bash
# Build a WordPress-installable zip: spyderbot-tracking-<version>.zip
# Single top-level folder; excludes tests, .git, CI, vendor, and other dev files.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

VERSION="$(
  grep -E "^ \* Version:" spyderbot-tracking.php \
    | head -1 \
    | sed -E 's/.*Version:[[:space:]]*//' \
    | tr -d '[:space:]'
)"
if [[ -z "$VERSION" ]]; then
  echo "ERROR: could not read Version from spyderbot-tracking.php" >&2
  exit 1
fi

PLUGIN_SLUG="spyderbot-tracking"
OUT_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
DIST_DIR="${ROOT_DIR}/dist"
STAGE_DIR="${DIST_DIR}/.stage/${PLUGIN_SLUG}"
OUT_PATH="${DIST_DIR}/${OUT_NAME}"

rm -rf "${DIST_DIR}/.stage"
mkdir -p "$STAGE_DIR"

# Copy plugin files with excludes (rsync preferred; fall back to tar).
if command -v rsync >/dev/null 2>&1; then
  rsync -a \
    --exclude '.git' \
    --exclude '.git/' \
    --exclude '.gitignore' \
    --exclude '.gitattributes' \
    --exclude '.gitlab-ci.yml' \
    --exclude '.github/' \
    --exclude 'tests/' \
    --exclude 'bin/' \
    --exclude 'vendor/' \
    --exclude 'node_modules/' \
    --exclude 'dist/' \
    --exclude 'composer.lock' \
    --exclude 'composer.json' \
    --exclude 'phpunit.xml' \
    --exclude 'phpunit.xml.dist' \
    --exclude 'patchwork.json' \
    --exclude '.phpunit.result.cache' \
    --exclude '.phpunit.cache/' \
    --exclude '.DS_Store' \
    --exclude '*.map' \
    --exclude 'CHANGELOG.md' \
    --exclude 'README.md' \
    ./ "$STAGE_DIR/"
else
  mkdir -p "$STAGE_DIR"
  tar -cf - \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='.gitlab-ci.yml' \
    --exclude='tests' \
    --exclude='bin' \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='dist' \
    --exclude='composer.lock' \
    --exclude='composer.json' \
    --exclude='phpunit.xml' \
    --exclude='phpunit.xml.dist' \
    --exclude='patchwork.json' \
    --exclude='CHANGELOG.md' \
    --exclude='README.md' \
    . | tar -xf - -C "$STAGE_DIR"
fi

# Worktree checkouts leave a plain `.git` gitfile — never ship it.
rm -rf "${STAGE_DIR}/.git"

# Ensure languages/ exists in the package (text domain path).
mkdir -p "$STAGE_DIR/languages"

# Validate required files before zipping.
for req in spyderbot-tracking.php readme.txt includes/class-settings.php; do
  if [[ ! -f "$STAGE_DIR/$req" ]]; then
    echo "ERROR: missing required file in stage: $req" >&2
    exit 1
  fi
done

# Reject unexpected SpyderBot hostnames in packaged PHP/txt.
mapfile -t _hosts < <(grep -RoE --exclude-dir=languages '[a-z0-9.-]+\.spyderbot\.net' "$STAGE_DIR" 2>/dev/null | sed 's/.*://' | sort -u || true)
for _host in "${_hosts[@]:-}"; do
  case "$_host" in
    ''|spyderbot.net|api.spyderbot.net) ;;
    *)
      echo "ERROR: staged package references unexpected host: $_host" >&2
      exit 1
      ;;
  esac
done
unset _hosts _host

rm -f "$OUT_PATH"
if command -v zip >/dev/null 2>&1; then
  (
    cd "${DIST_DIR}/.stage"
    zip -rq "$OUT_PATH" "$PLUGIN_SLUG"
  )
else
  # Fallback when info-zip is not installed (sandbox / CI boxes).
  python3 - "$OUT_PATH" "${DIST_DIR}/.stage" "$PLUGIN_SLUG" <<'PY'
import sys, zipfile
from pathlib import Path
out, stage_parent, slug = Path(sys.argv[1]), Path(sys.argv[2]), sys.argv[3]
root = stage_parent / slug
with zipfile.ZipFile(out, "w", compression=zipfile.ZIP_DEFLATED) as zf:
    for path in sorted(root.rglob("*")):
        if path.is_file():
            zf.write(path, arcname=str(Path(slug) / path.relative_to(root)))
print(f"python zipfile wrote {out}")
PY
fi

# Structural check: zip must contain exactly one top-level folder.
if command -v unzip >/dev/null 2>&1; then
  TOP_LEVEL="$(unzip -Z1 "$OUT_PATH" | awk -F/ '{print $1}' | sort -u)"
else
  TOP_LEVEL="$(python3 - "$OUT_PATH" <<'PY'
import sys, zipfile
names = zipfile.ZipFile(sys.argv[1]).namelist()
tops = sorted({n.split('/', 1)[0] for n in names if n})
print('\n'.join(tops))
PY
)"
fi
if [[ "$TOP_LEVEL" != "$PLUGIN_SLUG" ]]; then
  echo "ERROR: expected single top-level folder '$PLUGIN_SLUG', got:" >&2
  echo "$TOP_LEVEL" >&2
  exit 1
fi

# php -l every PHP file in the staged tree.
PHP_BIN="${PHP_BIN:-php}"
if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "ERROR: php not found (set PHP_BIN)" >&2
  exit 1
fi

while IFS= read -r -d '' phpfile; do
  "$PHP_BIN" -l "$phpfile" >/dev/null
done < <(find "$STAGE_DIR" -type f -name '*.php' -print0)

SIZE="$(wc -c < "$OUT_PATH" | tr -d ' ')"
SHA256="$(sha256sum "$OUT_PATH" | awk '{print $1}')"

echo "Built: $OUT_PATH"
echo "Size:  $SIZE bytes"
echo "SHA256: $SHA256"

# Leave a stable copy name without cleaning stage (optional cleanup).
rm -rf "${DIST_DIR}/.stage"
echo "$OUT_PATH"
