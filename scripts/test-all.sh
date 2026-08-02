#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

bash "${ROOT_DIR}/scripts/test-php.sh"
bash "${ROOT_DIR}/scripts/test-node.sh"
bash "${ROOT_DIR}/scripts/test-js.sh"
