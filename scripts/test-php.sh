#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

for test_file in "${ROOT_DIR}"/tests/php/*.php; do
  if [ "$(basename "${test_file}")" = "package_dist_phase1.php" ]; then
    continue
  fi

  php "${test_file}"
done
