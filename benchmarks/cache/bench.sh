#!/bin/bash
# Times the three scenarios that matter for whichever Surveyor is checked out,
# repeated so the numbers can be trusted:
#
#   cold        empty cache
#   warm        nothing changed since the cold build
#   warm+edit   one file touched since the cold build
#
# Prints one TSV row per repeat, plus the cache size and how many entries the
# edit forced back through the analyzer.
#
# Usage: bench.sh <label> [repeats]

set -u

S="$(cd "$(dirname "$0")" && pwd)"
APP=/Users/joetannenbaum/Herd/cloud
LABEL=$1
RUNS=${2:-3}
TARGET=app/Models/Instance.php

cd "$APP" || exit 1

if ! git diff --quiet; then
  echo "ABORT: tracked files in cloud are modified"
  exit 1
fi

trap 'cd "$APP" && git checkout -- . 2>/dev/null' EXIT

time_run() {
  local cache=$1 out=$2 start end
  rm -rf "$out"; mkdir -p "$out"
  start=$(php -r 'echo microtime(true);')
  WAYFINDER_CACHE_DIRECTORY=$cache php artisan wayfinder:generate --path="$out" > /dev/null 2>&1
  end=$(php -r 'echo microtime(true);')
  php -r "printf('%.2f', $end - $start);"
}

for i in $(seq 1 "$RUNS"); do
  CACHE=$S/bn-$LABEL-cache
  rm -rf "$CACHE"; mkdir -p "$CACHE"

  cold=$(time_run "$CACHE" "$S/bn-out")
  entries=$(find "$CACHE" -name '*.cache' | wc -l | tr -d ' ')
  size=$(du -sm "$CACHE" | cut -f1 | tr -d ' ')

  warm=$(time_run "$CACHE" "$S/bn-out")

  before=$(mktemp)
  find "$CACHE" -name '*.cache' -exec stat -f '%N %m' {} \; | sort > "$before"

  sleep 1
  python3 - "$APP/$TARGET" <<'PY'
import sys
p = sys.argv[1]
s = open(p).read()
i = s.rstrip().rfind('\n}')
probe = "\n    public function benchProbeRelation(): HasOne\n    {\n        return $this->hasOne(Daemon::class);\n    }\n"
open(p, 'w').write(s[:i] + probe + s[i:])
PY

  edit=$(time_run "$CACHE" "$S/bn-out")

  after=$(mktemp)
  find "$CACHE" -name '*.cache' -exec stat -f '%N %m' {} \; | sort > "$after"
  rewritten=$(comm -13 "$before" "$after" | wc -l | tr -d ' ')

  git checkout -- "$TARGET"
  rm -f "$before" "$after"

  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$LABEL" "$i" "$cold" "$warm" "$edit" "$entries" "$size" "$rewritten"
done
