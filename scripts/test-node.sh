#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

for test_file in "${ROOT_DIR}"/mcp/tests/*.test.js; do
  node "${test_file}"
done
