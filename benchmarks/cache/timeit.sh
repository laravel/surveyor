#!/bin/bash
# Times cold, warm and warm-plus-edit for a given Surveyor source tree.
#
# Usage: timeit.sh <label> [src-dir] [repeats] [target]
#
# Copies <src-dir> into the app's vendor directory, then for each repeat builds
# from an empty cache, builds again with nothing changed, and builds once more
# after appending a method to one model. The edited file is restored from a copy
# rather than through git, so uncommitted work in the app survives.

set -u

S="$(cd "$(dirname "$0")" && pwd)"
APP=${SURVEYOR_BENCH_APP:?set SURVEYOR_BENCH_APP to the application to build}
LABEL=$1
SRC=${2:-$SUR/src}
RUNS=${3:-2}
TARGET=$APP/${4:-app/Models/Instance.php}
BACKUP=$S/tm-$LABEL-$(basename "${4:-Instance.php}").orig

rsync -a --delete "$SRC/" "$APP/vendor/laravel/surveyor/src/"
cp "$TARGET" "$BACKUP"
trap 'cp "$BACKUP" "$TARGET"' EXIT

cd "$APP" || exit 1

time_run() {
  local cache=$1 out=$2 start end
  rm -rf "$out"; mkdir -p "$out"
  start=$(php -r 'echo microtime(true);')
  WAYFINDER_CACHE_DIRECTORY=$cache php artisan wayfinder:generate --path="$out" > /dev/null 2>&1
  end=$(php -r 'echo microtime(true);')
  php -r "printf('%.2f', $end - $start);"
}

printf '%-14s %3s %7s %7s %7s %8s %6s %10s\n' label run cold warm edit entries mb rewritten

for i in $(seq 1 "$RUNS"); do
  CACHE=$S/tm-$LABEL-cache
  rm -rf "$CACHE"; mkdir -p "$CACHE"

  cold=$(time_run "$CACHE" "$S/tm-out")
  entries=$(find "$CACHE" -name '*.cache' | wc -l | tr -d ' ')
  size=$(du -sm "$CACHE" | cut -f1 | tr -d ' ')

  warm=$(time_run "$CACHE" "$S/tm-out")

  before=$(mktemp)
  find "$CACHE" -name '*.cache' -exec stat -f '%N %m' {} \; | sort > "$before"

  sleep 1
  python3 - "$TARGET" <<'PY'
import sys
p = sys.argv[1]
s = open(p).read()
i = s.rstrip().rfind('\n}')
probe = "\n    public function benchProbeRelation(): HasOne\n    {\n        return $this->hasOne(Daemon::class);\n    }\n"
open(p, 'w').write(s[:i] + probe + s[i:])
PY

  edit=$(time_run "$CACHE" "$S/tm-out")

  after=$(mktemp)
  find "$CACHE" -name '*.cache' -exec stat -f '%N %m' {} \; | sort > "$after"
  rewritten=$(comm -13 "$before" "$after" | wc -l | tr -d ' ')

  cp "$BACKUP" "$TARGET"
  rm -f "$before" "$after"

  printf '%-14s %3s %7s %7s %7s %8s %6s %10s\n' "$LABEL" "$i" "$cold" "$warm" "$edit" "$entries" "$size" "$rewritten"
done
