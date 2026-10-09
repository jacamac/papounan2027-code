#!/usr/bin/env bash
# Build the installable plugin zip: build/papounan-site.zip
#
#   tools/build-zip.sh            keep the version in papounan-site.php
#   tools/build-zip.sh 1.2.0      inject 1.2.0 (header, constant, readme.txt Stable tag)
#
# Copies the repo minus .distignore entries, installs runtime Composer
# dependencies only (Plugin Update Checker), then zips the folder so it
# unpacks as papounan-site/ (required for WordPress to treat it as an update).
set -euo pipefail

SLUG="papounan-site"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-}"
BUILD="$ROOT/build"
STAGE="$BUILD/$SLUG"

rm -rf "$BUILD"
mkdir -p "$STAGE"

rsync -a --exclude-from="$ROOT/.distignore" "$ROOT/" "$STAGE/"
cp "$ROOT/composer.json" "$STAGE/"
[ -f "$ROOT/composer.lock" ] && cp "$ROOT/composer.lock" "$STAGE/"
composer install --no-dev --prefer-dist --no-progress --no-interaction --optimize-autoloader --working-dir="$STAGE"
rm -f "$STAGE/composer.json" "$STAGE/composer.lock"

if [ -n "$VERSION" ]; then
	sed -i.bak "s/^\( \* Version: *\).*/\1${VERSION}/" "$STAGE/$SLUG.php"
	sed -i.bak "s/define( 'PAPOUNAN_SITE_VERSION', '[^']*' );/define( 'PAPOUNAN_SITE_VERSION', '${VERSION}' );/" "$STAGE/$SLUG.php"
	sed -i.bak "s/^Stable tag:.*/Stable tag: ${VERSION}/" "$STAGE/readme.txt"
	rm -f "$STAGE"/*.bak
fi

# Smoke test: every PHP file parses and the plugin header is intact.
find "$STAGE" -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l >/dev/null
grep -q "Plugin Name:" "$STAGE/$SLUG.php"
test -f "$STAGE/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php"

(cd "$BUILD" && zip -qr "$SLUG.zip" "$SLUG")
echo "Built $BUILD/$SLUG.zip ($(grep -m1 'Version:' "$STAGE/$SLUG.php" | sed 's/.*Version: *//'))"
