#!/usr/bin/env bash
#
# Build dist/roova.zip — the file you upload under Appearance > Themes > Add New.
#
# Usage: bin/build.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
THEME="roova"
DIST="$ROOT/dist"
ZIP="$DIST/$THEME.zip"

cd "$ROOT"

# Refuse to ship a theme that does not parse.
if command -v php >/dev/null 2>&1; then
	bash "$ROOT/bin/lint.sh"
fi

# The theme header and screenshot are what WordPress reads on upload.
grep -q '^Theme Name:' "$THEME/style.css" || { echo "style.css is missing its theme header" >&2; exit 1; }
[ -f "$THEME/screenshot.png" ] || { echo "screenshot.png is missing" >&2; exit 1; }

mkdir -p "$DIST"
rm -f "$ZIP"

find "$THEME" -name '.DS_Store' -delete

zip -r -q "$ZIP" "$THEME" \
	-x '*.DS_Store' \
	-x '*/.git/*' \
	-x '*/node_modules/*' \
	-x '*.map'

echo "Built $ZIP"
unzip -l "$ZIP" | tail -1
