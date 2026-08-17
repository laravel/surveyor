#!/bin/bash
# Runs each kind of source change through a warm cache and a cold one, and
# reports whether the two agree.
#
# Covers the shapes the earlier sweeps never touched: removing a method,
# editing in place, adding a file, deleting a file, renaming a file.
#
# Usage: shapesweep.sh <label>

set -u

S="$(cd "$(dirname "$0")" && pwd)"
APP=/Users/joetannenbaum/Herd/cloud
LABEL=$1
KEEP=$S/sh-$LABEL
CACHE_W=$S/sh-cache-w
CACHE_C=$S/sh-cache-c

SHAPES=(append-method remove-method edit-enum-case add-file remove-file rename-file)

cd "$APP" || exit 1

if ! git diff --quiet; then
  echo "ABORT: tracked files in cloud are modified"
  exit 1
fi

cleanup() {
  cd "$APP" && git checkout -- . 2>/dev/null
  python3 "$S/mutate.py" cleanup apply
}
trap cleanup EXIT

rm -rf "$KEEP"; mkdir -p "$KEEP"

run() {
  local cache=$1 out=$2
  rm -rf "$out"; mkdir -p "$out"
  WAYFINDER_CACHE_DIRECTORY=$cache php artisan wayfinder:generate --path="$out" > /dev/null 2>&1
}

mask() {
  sed -E 's/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?Z?/<TS>/g' "$1"
}

printf '%-16s %14s %8s %s\n' shape warm-vs-cold raw effect

for shape in "${SHAPES[@]}"; do
  cd "$APP" && git checkout -- . 2>/dev/null
  python3 "$S/mutate.py" cleanup apply

  python3 "$S/mutate.py" "$shape" pre

  rm -rf "$CACHE_W" "$CACHE_C"; mkdir -p "$CACHE_W" "$CACHE_C"
  run "$CACHE_W" "$S/sh-out-base"
  mask "$S/sh-out-base/types.d.ts" > "$KEEP/$shape.base"

  sleep 1

  python3 "$S/mutate.py" "$shape" apply

  run "$CACHE_W" "$S/sh-out-w"
  run "$CACHE_C" "$S/sh-out-c"

  mask "$S/sh-out-w/types.d.ts" > "$KEEP/$shape.warm"
  mask "$S/sh-out-c/types.d.ts" > "$KEEP/$shape.cold"

  sorted=$(diff <(sort "$KEEP/$shape.cold") <(sort "$KEEP/$shape.warm") | grep -c '^[<>]')
  raw=$(diff "$KEEP/$shape.cold" "$KEEP/$shape.warm" | grep -c '^[<>]')
  moved=$(diff "$KEEP/$shape.base" "$KEEP/$shape.cold" | grep -c '^[<>]')

  effect="changed $moved lines"
  [ "$moved" -eq 0 ] && effect="NO EFFECT ON OUTPUT (vacuous test)"

  printf '%-16s %14s %8s %s\n' "$shape" "$sorted" "$raw" "$effect"

  if [ "$sorted" -gt 0 ]; then
    diff <(sort "$KEEP/$shape.cold") <(sort "$KEEP/$shape.warm") | grep '^[<>]' \
      | grep -v '@see' | cut -c1-130 | head -6 | sed 's/^/      /'
  fi
done

echo
echo "kept: $KEEP"
