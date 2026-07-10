#!/usr/bin/env bash
# Build an installable WordPress plugin zip: dist/matrixweave-for-woocommerce.zip
# The zip contains a single top-level `matrixweave-for-woocommerce/` folder, as
# WordPress expects for Plugins → Add New → Upload Plugin.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="matrixweave-for-woocommerce"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$STAGE/$SLUG" "$ROOT/dist"

# Copy only runtime files — no git, docs tooling, or the dist folder itself.
(cd "$ROOT" && tar -cf - \
  --exclude='./.git' \
  --exclude='./.github' \
  --exclude='./dist' \
  --exclude='./bin' \
  --exclude='./.gitignore' \
  --exclude='./README.md' \
  .) | (cd "$STAGE/$SLUG" && tar -xf -)

rm -f "$ROOT/dist/$SLUG.zip"
(cd "$STAGE" && zip -rq "$ROOT/dist/$SLUG.zip" "$SLUG")

echo "Built $ROOT/dist/$SLUG.zip"
unzip -l "$ROOT/dist/$SLUG.zip" | head -25
