#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"
: "${LCFA_WP_ROOT:?Set LCFA_WP_ROOT to the WordPress public directory}"
: "${LCFA_PHP_BIN:?Set LCFA_PHP_BIN to the Local PHP binary}"
: "${LCFA_PHP_INI:?Set LCFA_PHP_INI to the Local site php.ini}"

"${LCFA_PHP_BIN}" -c "${LCFA_PHP_INI}" "${SCRIPT_DIR}/build-site.php"
"${LCFA_PHP_BIN}" -c "${LCFA_PHP_INI}" "${SCRIPT_DIR}/verify-rollback.php"

THEME_ROOT="${LCFA_WP_ROOT}/wp-content/themes/picostrap5-child-base"
COMPILED_CSS="${TMPDIR:-/private/tmp}/lcfa-houseflow-bundle.css"
"${PLUGIN_ROOT}/mcp/node_modules/.bin/sass" \
  --style=compressed \
  --no-source-map \
  "${THEME_ROOT}/sass/main.scss" \
  "${COMPILED_CSS}"

LCFA_COMPILED_CSS="${COMPILED_CSS}" "${LCFA_PHP_BIN}" -c "${LCFA_PHP_INI}" "${SCRIPT_DIR}/complete-compile.php"

if [[ "${LCFA_RUN_VISUAL_CHECK:-1}" == "1" ]]; then
  NODE_PATH="${PLUGIN_ROOT}/mcp/node_modules" \
    LCFA_HOUSEFLOW_URL="${LCFA_HOUSEFLOW_URL:-http://test-ai-forge.local}" \
    node "${SCRIPT_DIR}/visual-check.cjs"
fi
