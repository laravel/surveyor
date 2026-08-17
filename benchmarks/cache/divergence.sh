#!/bin/bash
# Does a warm-cache run after one edit produce the same output as a cold run?
#
# Usage: divergence.sh <label> [target-relative-path]
#
# Builds a cold cache, edits one file, then generates twice: once against the
# warm cache and once from scratch. Leaves both caches and both outputs behind
# for inspection.

set -u

S="$(cd "$(dirname "$0")" && pwd)"
APP=/Users/joetannenbaum/Herd/cloud
LABEL=$1
TARGET=${2:-app/Models/Instance.php}

CACHE_WARM=$S/dv-$LABEL-cache-warm
CACHE_COLD=$S/dv-$LABEL-cache-cold
OUT_BASE=$S/dv-$LABEL-base
OUT_WARM=$S/dv-$LABEL-warm
OUT_COLD=$S/dv-$LABEL-cold

cd "$APP" || exit 1

if ! git diff --quiet; then
  echo "ABORT: tracked files in cloud are modified"
  exit 1
fi

trap 'cd "$APP" && git checkout -- . 2>/dev/null' EXIT

run() {
  local cache=$1 out=$2
  rm -rf "$out"; mkdir -p "$out"
  WAYFINDER_CACHE_DIRECTORY=$cache php artisan wayfinder:generate --path="$out" > /dev/null 2>&1
}

rm -rf "$CACHE_WARM" "$CACHE_COLD"; mkdir -p "$CACHE_WARM" "$CACHE_COLD"

echo "building cold cache..."
run "$CACHE_WARM" "$OUT_BASE"
echo "  $(ls "$CACHE_WARM" | grep -c '\.cache') entries"

sleep 1

python3 - "$APP/$TARGET" <<'PY'
import sys
p = sys.argv[1]
s = open(p).read()
i = s.rstrip().rfind('\n}')
probe = "\n    protected function sweepProbeAccessor(): Attribute\n    {\n        return Attribute::get(fn (): string => 'probe');\n    }\n"
open(p, 'w').write(s[:i] + probe + s[i:])
PY

echo "warm run (cache reused)..."
run "$CACHE_WARM" "$OUT_WARM"

echo "cold run (empty cache)..."
run "$CACHE_COLD" "$OUT_COLD"

cd "$APP" && git checkout -- "$TARGET"

mask() {
  rm -rf "$2"; cp -R "$1" "$2"
  find "$2" -type f -exec sed -i '' -E 's/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?Z?/<TS>/g' {} \;
}

mask "$OUT_WARM" "$S/dv-$LABEL-warm-masked"
mask "$OUT_COLD" "$S/dv-$LABEL-cold-masked"

echo
echo "=== output diff (cold < , warm > ) ==="
diff -r "$S/dv-$LABEL-cold-masked" "$S/dv-$LABEL-warm-masked" > "$S/dv-$LABEL.diff" 2>&1
raw=$(grep -c '^[<>]' "$S/dv-$LABEL.diff")
sorted=$(diff <(sort "$S/dv-$LABEL-cold-masked/types.d.ts") <(sort "$S/dv-$LABEL-warm-masked/types.d.ts") | grep -c '^[<>]')
echo "raw lines: $raw   sorted (order-insensitive) lines: $sorted"
echo
diff <(sort "$S/dv-$LABEL-cold-masked/types.d.ts") <(sort "$S/dv-$LABEL-warm-masked/types.d.ts") | grep '^[<>]' | cut -c1-150
echo
echo "other differing files:"
diff -rq "$S/dv-$LABEL-cold-masked" "$S/dv-$LABEL-warm-masked" | grep -v 'types.d.ts'
echo
echo "full diff: $S/dv-$LABEL.diff"
echo "caches: $CACHE_WARM (warm) / $CACHE_COLD (cold)"
