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
SUR="$(cd "$S/../.." && pwd)"
APP=${SURVEYOR_BENCH_APP:?set SURVEYOR_BENCH_APP to the application to build}
LABEL=$1
SRC=${2:-$SUR/src}
RUNS=${3:-2}
TARGET=$APP/${4:-$(python3 "$S/mutate.py" targets list | awk '{print $1}')}
BACKUP=$S/tm-$LABEL-$(basename "${4:-Instance.php}").orig

# Some checkouts symlink the package straight at a working copy, in which case
# there is nothing to copy and copying would mean writing a directory onto
# itself.
if [ "$(cd "$SRC" && pwd -P)" != "$(cd "$APP/vendor/laravel/surveyor/src" && pwd -P)" ]; then
  rsync -a --delete "$SRC/" "$APP/vendor/laravel/surveyor/src/"
fi
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
  python3 - "$TARGET" "$S" <<'PY'
import json
import os
import sys

path, here = sys.argv[1], sys.argv[2]
targets = json.load(open(os.path.join(here, 'targets.json')))
probe = targets[os.environ.get('SURVEYOR_BENCH_PRESET', 'default')]['relation']

s = open(path).read()
i = s.rstrip().rfind('\n}')
open(path, 'w').write(s[:i] + probe + s[i:])
PY

  edit=$(time_run "$CACHE" "$S/tm-out")

  after=$(mktemp)
  find "$CACHE" -name '*.cache' -exec stat -f '%N %m' {} \; | sort > "$after"
  rewritten=$(comm -13 "$before" "$after" | wc -l | tr -d ' ')

  cp "$BACKUP" "$TARGET"
  rm -f "$before" "$after"

  printf '%-14s %3s %7s %7s %7s %8s %6s %10s\n' "$LABEL" "$i" "$cold" "$warm" "$edit" "$entries" "$size" "$rewritten"
done
