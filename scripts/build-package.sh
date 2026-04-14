#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
LIBS_DIR="$(cd "${PLUGIN_DIR}/.." && pwd)"
DIST_SLUG="xpressui-wordpress-bridge-pro"
ZIP_NAME="${1:-${DIST_SLUG}.zip}"
OUTPUT_PATH="${LIBS_DIR}/${ZIP_NAME}"
STAGE_DIR="$(mktemp -d /tmp/xpressui-bridge-pro-build.XXXXXX)"

cleanup() {
  rm -rf "${STAGE_DIR}"
}
trap cleanup EXIT

rm -f "${OUTPUT_PATH}"

cp -R "${PLUGIN_DIR}" "${STAGE_DIR}/${DIST_SLUG}"

rm -rf "${STAGE_DIR:?}/${DIST_SLUG}/.git" \
       "${STAGE_DIR:?}/${DIST_SLUG}/.github" \
       "${STAGE_DIR:?}/${DIST_SLUG}/.wordpress-org" \
       "${STAGE_DIR:?}/${DIST_SLUG}/scripts" \
       "${STAGE_DIR:?}/${DIST_SLUG}/node_modules" \
       "${STAGE_DIR:?}/${DIST_SLUG}/xpressui-src"
rm -f "${STAGE_DIR:?}/${DIST_SLUG}/.gitignore" \
      "${STAGE_DIR:?}/${DIST_SLUG}/.gitkeep" \
      "${STAGE_DIR:?}/${DIST_SLUG}/.gitmodules" \
      "${STAGE_DIR:?}/${DIST_SLUG}/package.json" \
      "${STAGE_DIR:?}/${DIST_SLUG}/package-lock.json" \
      "${STAGE_DIR:?}/${DIST_SLUG}/xpressui-version.txt" \
      "${STAGE_DIR:?}/${DIST_SLUG}/README.md"

cd "${STAGE_DIR}"
zip -rq "${OUTPUT_PATH}" "${DIST_SLUG}"

echo "${OUTPUT_PATH}"
