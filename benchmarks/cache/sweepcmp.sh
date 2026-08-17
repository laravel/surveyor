#!/bin/bash
# For each target file: build a cold cache, edit the file, then generate twice
# (warm cache vs empty cache) and report how far apart the two outputs are.
#
# Every target gets its own freshly built cache, so results do not carry over
# between targets.
#
# Usage: sweepcmp.sh <label>
#
# Keeps the masked types.d.ts of both runs per target, so outputs can later be
# compared across labels.

set -u

S="$(cd "$(dirname "$0")" && pwd)"
APP=/Users/joetannenbaum/Herd/cloud
LABEL=$1
KEEP=$S/sw-$LABEL
CACHE_W=$S/sw-cache-w
CACHE_C=$S/sw-cache-c
OUT_W=$S/sw-out-w
OUT_C=$S/sw-out-c

TARGETS=(
  app/Models/Instance.php
  app/Models/Environment.php
  app/Models/Application.php
  app/Models/Organization.php
  app/Models/User.php
  app/Enums/InstanceType.php
  app/Enums/AlertType.php
  app/Support/ClickHouse/Builder.php
  app/Support/Metrics/ManagedQueueChartQuery.php
  app/Actions/ReplicateEnvironment.php
  app/Http/Resources/Dashboard/EnvironmentResource.php
  app/Http/Resources/Dashboard/CacheResource.php
)

cd "$APP" || exit 1

if ! git diff --quiet; then
  echo "ABORT: tracked files in cloud are modified"
  exit 1
fi

trap 'cd "$APP" && git checkout -- . 2>/dev/null' EXIT

rm -rf "$KEEP"; mkdir -p "$KEEP"

run() {
  local cache=$1 out=$2
  rm -rf "$out"; mkdir -p "$out"
  WAYFINDER_CACHE_DIRECTORY=$cache php artisan wayfinder:generate --path="$out" > /dev/null 2>&1
}

mask() {
  sed -E 's/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?Z?/<TS>/g' "$1"
}

printf '%-56s %8s %8s %s\n' target warm-vs-cold raw note

for target in "${TARGETS[@]}"; do
  slug=$(echo "$target" | tr '/' '_')

  rm -rf "$CACHE_W" "$CACHE_C"; mkdir -p "$CACHE_W" "$CACHE_C"
  run "$CACHE_W" "$S/sw-out-base"
  mask "$S/sw-out-base/types.d.ts" > "$KEEP/$slug.base"

  sleep 1

  python3 - "$APP/$target" <<'PY'
import sys, re
p = sys.argv[1]
s = open(p).read()
i = s.rstrip().rfind('\n}')
if i == -1:
    sys.exit(9)

if '/Enums/' in p and re.search(r'^\s*case\s+\w+', s, re.M):
    m = list(re.finditer(r'^(\s*)case\s+(\w+)\s*=\s*(.+?);\s*$', s, re.M))[-1]
    indent, val = m.group(1), m.group(3)
    lit = "'sweep_probe'" if val.strip().startswith("'") else "999999"
    s = s[:m.end()] + f"\n{indent}case SweepProbeCase = {lit};" + s[m.end():]
elif 'Attribute;' in s:
    probe = "\n    protected function sweepProbeAccessor(): Attribute\n    {\n        return Attribute::get(fn (): string => 'probe');\n    }\n"
    s = s[:i] + probe + s[i:]
else:
    probe = "\n    public function sweepProbeMethod(): \\App\\Models\\User\n    {\n        return new \\App\\Models\\User;\n    }\n"
    s = s[:i] + probe + s[i:]

open(p, 'w').write(s)
PY
  if [ $? -ne 0 ]; then printf '%-56s %8s\n' "$target" skip; continue; fi

  run "$CACHE_W" "$OUT_W"
  run "$CACHE_C" "$OUT_C"

  git checkout -- "$target"

  mask "$OUT_W/types.d.ts" > "$KEEP/$slug.warm"
  mask "$OUT_C/types.d.ts" > "$KEEP/$slug.cold"

  sorted=$(diff <(sort "$KEEP/$slug.cold") <(sort "$KEEP/$slug.warm") | grep -c '^[<>]')
  raw=$(diff "$KEEP/$slug.cold" "$KEEP/$slug.warm" | grep -c '^[<>]')
  moved=$(diff "$KEEP/$slug.base" "$KEEP/$slug.cold" | grep -c '^[<>]')

  note=""
  [ "$moved" -eq 0 ] && note="(edit changed no output)"

  printf '%-56s %8s %8s %s\n' "$target" "$sorted" "$raw" "$note"

  if [ "$sorted" -gt 0 ]; then
    diff <(sort "$KEEP/$slug.cold") <(sort "$KEEP/$slug.warm") | grep '^[<>]' \
      | grep -v '@see\|^\s*[<>]\s*\*/\?$\|^[<>] *$' | cut -c1-140 | head -6 | sed 's/^/      /'
  fi
done

echo
echo "kept: $KEEP"
