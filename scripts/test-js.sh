#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

for test_file in "${ROOT_DIR}"/tests/js/*.js; do
  if [ "$(basename "${test_file}")" = "editor_live_local_smoke.js" ]; then
    continue
  fi

  node "${test_file}"
done
