#!/bin/bash
# Builds an app's generated output twice — once with the Surveyor working-tree
# change applied, once without — and diffs the two.
#
# Works off the uncommitted change rather than branches, so nothing is committed
# and the change is restored either way.
#
# Usage: patchdiff.sh <label> <app-path> [file-to-toggle]

set -u

S="$(cd "$(dirname "$0")" && pwd)"
SUR=/Users/joetannenbaum/Dev/surveyor
LABEL=$1
APP=$2
FILE=${3:-src/NodeResolvers/Expr/Ternary.php}
PATCH=$S/pd-$LABEL.patch

if [ -z "$(git -C "$SUR" diff -- "$FILE")" ]; then
  echo "ABORT: no working-tree change in $FILE to toggle"
  exit 1
fi

git -C "$SUR" diff -- "$FILE" > "$PATCH"

restore() {
  git -C "$SUR" checkout -- "$FILE" 2>/dev/null
  git -C "$SUR" apply "$PATCH" 2>/dev/null
}
trap restore EXIT

build() {
  local out=$1 cache=$2
  rm -rf "$out" "$cache"; mkdir -p "$out" "$cache"
  (cd "$APP" && WAYFINDER_CACHE_DIRECTORY=$cache php artisan wayfinder:generate --path="$out" > /dev/null 2>&1)
  rm -rf "$out-masked"; cp -R "$out" "$out-masked"
  find "$out-masked" -type f -exec sed -i '' -E 's/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?Z?/<TS>/g' {} \;
}

echo "building WITH the fix ..."
build "$S/pd-$LABEL-fix" "$S/pd-$LABEL-cache-a"

echo "reverting and building WITHOUT the fix ..."
git -C "$SUR" checkout -- "$FILE"
build "$S/pd-$LABEL-main" "$S/pd-$LABEL-cache-b"

git -C "$SUR" apply "$PATCH"

echo
echo "=== $LABEL cold output: without fix (<) vs with fix (>) ==="
if [ ! -s "$S/pd-$LABEL-main-masked/types.d.ts" ] || [ ! -s "$S/pd-$LABEL-fix-masked/types.d.ts" ]; then
  echo "WARNING: one or both builds produced no types.d.ts"
  ls -la "$S/pd-$LABEL-main-masked" "$S/pd-$LABEL-fix-masked" 2>&1 | head -20
  exit 1
fi

diff -r "$S/pd-$LABEL-main-masked" "$S/pd-$LABEL-fix-masked" > "$S/pd-$LABEL.diff" 2>&1
echo "raw lines:    $(grep -c '^[<>]' "$S/pd-$LABEL.diff")"
echo "declarations: $(python3 "$S/fielddiff.py" "$S/pd-$LABEL-main-masked/types.d.ts" "$S/pd-$LABEL-fix-masked/types.d.ts" | head -2 | tail -1)"
echo
python3 "$S/fielddiff.py" "$S/pd-$LABEL-main-masked/types.d.ts" "$S/pd-$LABEL-fix-masked/types.d.ts"
