#!/usr/bin/env bash
#
# Syntax-check every PHP file in the theme.
#
# Usage: bin/lint.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
THEME="$ROOT/roova"

if ! command -v php >/dev/null 2>&1; then
	echo "php is not installed — install it (brew install php) or run this on the WordPress host." >&2
	exit 1
fi

failed=0
count=0

while IFS= read -r file; do
	count=$((count + 1))
	if ! output=$(php -l "$file" 2>&1); then
		echo "$output"
		failed=$((failed + 1))
	fi
done < <(find "$THEME" -name '*.php' -type f)

echo "Checked $count PHP files."

if [ "$failed" -gt 0 ]; then
	echo "$failed file(s) have syntax errors." >&2
	exit 1
fi

echo "No syntax errors."
