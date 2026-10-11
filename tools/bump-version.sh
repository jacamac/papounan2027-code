#!/usr/bin/env bash
# Set the plugin version in the three places that must match the release tag:
# the plugin header, PAPOUNAN_SITE_VERSION and the readme.txt "Stable tag".
# Usage: bash tools/bump-version.sh X.Y.Z
# Add the "= X.Y.Z =" changelog entry to readme.txt yourself.
set -euo pipefail

VERSION="${1:-}"
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "Usage: $0 X.Y.Z" >&2
	exit 1
fi

cd "$(dirname "$0")/.."

sed -i.bak -E "s/^( \* Version: +).*/\1${VERSION}/" papounan-site.php
sed -i.bak -E "s/('PAPOUNAN_SITE_VERSION', *')[^']*'/\1${VERSION}'/" papounan-site.php
sed -i.bak -E "s/^(Stable tag: *).*/\1${VERSION}/" readme.txt
rm -f papounan-site.php.bak readme.txt.bak

grep -n "Version:\|PAPOUNAN_SITE_VERSION'" papounan-site.php
grep -n "^Stable tag" readme.txt
grep -q "^= ${VERSION} =" readme.txt || echo "Reminder: readme.txt has no '= ${VERSION} =' changelog entry yet." >&2
