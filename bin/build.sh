#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="vintrica-vignette-form"
VERSION="$(grep "define( 'VINTRICA_VERSION'" "${ROOT_DIR}/vintrica-vignette-form.php" | sed -E "s/.*'([^']+)'.*/\1/")"
BUILD_DIR="${ROOT_DIR}/build/${PLUGIN_SLUG}"
DIST_DIR="${ROOT_DIR}/dist"
ZIP_FILE="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

echo "Running verification..."
if command -v php >/dev/null 2>&1; then
	php "${ROOT_DIR}/bin/verify.php"
else
	echo "Warning: PHP CLI not found. Skipping automated verification."
	echo "Run 'php bin/verify.php' before release when PHP is available."
fi

echo "Preparing production build ${PLUGIN_SLUG} ${VERSION}..."
rm -rf "${BUILD_DIR}" "${DIST_DIR}"
mkdir -p "${BUILD_DIR}" "${DIST_DIR}"

rsync -a \
	--exclude '.git/' \
	--exclude '.gitignore' \
	--exclude 'build/' \
	--exclude 'dist/' \
	--exclude 'bin/' \
	--exclude '.DS_Store' \
	"${ROOT_DIR}/" "${BUILD_DIR}/"

(
	cd "${ROOT_DIR}/build"
	zip -r "${ZIP_FILE}" "${PLUGIN_SLUG}" \
		-x "*.DS_Store" \
		-x "*/.git/*" \
		-x "*/bin/*" \
		-x "*/build/*" \
		-x "*/dist/*"
)

echo "Production ZIP created: ${ZIP_FILE}"
unzip -l "${ZIP_FILE}" | head -n 20
