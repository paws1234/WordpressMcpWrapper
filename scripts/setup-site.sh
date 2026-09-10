#!/usr/bin/env bash
#
# Provision a wp-dev project: WordPress core install, bundled plugins and themes,
# the project theme, and pretty permalinks.
#
# Safe to re-run; every step is skipped when it is already done.
#
# Usage: setup-site.sh [project-dir]
#
set -euo pipefail

PROJECT_DIR="${1:-$(pwd)}"
cd "$PROJECT_DIR"

if [ ! -f .env ]; then
	echo "No .env found in $PROJECT_DIR" >&2
	exit 1
fi

set -a
# shellcheck disable=SC1091
. ./.env
set +a

WP="docker compose run --rm -T cli"

echo "==> Starting ${PROJECT_NAME} (${SITE_URL})"
docker compose up -d db wordpress

echo "==> Waiting for WordPress core files"
ready=""
for _ in $(seq 1 60); do
	# The wordpress container's entrypoint unpacks core and writes wp-config.php on
	# first run. Wait for that file rather than probing with WP-CLI, because most
	# WP-CLI commands refuse to run before the site is installed.
	if docker compose exec -T wordpress test -f /var/www/html/wp-config.php 2>/dev/null; then
		ready="yes"
		break
	fi
	sleep 2
done

if [ -z "$ready" ]; then
	echo "WordPress core files never appeared." >&2
	echo "Check: docker compose logs wordpress" >&2
	exit 1
fi

echo "==> Installing WordPress"
if $WP core is-installed >/dev/null 2>&1; then
	echo "    already installed"
else
	$WP core install \
		--url="$SITE_URL" \
		--title="$SITE_TITLE" \
		--admin_user="$ADMIN_USER" \
		--admin_password="$ADMIN_PASSWORD" \
		--admin_email="$ADMIN_EMAIL" \
		--skip-email
fi

echo "==> Reconciling the site URL"
# The site URL lives in the database, not in .env. If WP_PORT changes, the config files are
# regenerated but WordPress keeps advertising the old port, so the front end loads with asset
# URLs the browser cannot reach - which a Playwright screenshot would faithfully capture as a
# broken page. Keep the database in step with SITE_URL.
configured_url="$( $WP option get home 2>/dev/null || true )"

if [ -n "$configured_url" ] && [ "$configured_url" != "$SITE_URL" ]; then
	echo "    $configured_url -> $SITE_URL"
	$WP search-replace "$configured_url" "$SITE_URL" --all-tables --precise --skip-columns=guid \
		|| echo "    warning: search-replace did not complete; home and siteurl were still updated"
	$WP option update home "$SITE_URL"
	$WP option update siteurl "$SITE_URL"
else
	echo "    $SITE_URL (unchanged)"
fi

echo "==> Installing bundled packages"
for plugin in ${BUNDLED_PLUGINS:-}; do
	if $WP plugin is-installed "$plugin" >/dev/null 2>&1; then
		$WP plugin activate "$plugin"
	else
		$WP plugin install "/opt/packages/${plugin}.zip" --activate
	fi
done

for theme in ${BUNDLED_THEMES:-}; do
	if $WP theme is-installed "$theme" >/dev/null 2>&1; then
		echo "    ${theme} already installed"
	else
		$WP theme install "/opt/packages/${theme}.zip"
	fi
done

echo "==> Activating project code"
$WP theme activate "$PROJECT_THEME"
$WP plugin activate wp-agent-bridge

echo "==> Enabling pretty permalinks"
# The MCP endpoint is served at /wp-json/mcp/mcp-adapter-default-server, which only
# resolves once pretty permalinks are enabled. With plain permalinks that path falls
# through to the front page and returns HTML instead of JSON-RPC.
$WP rewrite structure '/%postname%/' --hard
$WP rewrite flush --hard

echo
echo "---------------------------------------------------------------"
echo " ${SITE_TITLE}"
echo " Site:   ${SITE_URL}"
echo " Admin:  ${ADMIN_USER} / ${ADMIN_PASSWORD}"
echo " Editor: ${SITE_URL}/wp-admin"
echo "---------------------------------------------------------------"
echo
echo "Next: wpdev creds"
