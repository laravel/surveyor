#!/bin/bash
# Does re-analysing the same bytes give the same answer?
#
# Builds a cold cache, touches one file without changing it, then rebuilds. Any
# entry whose canonical surface changed between the two runs is a flap: the same
# source analysed twice, two answers.
#
# Usage: determinism.sh <label> [target-relative-path]
#
# SURVEYOR_BENCH_APP names the application to build.

set -u

S="$(cd "$(dirname "$0")" && pwd)"
SUR="$(cd "$S/../.." && pwd)"
APP=${SURVEYOR_BENCH_APP:?set SURVEYOR_BENCH_APP to the application to build}
LABEL=$1
TARGET=${2:-$(python3 "$S/mutate.py" targets list | awk '{print $1}')}

# surfacedump.php builds the cached objects back up, so it boots the same
# autoloader the application does.
export SURFACE_AUTOLOAD="$APP/vendor/autoload.php"

CACHE=$S/dt-$LABEL-cache
KEEP=$S/dt-$LABEL

cd "$APP" || exit 1

# Nothing here edits app source, it only touches a file, so a dirty tree is
# reported rather than treated as fatal. The app need not be a repository at all.
if git rev-parse --git-dir > /dev/null 2>&1 && ! git diff --quiet; then
  echo "note: cloud has modified tracked files:"
  git diff --name-only | sed 's/^/  /'
fi

# The app loads Surveyor from vendor, so the working tree has to be copied in.
# Some checkouts symlink the package straight at a working copy, in which case
# there is nothing to copy and copying would mean writing a directory onto
# itself.
if [ "$(cd "$SUR/src" && pwd -P)" != "$(cd "$APP/vendor/laravel/surveyor/src" && pwd -P)" ]; then
  rsync -a --delete "$SUR/src/" "$APP/vendor/laravel/surveyor/src/"
fi

rm -rf "$KEEP"; mkdir -p "$KEEP"
rm -rf "$CACHE"; mkdir -p "$CACHE"

run() {
  local out=$1
  rm -rf "$out"; mkdir -p "$out"
  WAYFINDER_CACHE_DIRECTORY=$CACHE php artisan wayfinder:generate --path="$out" > /dev/null 2>&1
}

echo "cold build..."
run "$KEEP/out-cold"
php "$S/surfacedump.php" "$CACHE" > "$KEEP/dump-a"
php "$S/surfacedump.php" "$CACHE" --detail > "$KEEP/detail-a"
php "$S/surfacedump.php" "$CACHE" --fields --detail > "$KEEP/fields-a"
find "$CACHE" -name '*.cache' -exec stat -f '%N %m' {} \; | sort > "$KEEP/mtimes-a"
echo "  $(wc -l < "$KEEP/dump-a" | tr -d ' ') entries"

sleep 1
touch "$APP/$TARGET"

echo "warm build after touching $TARGET..."
run "$KEEP/out-warm"
php "$S/surfacedump.php" "$CACHE" > "$KEEP/dump-b"
php "$S/surfacedump.php" "$CACHE" --detail > "$KEEP/detail-b"
php "$S/surfacedump.php" "$CACHE" --fields --detail > "$KEEP/fields-b"
find "$CACHE" -name '*.cache' -exec stat -f '%N %m' {} \; | sort > "$KEEP/mtimes-b"

rewritten=$(comm -13 "$KEEP/mtimes-a" "$KEEP/mtimes-b" | wc -l | tr -d ' ')

mask() {
  find "$1" -type f -exec sed -i '' -E 's/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?Z?/<TS>/g' {} \;
}
mask "$KEEP/out-cold"; mask "$KEEP/out-warm"
outdiff=$(diff -r "$KEEP/out-cold" "$KEEP/out-warm" | grep -c '^[<>]')

python3 - "$KEEP/dump-a" "$KEEP/dump-b" "$rewritten" "$outdiff" <<'PY'
import sys

a_path, b_path, rewritten, outdiff = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4]

def load(path):
    rows = {}
    for line in open(path):
        p, surface, entry, deps = line.rstrip("\n").split("\t")
        rows[p] = (surface, entry)
    return rows

A, B = load(a_path), load(b_path)
shared = set(A) & set(B)

surface = sorted(p for p in shared if A[p][0] != B[p][0])
entry = sorted(p for p in shared if A[p][1] != B[p][1])

print()
print(f"entries          {len(A)} cold, {len(B)} warm")
print(f"only in cold     {len(set(A) - set(B))}")
print(f"only in warm     {len(set(B) - set(A))}")
print(f"rewritten        {rewritten}")
print(f"entry flaps      {len(entry)}")
print(f"surface flaps    {len(surface)}")
print(f"output diff      {outdiff} lines")
print()
for p in surface:
    print("  surface", p)
for p in entry:
    if p not in surface:
        print("  entry  ", p)
PY

echo "fields that moved (entry, count):"
diff "$KEEP/fields-a" "$KEEP/fields-b" | grep '^<' | awk '{print $4}' | sort | uniq -c | sort -rn | sed 's/^/  /'

echo
echo "kept: $KEEP"
echo "surface detail: diff <(grep -F <path> $KEEP/detail-a) <(grep -F <path> $KEEP/detail-b)"
