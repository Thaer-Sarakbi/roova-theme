#!/usr/bin/env bash
#
# Build a throwaway WordPress + WooCommerce site and install the theme into it,
# so the booking flow can be exercised for real (hooks, database, templates).
#
# Usage: bin/testenv.sh [port]
#
# Needs: Homebrew php and mariadb (brew install php mariadb).
# Everything lands in .testenv/, which is disposable — delete it to start over.

set -euo pipefail

PORT="${1:-8099}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENVDIR="$ROOT/.testenv"
SITE="$ENVDIR/site"
WPCLI="$ENVDIR/wp-cli.phar"
DB=roova_test

export PATH="/opt/homebrew/bin:/opt/homebrew/opt/mariadb/bin:$PATH"

wp() { php "$WPCLI" --path="$SITE" "$@" 2>/dev/null | grep -v '^Deprecated' || true; }

mkdir -p "$ENVDIR"

# 1. MariaDB.
if ! mysqladmin ping >/dev/null 2>&1; then
	echo "Starting MariaDB…"
	mysqld_safe --datadir=/opt/homebrew/var/mysql >/dev/null 2>&1 &
	for _ in $(seq 1 30); do
		mysqladmin ping >/dev/null 2>&1 && break
		sleep 1
	done
fi

mysql -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB;
	CREATE USER IF NOT EXISTS 'roova'@'localhost' IDENTIFIED BY 'roova';
	GRANT ALL ON $DB.* TO 'roova'@'localhost'; FLUSH PRIVILEGES;"

# 2. WordPress + WP-CLI + WooCommerce.
[ -f "$WPCLI" ] || curl -sSL -o "$WPCLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
[ -f "$ENVDIR/wp.tar.gz" ] || curl -sSL -o "$ENVDIR/wp.tar.gz" https://wordpress.org/latest.tar.gz
[ -f "$ENVDIR/woocommerce.zip" ] || curl -sSL -o "$ENVDIR/woocommerce.zip" https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip

rm -rf "$SITE"
mkdir -p "$SITE"
tar xzf "$ENVDIR/wp.tar.gz" -C "$ENVDIR"
mv "$ENVDIR/wordpress"/* "$SITE"/
rmdir "$ENVDIR/wordpress"

wp core config --dbname=$DB --dbuser=roova --dbpass=roova --dbhost=127.0.0.1 --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', true );
PHP

wp core install --url="http://127.0.0.1:$PORT" --title="Roova Test" \
	--admin_user=admin --admin_password=admin --admin_email=test@example.com --skip-email
wp plugin install "$ENVDIR/woocommerce.zip" --activate

# WooCommerce hides the whole store behind a "coming soon" page on a fresh
# install, which makes hotel pages look broken. Turn it off.
wp option update woocommerce_coming_soon no

# Cash on delivery, so checkout can complete without a payment gateway.
wp eval '$s = get_option( "woocommerce_cod_settings", array() ); $s["enabled"] = "yes"; update_option( "woocommerce_cod_settings", $s );'

# 3. The theme.
bash "$ROOT/bin/build.sh" >/dev/null
rm -rf "$SITE/wp-content/themes/roova"
unzip -q "$ROOT/dist/roova.zip" -d "$SITE/wp-content/themes"
wp theme activate roova

echo
echo "Site ready:  http://127.0.0.1:$PORT   (admin / admin)"
echo "Serve it:    php -S 127.0.0.1:$PORT -t $SITE"
echo "WP-CLI:      php $WPCLI --path=$SITE <command>"
echo "Reinstall:   rm -rf $SITE/wp-content/themes/roova && unzip -q $ROOT/dist/roova.zip -d $SITE/wp-content/themes"
