#!/usr/bin/env bash
# Builds dist/choir-plugin.zip and dist/cantata-theme.zip from the source trees.
# Runs the same guard the plugin has always been built with:
#   1. PHP lint on every .php file
#   2. every tcc_* function that is called must be defined somewhere in the plugin
#   3. every .js file must at least parse
# Usage: ./build.sh            (needs php and node on PATH)
set -euo pipefail
cd "$(dirname "$0")"

PHP="${PHP:-php}"
NODE="${NODE:-node}"
fail=0

echo "== PHP lint"
while IFS= read -r f; do
	"$PHP" -n -l "$f" >/dev/null || fail=1
done < <(find tilden-cantata tilden-choir tcc-cue-export -name '*.php')

echo "== undefined tcc_* calls"
grep -rhoE '\btcc_[a-z0-9_]+\s*\(' tilden-cantata --include='*.php' | sed -E 's/\s*\($//' | sort -u > /tmp/tcc_calls.txt
grep -rhoE 'function\s+tcc_[a-z0-9_]+' tilden-cantata --include='*.php' | awk '{print $2}' | sort -u > /tmp/tcc_defs.txt
missing=$(comm -23 /tmp/tcc_calls.txt /tmp/tcc_defs.txt || true)
if [ -n "$missing" ]; then
	echo "undefined: $missing"; fail=1
fi

echo "== JS parse"
while IFS= read -r f; do
	"$NODE" -e "new (require('vm').Script)(require('fs').readFileSync(process.argv[1],'utf8'))" "$f" || { echo "bad: $f"; fail=1; }
done < <(find tilden-cantata tilden-choir -name '*.js')

[ "$fail" -eq 0 ] || { echo "BUILD FAILED"; exit 1; }

echo "== zip"
mkdir -p dist
rm -f dist/choir-plugin.zip dist/cantata-theme.zip
zip -qr dist/choir-plugin.zip tilden-cantata -x '*.DS_Store'
zip -qr dist/cantata-theme.zip tilden-choir -x '*.DS_Store'
echo "plugin $(grep -m1 'Version:' tilden-cantata/tilden-cantata.php | awk '{print $NF}')  theme $(grep -m1 'Version:' tilden-choir/style.css | awk '{print $NF}')"
ls -la dist
