#!/bin/bash
set -euo pipefail

# Package the pollora-demo-plugin development plugin into the plugin-default template.
#
# Usage: ./package-plugin.sh <source-plugin-path>
#
# Example:
#   ./package-plugin.sh ~/Sites/pollora-test/public/content/plugins/pollora-demo-plugin
#
# This script:
# 1. Copies plugin files (excluding node_modules, locks, build artifacts)
# 2. Replaces "pollora-demo-plugin" / "PolloraDemoPlugin" with placeholders
# 3. Renames PHP files in app/ to .stub
# 4. Renames the main plugin file to use placeholder
# 5. Shows a diff for review before committing

SOURCE="${1:?Usage: $0 <source-plugin-path>}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
TARGET_DIR="$(dirname "$SCRIPT_DIR")"

# The dev plugin code name and its variants
CODE_NAME="pollora-demo-plugin"
CODE_STUDLY="PolloraDemoPlugin"
CODE_FUNCTION="pollora_demo_plugin"
CODE_UPPER="POLLORA_DEMO_PLUGIN"

echo "=== Packaging plugin ==="
echo "  Source: $SOURCE"
echo "  Target: $TARGET_DIR"
echo ""

if [ ! -d "$SOURCE" ]; then
    echo "Error: Source directory not found: $SOURCE"
    exit 1
fi

# The WordPress and PHP requirements in the plugin header belong to the
# template, which follows the framework; the development copy lags behind.
# Read them before the sync and put them back after it.
TEMPLATE_MAIN="$TARGET_DIR/%plugin_name%.php"
REQUIREMENTS=""
if [ -f "$TEMPLATE_MAIN" ]; then
    REQUIREMENTS=$(grep -E '^ \* (Requires at least|Tested up to|Requires PHP):' "$TEMPLATE_MAIN" || true)
fi

# Sync files (exclude node_modules, locks, build artifacts, .git)
echo "Syncing files..."
rsync -av --delete \
    --exclude='node_modules' \
    --exclude='package-lock.json' \
    --exclude='yarn.lock' \
    --exclude='.git' \
    --exclude='bin/' \
    --exclude='/README.md' \
    --exclude='/.github/' \
    --exclude='/LICENSE' \
    --exclude='/license.txt' \
    "$SOURCE/" "$TARGET_DIR/" \
    --quiet

# package.json comes from the development copy, which declares no license (or
# another one): the template's is MIT, the same as LICENSE.
echo "Setting the package.json license to MIT..."
node -e '
const fs = require("fs");
const [file, license] = process.argv.slice(1);
const pkg = JSON.parse(fs.readFileSync(file, "utf8"));
let out = pkg;
if ("license" in pkg) {
    pkg.license = license;
} else {
    out = {};
    for (const [key, value] of Object.entries(pkg)) {
        out[key] = value;
        if (key === "private") out.license = license;
    }
    if (!("license" in out)) out.license = license;
}
fs.writeFileSync(file, JSON.stringify(out, null, 4) + "\n");
' "$TARGET_DIR/package.json" "MIT"

echo "Replacing code name with placeholders..."

find "$TARGET_DIR" -type f \
    -not -path "*/.git/*" \
    -not -path "*/.github/*" \
    -not -path "*/node_modules/*" \
    -not -path "*/bin/*" \
    -not -name "package-plugin.sh" \
    -not -name "*.woff2" \
    -not -name "*.png" \
    -not -name "*.jpg" \
    -not -name "*.svg" \
    | while read -r file; do
        sed -i \
            -e "s|Plugin\\\\${CODE_STUDLY}|Plugin\\\\%plugin_namespace%|g" \
            -e "s|${CODE_STUDLY}Plugin|%plugin_namespace%Plugin|g" \
            -e "s|${CODE_STUDLY}App|%plugin_namespace%Plugin|g" \
            -e "s|${CODE_STUDLY}Admin|%plugin_namespace%Admin|g" \
            -e "s|${CODE_STUDLY}|%plugin_namespace%|g" \
            -e "s|# Pollora Demo Plugin|# %plugin_name%|g" \
            -e "s|Pollora demo plugin for development and template generation\.|%plugin_description%|g" \
            -e "s|Plugin Name: Pollora Demo Plugin|Plugin Name: %plugin_name%|g" \
            -e "s|Plugin URI: https://pollora.dev|Plugin URI: %plugin_uri%|g" \
            -e "s|Description: %plugin_description%|Description: %plugin_description%|g" \
            -e "s|Author: Pollora|Author: %plugin_author%|g" \
            -e "s|Author URI: https://pollora.dev|Author URI: %plugin_author_uri%|g" \
            -e "s|Version: [0-9.]*|Version: %plugin_version%|g" \
            -e "s|'version' => '[0-9.]*'|'version' => '%plugin_version%'|g" \
            -e "s|_VERSION', '[0-9.]*'|_VERSION', '%plugin_version%'|g" \
            -e "s|\"version\": \"[0-9.]*\"|\"version\": \"%plugin_version%\"|g" \
            -e "s|${CODE_UPPER}|%PLUGIN_NAME%|g" \
            -e "s|${CODE_FUNCTION}|%plugin_function_name%|g" \
            -e "s|'${CODE_NAME}'|'%plugin_slug%'|g" \
            -e "s|\"${CODE_NAME}\"|\"%plugin_slug%\"|g" \
            -e "s|${CODE_NAME}|%plugin_name%|g" \
            -e "s|\"author\": \"Pollora\"|\"author\": \"%plugin_author%\"|g" \
            "$file"
    done

echo "Renaming PHP files to .stub in app/..."

# Rename main plugin class
if [ -f "$TARGET_DIR/app/${CODE_STUDLY}Plugin.php" ]; then
    mv "$TARGET_DIR/app/${CODE_STUDLY}Plugin.php" "$TARGET_DIR/app/%plugin_namespace%Plugin.stub"
elif [ -f "$TARGET_DIR/app/%plugin_namespace%Plugin.php" ]; then
    mv "$TARGET_DIR/app/%plugin_namespace%Plugin.php" "$TARGET_DIR/app/%plugin_namespace%Plugin.stub"
fi

# Rename service providers
find "$TARGET_DIR/app/Providers" -name "*.php" -type f | while read -r file; do
    mv "$file" "${file%.php}.stub"
done

# Rename main plugin file
if [ -f "$TARGET_DIR/${CODE_NAME}.php" ]; then
    mv "$TARGET_DIR/${CODE_NAME}.php" "$TARGET_DIR/%plugin_name%.php"
elif [ -f "$TARGET_DIR/%plugin_name%.php" ]; then
    # Already renamed by sed, just need to check
    true
fi

if [ -n "$REQUIREMENTS" ] && [ -f "$TEMPLATE_MAIN" ]; then
    echo "Restoring the template's requirements in the plugin header..."
    while IFS= read -r line; do
        key="${line%%:*}"
        sed -i "s|^${key//\*/\\*}:.*|${line}|" "$TEMPLATE_MAIN"
    done <<< "$REQUIREMENTS"
fi

echo "Checking nothing kept the code name..."

# The replacement rules above are a hand-maintained list, and theme-default
# shipped a tag where a whole new directory slipped past its equivalent: the
# blocks added under resources/views went out naming the development theme.
# Nothing there noticed, because nothing looked afterwards. This looks.
if leaked=$(grep -rn "${CODE_NAME}\|${CODE_STUDLY}\|${CODE_FUNCTION}\|${CODE_UPPER}" "$TARGET_DIR" \
        --exclude-dir=.git --exclude-dir=.github --exclude-dir=bin --exclude-dir=node_modules --exclude=package-plugin.sh); then
    echo ""
    echo "Error: the code name survived packaging:"
    echo "$leaked"
    exit 1
fi

echo ""
echo "=== Done ==="
echo ""
echo "Review changes:"
echo "  cd $TARGET_DIR && git diff"
echo ""
echo "Then commit, tag and push:"
echo "  git add -A && git commit -m 'feat: update plugin template'"
echo "  git tag x.y.z && git push origin main --tags"