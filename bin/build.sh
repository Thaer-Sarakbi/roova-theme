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

if command -v zip >/dev/null 2>&1; then
	zip -r -q "$ZIP" "$THEME" \
		-x '*.DS_Store' \
		-x '*/.git/*' \
		-x '*/node_modules/*' \
		-x '*.map'
elif command -v php >/dev/null 2>&1; then
	# Windows has no zip binary, but PHP always ships ZipArchive. Same contents,
	# same exclusions — the theme folder sits at the root of the archive.
	packer="$(mktemp -t roova-pack-XXXXXX.php)"
	trap 'rm -f "$packer"' EXIT

	cat > "$packer" <<'PHP'
<?php
$theme = getenv( 'ROOVA_THEME' );
$out   = getenv( 'ROOVA_ZIP' );
$skip  = '#(^|/)\.git(/|$)|(^|/)node_modules(/|$)|(^|/)\.DS_Store$|\.map$#';

$zip = new ZipArchive();
if ( true !== $zip->open( $out, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "could not create $out\n" );
	exit( 1 );
}

$files = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $theme, FilesystemIterator::SKIP_DOTS )
);

$count = 0;
foreach ( $files as $file ) {
	if ( $file->isDir() ) {
		continue;
	}
	$path = str_replace( DIRECTORY_SEPARATOR, '/', $file->getPathname() );
	$rel  = basename( $theme ) . '/' . ltrim( substr( $path, strlen( $theme ) ), '/' );
	if ( preg_match( $skip, $rel ) ) {
		continue;
	}
	$zip->addFile( $file->getPathname(), $rel );
	$count++;
}

$zip->close();
echo "packed $count files\n";
PHP

	ROOVA_THEME="$THEME" ROOVA_ZIP="$ZIP" php "$packer"
else
	echo "neither zip nor php is available to build the archive" >&2
	exit 1
fi

echo "Built $ZIP"
unzip -l "$ZIP" | tail -1
