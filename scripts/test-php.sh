#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

for test_file in "${ROOT_DIR}"/tests/php/*.php; do
  case "$(basename "${test_file}")" in
    package_dist_phase1.php|reflection-compat.php)
      continue
      ;;
  esac

  php "${test_file}"
done
