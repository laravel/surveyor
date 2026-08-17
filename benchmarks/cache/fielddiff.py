"""Pairs changed type declarations across two generated types.d.ts files and
reports which fields differ, so each change can be judged on its own.

Usage: python3 fielddiff.py <a.ts> <b.ts>
"""

import re
import sys

a_path, b_path = sys.argv[1], sys.argv[2]


def decls(path):
    """Map each `export type X = ...` line to its full text, keyed by name plus
    a slice of context so same-named types in different namespaces stay apart."""
    out = {}
    ns = []
    for raw in open(path):
        line = raw.rstrip("\n")
        stripped = line.strip()
        indent = len(line) - len(line.lstrip())
        while ns and ns[-1][0] >= indent:
            ns.pop()
        m = re.match(r"export namespace (\w+) \{", stripped)
        if m:
            ns.append((indent, m.group(1)))
            continue
        m = re.match(r"export type (\w+) = (.*)$", stripped)
        if m:
            key = ".".join(n for _, n in ns) + "." + m.group(1)
            out[key] = m.group(2)
    return out


A, B = decls(a_path), decls(b_path)

only_a = sorted(set(A) - set(B))
only_b = sorted(set(B) - set(A))
changed = sorted(k for k in set(A) & set(B) if A[k] != B[k])

print(f"declarations: {len(A)} in A, {len(B)} in B")
print(f"only in A: {len(only_a)}   only in B: {len(only_b)}   changed: {len(changed)}\n")

for k in only_a:
    print(f"REMOVED  {k}\n         {A[k][:150]}\n")
for k in only_b:
    print(f"ADDED    {k}\n         {B[k][:150]}\n")


def fields(text):
    """Split a flat object type into top-level `key: value` pairs."""
    out, depth, buf = {}, 0, ""
    for ch in text:
        if ch in "{[(":
            depth += 1
        elif ch in "}])":
            depth -= 1
        if ch == "," and depth <= 1:
            out.update(kv(buf))
            buf = ""
        else:
            buf += ch
    out.update(kv(buf))
    return out


def kv(chunk):
    chunk = chunk.strip().lstrip("{").rstrip("}").strip()
    if ":" not in chunk:
        return {}
    k, v = chunk.split(":", 1)
    return {k.strip(): v.strip()}


for k in changed:
    fa, fb = fields(A[k]), fields(B[k])
    diffs = [
        (f, fa.get(f, "<absent>"), fb.get(f, "<absent>"))
        for f in sorted(set(fa) | set(fb))
        if fa.get(f) != fb.get(f)
    ]
    print(f"CHANGED  {k}")
    if not diffs:
        print(f"         (differs below top level)")
        print(f"         A: {A[k][:200]}")
        print(f"         B: {B[k][:200]}")
    for f, va, vb in diffs:
        print(f"         {f}:")
        print(f"           main: {va[:170]}")
        print(f"           fix : {vb[:170]}")
    print()
