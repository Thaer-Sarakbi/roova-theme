<?php
/**
 * Build a .pot file for the Roova theme by scanning its PHP sources.
 *
 * A straight port of bin/makepot.py for machines with PHP but no Python
 * (Windows, mostly — the same reason bin/testenv-win.sh exists). Both tools
 * walk directories in sorted order, so they produce the same file.
 *
 * Usage: php bin/makepot.php roova roova/languages/roova.pot
 */

if ( $argc < 3 ) {
	fwrite( STDERR, "usage: php bin/makepot.php <theme-dir> <out.pot>\n" );
	exit( 1 );
}

$theme = rtrim( str_replace( '\\', '/', $argv[1] ), '/' );
$out   = $argv[2];

// A quoted PHP string: '...' or "..." with escaped quotes allowed.
$string   = "(?:'((?:[^'\\\\]|\\\\.)*)'|\"((?:[^\"\\\\]|\\\\.)*)\")";
$singular = '(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)';
$plural   = '_n';
$context  = '(?:_x|esc_html_x|esc_attr_x)';

$re_singular = '/' . $singular . '\(\s*' . $string . '\s*,\s*\'roova\'\s*\)/';
$re_plural   = '/' . $plural . '\(\s*' . $string . '\s*,\s*' . $string . '\s*,/';
$re_context  = '/' . $context . '\(\s*' . $string . '\s*,\s*' . $string . '\s*,\s*\'roova\'\s*\)/';

/**
 * The PHP string value with its escapes resolved.
 *
 * @param string $single Single-quoted capture, '' when it did not match.
 * @param string $double Double-quoted capture.
 * @return string
 */
function roova_pot_unquote( $single, $double ) {
	if ( '' !== $single ) {
		return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $single );
	}
	return str_replace( array( '\\"', '\\n', '\\t', '\\\\' ), array( '"', "\n", "\t", '\\' ), $double );
}

/**
 * Escape a string for a .pot msgid.
 *
 * @param string $value Raw string.
 * @return string
 */
function roova_pot_escape( $value ) {
	return str_replace( array( '\\', '"', "\n", "\t" ), array( '\\\\', '\\"', '\\n', '\\t' ), $value );
}

/**
 * Every PHP file under a directory: its own files first, then each
 * subdirectory, both in sorted order.
 *
 * @param string $dir Directory.
 * @return string[]
 */
function roova_pot_walk( $dir ) {
	$files = array();
	$dirs  = array();

	foreach ( (array) scandir( $dir ) as $name ) {
		if ( 0 === strpos( $name, '.' ) ) {
			continue;
		}
		$path = $dir . '/' . $name;
		if ( is_dir( $path ) ) {
			$dirs[] = $path;
		} elseif ( '.php' === substr( $name, -4 ) ) {
			$files[] = $path;
		}
	}

	sort( $files );
	sort( $dirs );

	foreach ( $dirs as $sub ) {
		$files = array_merge( $files, roova_pot_walk( $sub ) );
	}

	return $files;
}

$entries = array();

/**
 * Record one string, or add a reference to one already seen.
 *
 * @param array  $entries   Entries, by key.
 * @param string $key       Unique key for the string.
 * @param string $block     The .pot block to write.
 * @param string $reference file:line.
 */
function roova_pot_add( &$entries, $key, $block, $reference ) {
	if ( isset( $entries[ $key ] ) ) {
		if ( ! in_array( $reference, $entries[ $key ]['refs'], true ) ) {
			$entries[ $key ]['refs'][] = $reference;
		}
		return;
	}

	$entries[ $key ] = array(
		'block' => $block,
		'refs'  => array( $reference ),
	);
}

foreach ( roova_pot_walk( $theme ) as $path ) {
	$relative = ltrim( substr( str_replace( '\\', '/', $path ), strlen( $theme ) ), '/' );
	$lines    = explode( "\n", str_replace( "\r\n", "\n", (string) file_get_contents( $path ) ) );

	foreach ( $lines as $index => $line ) {
		$reference = $relative . ':' . ( $index + 1 );

		if ( preg_match_all( $re_context, $line, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$match += array( '', '', '', '', '' );
				$text   = roova_pot_unquote( $match[1], $match[2] );
				$ctx    = roova_pot_unquote( $match[3], $match[4] );
				roova_pot_add(
					$entries,
					"ctx\0" . $ctx . "\0" . $text,
					'msgctxt "' . roova_pot_escape( $ctx ) . "\"\nmsgid \"" . roova_pot_escape( $text ) . "\"\nmsgstr \"\"",
					$reference
				);
			}
		}

		if ( preg_match_all( $re_plural, $line, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$match += array( '', '', '', '', '' );
				$one    = roova_pot_unquote( $match[1], $match[2] );
				$many   = roova_pot_unquote( $match[3], $match[4] );
				roova_pot_add(
					$entries,
					"plural\0" . $one . "\0" . $many,
					'msgid "' . roova_pot_escape( $one ) . "\"\nmsgid_plural \"" . roova_pot_escape( $many ) . "\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"",
					$reference
				);
			}
		}

		if ( preg_match_all( $re_singular, $line, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$match += array( '', '', '' );
				$text   = roova_pot_unquote( $match[1], $match[2] );
				roova_pot_add(
					$entries,
					"single\0" . $text,
					'msgid "' . roova_pot_escape( $text ) . "\"\nmsgstr \"\"",
					$reference
				);
			}
		}
	}
}

$pot = "# Copyright (C) 2026 Roova\n"
	. "# This file is distributed under the GNU General Public License v2 or later.\n"
	. "msgid \"\"\n"
	. "msgstr \"\"\n"
	. "\"Project-Id-Version: Roova 1.0.0\\n\"\n"
	. "\"Report-Msgid-Bugs-To: \\n\"\n"
	. "\"MIME-Version: 1.0\\n\"\n"
	. "\"Content-Type: text/plain; charset=UTF-8\\n\"\n"
	. "\"Content-Transfer-Encoding: 8bit\\n\"\n"
	. "\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n"
	. "\"X-Domain: roova\\n\"\n";

foreach ( $entries as $entry ) {
	$pot .= "\n#: " . implode( ' ', $entry['refs'] ) . "\n" . $entry['block'] . "\n";
}

file_put_contents( $out, $pot );

echo count( $entries ) . " strings\n";
