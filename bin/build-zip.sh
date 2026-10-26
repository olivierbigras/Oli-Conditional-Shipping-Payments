#!/usr/bin/env bash
# Build the WordPress.org-style distributable ZIP.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="oli-conditional-shipping-payments"
VERSION="$(grep -E "^ \* Version:" "$ROOT/oli-conditional-shipping-payments.php" | head -n1 | sed 's/.*Version: //' | tr -d '[:space:]')"
DEST_DIR="${1:-/opt/cursor/artifacts}"
STAGING="$(mktemp -d)"
DEST="${DEST_DIR}/${SLUG}-${VERSION}.zip"

mkdir -p "$DEST_DIR" "$STAGING/$SLUG"

if [[ -f "$ROOT/.distignore" ]]; then
	rsync -a --exclude-from="$ROOT/.distignore" --exclude=".git" --exclude="vendor" "$ROOT"/ "$STAGING/$SLUG"/
else
	rsync -a --exclude=".git" --exclude="vendor" "$ROOT"/ "$STAGING/$SLUG"/
fi

# rsync --exclude-from treats patterns relative to the transfer root.
# Drop leftover tooling if any remain.
rm -rf "$STAGING/$SLUG/tests" "$STAGING/$SLUG/vendor" "$STAGING/$SLUG/.github" "$STAGING/$SLUG/.git"

(
	cd "$STAGING"
	zip -r -q "$DEST" "$SLUG"
)

rm -rf "$STAGING"
echo "$DEST"
