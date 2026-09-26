#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist"
STAGE_DIR="${DIST_DIR}/.stage"
PACKAGE_DIR="${STAGE_DIR}/livecanvas-forge-ai"
ZIP_PATH="${DIST_DIR}/livecanvas-forge-ai.zip"

# The wizard uses the archive shipped on this site, so setup does not depend on
# an unpublished npm version. npm pack includes only the package files allowlist.
mkdir -p "${ROOT_DIR}/assets/runtime"
(
  cd "${ROOT_DIR}/mcp"
  npm pack --ignore-scripts --cache "${DIST_DIR}/.npm-cache" --pack-destination "${ROOT_DIR}/assets/runtime"
)

rm -rf "${STAGE_DIR}"
mkdir -p "${PACKAGE_DIR}"

copy_into_package() {
  local source_path="$1"
  local target_path="${PACKAGE_DIR}/$(basename "${source_path}")"

  if [ -d "${source_path}" ]; then
    cp -R "${source_path}" "${target_path}"
  else
    cp "${source_path}" "${target_path}"
  fi
}

copy_into_package "${ROOT_DIR}/livecanvas-forge-ai.php"
copy_into_package "${ROOT_DIR}/README.md"
copy_into_package "${ROOT_DIR}/LICENSE.md"
copy_into_package "${ROOT_DIR}/composer.json"
copy_into_package "${ROOT_DIR}/composer.lock"
copy_into_package "${ROOT_DIR}/assets"
copy_into_package "${ROOT_DIR}/includes"
copy_into_package "${ROOT_DIR}/workflows"
copy_into_package "${ROOT_DIR}/skills"
copy_into_package "${ROOT_DIR}/mcp"
copy_into_package "${ROOT_DIR}/vendor"

# Keep the distributed ZIP deterministic: the plugin needs exactly the runtime
# matching its declared package version, never a collection of prior dev builds.
RUNTIME_VERSION="$(node -p "require('${ROOT_DIR}/mcp/package.json').version")"
find "${PACKAGE_DIR}/assets/runtime" -type f -name 'livecanvas-ai-bridge-mcp-*.tgz' \
  ! -name "livecanvas-ai-bridge-mcp-${RUNTIME_VERSION}.tgz" -delete

# Theme packages and screenshots are served from the remote catalog. Keep only
# the bundled catalog as an offline fallback so the plugin ZIP stays below
# common WordPress/nginx upload limits.
mkdir -p "${PACKAGE_DIR}/examples/theme-library"
cp "${ROOT_DIR}/examples/theme-library/catalog.json" "${PACKAGE_DIR}/examples/theme-library/catalog.json"

mkdir -p "${PACKAGE_DIR}/docs"
cp "${ROOT_DIR}/docs/coding-agent-setup.html" "${PACKAGE_DIR}/docs/coding-agent-setup.html"
cp "${ROOT_DIR}/docs/release-notes-0.2.0-beta.8.md" "${PACKAGE_DIR}/docs/release-notes-0.2.0-beta.8.md"
cp "${ROOT_DIR}/docs/verified-site-write-workflow.md" "${PACKAGE_DIR}/docs/verified-site-write-workflow.md"
cp "${ROOT_DIR}/docs/content-changesets.md" "${PACKAGE_DIR}/docs/content-changesets.md"
cp "${ROOT_DIR}/docs/private-conversations.md" "${PACKAGE_DIR}/docs/private-conversations.md"
cp "${ROOT_DIR}/docs/file-changesets.md" "${PACKAGE_DIR}/docs/file-changesets.md"
cp "${ROOT_DIR}/docs/picostrap-assets.md" "${PACKAGE_DIR}/docs/picostrap-assets.md"
cp "${ROOT_DIR}/docs/windpress-assets.md" "${PACKAGE_DIR}/docs/windpress-assets.md"
cp "${ROOT_DIR}/docs/local-transport.md" "${PACKAGE_DIR}/docs/local-transport.md"
cp "${ROOT_DIR}/docs/site-knowledge.md" "${PACKAGE_DIR}/docs/site-knowledge.md"
cp "${ROOT_DIR}/docs/desktop-delivery.md" "${PACKAGE_DIR}/docs/desktop-delivery.md"
cp "${ROOT_DIR}/docs/codex-thread-protocol.md" "${PACKAGE_DIR}/docs/codex-thread-protocol.md"
cp "${ROOT_DIR}/docs/codex-desktop-qualification.md" "${PACKAGE_DIR}/docs/codex-desktop-qualification.md"

find "${PACKAGE_DIR}" \
  \( -name '.DS_Store' -o -name '*.log' \) \
  -delete

rm -rf \
  "${PACKAGE_DIR}/mcp/node_modules" \
  "${PACKAGE_DIR}/mcp/.lcfa-backups" \
  "${PACKAGE_DIR}/mcp/tests" \
  "${PACKAGE_DIR}/mcp/.DS_Store" \
  "${PACKAGE_DIR}/vendor/bin"

# Picostrap compilation runs from the bundled local MCP bridge. Ship only the
# pure-JavaScript Dart Sass runtime and its required dependencies; browser and
# development dependencies remain excluded from the WordPress package.
SASS_RUNTIME_PACKAGES=(
  jsonc-parser
  smol-toml
  sass
  chokidar
  readdirp
  immutable
  source-map-js
)

mkdir -p "${PACKAGE_DIR}/mcp/node_modules"
for package_name in "${SASS_RUNTIME_PACKAGES[@]}"; do
  source_path="${ROOT_DIR}/mcp/node_modules/${package_name}"
  if [ ! -d "${source_path}" ]; then
    echo "Missing MCP Sass runtime dependency: ${package_name}. Run 'cd mcp && npm ci' before building." >&2
    exit 1
  fi

  cp -R "${source_path}" "${PACKAGE_DIR}/mcp/node_modules/${package_name}"
done

mkdir -p "${DIST_DIR}"
rm -f "${ZIP_PATH}"

(
  cd "${STAGE_DIR}"
  zip -qr "${ZIP_PATH}" "livecanvas-forge-ai"
)

rm -rf "${STAGE_DIR}"

echo "Built ${ZIP_PATH}"
