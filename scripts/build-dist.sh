#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist"
STAGE_DIR="${DIST_DIR}/.stage"
PACKAGE_DIR="${STAGE_DIR}/livecanvas-forge-ai"
ZIP_PATH="${DIST_DIR}/livecanvas-forge-ai.zip"

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
copy_into_package "${ROOT_DIR}/examples"
copy_into_package "${ROOT_DIR}/includes"
copy_into_package "${ROOT_DIR}/mcp"
copy_into_package "${ROOT_DIR}/vendor"

mkdir -p "${PACKAGE_DIR}/docs"
cp "${ROOT_DIR}/docs/coding-agent-setup.html" "${PACKAGE_DIR}/docs/coding-agent-setup.html"

find "${PACKAGE_DIR}" \
  \( -name '.DS_Store' -o -name '*.log' \) \
  -delete

rm -rf \
  "${PACKAGE_DIR}/mcp/node_modules" \
  "${PACKAGE_DIR}/mcp/tests" \
  "${PACKAGE_DIR}/mcp/.DS_Store" \
  "${PACKAGE_DIR}/vendor/bin"

mkdir -p "${DIST_DIR}"
rm -f "${ZIP_PATH}"

(
  cd "${STAGE_DIR}"
  zip -qr "${ZIP_PATH}" "livecanvas-forge-ai"
)

rm -rf "${STAGE_DIR}"

echo "Built ${ZIP_PATH}"
