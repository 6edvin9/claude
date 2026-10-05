#!/bin/sh
# Copies the storefront runtime from plugin-src/ into the release plugin and zips it.
# Usage: sh build-release.sh [path-to-release-data.zip]
set -e
cd "$(dirname "$0")"
R=aluglobus-release
for f in storefront.php storefront.css storefront.js preservation.php variations.php single-product.php catalog.css manifest.json; do
  cp ../plugin-src/$f $R/runtime/$f
done
# drop the staging-only read-only inventory endpoint from the runtime copy
python3 - "$R/runtime/storefront.php" <<'PY'
import sys,re
f=sys.argv[1];s=open(f).read()
i=s.find('// Read-only inventory for the live release')
if i>=0:
    j=s.find('wp_send_json_success($o);});',i)
    s=s[:i]+s[j+len('wp_send_json_success($o);});'):]
open(f,'w').write(s)
PY
if [ -n "$1" ]; then rm -rf $R/data/bundle.json.gz $R/data/media; unzip -q -o "$1" -d $R; fi
for f in $(find $R -name '*.php'); do php -l "$f" >/dev/null; done
rm -f aluglobus-release.zip
zip -qr aluglobus-release.zip $R -x '*.DS_Store'
ls -la aluglobus-release.zip
