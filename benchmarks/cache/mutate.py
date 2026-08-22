"""Applies one named source mutation to an application checkout.

Usage: mutate.py <shape> <phase>

Phases: `pre` runs before the base cache is built, `apply` is the change under
test. Most shapes only use `apply`; the removal and rename shapes need the file
to exist in the base build first.

The application comes from SURVEYOR_BENCH_APP, and the declarations to edit
inside it from a `targets.json` beside this script, which is not tracked. Every
shape has to change generated output or it proves nothing, so the edits have to
name real declarations in a real application, and those belong to whoever owns
that application rather than to this repository. Copy `targets.example.json` and
fill it in; SURVEYOR_BENCH_PRESET picks an entry from it, defaulting to
`default`.
"""

import json
import os
import re
import sys

APP = os.environ.get("SURVEYOR_BENCH_APP")

if not APP:
    sys.exit("set SURVEYOR_BENCH_APP to the application to mutate")

TARGETS = os.path.join(os.path.dirname(os.path.abspath(__file__)), "targets.json")

if not os.path.exists(TARGETS):
    sys.exit(f"copy targets.example.json to {TARGETS} and fill it in")

named = json.load(open(TARGETS))
which = os.environ.get("SURVEYOR_BENCH_PRESET", "default")

if which not in named:
    sys.exit(f"no targets named {which} in {TARGETS}")

preset = named[which]

MODEL = f"{APP}/{preset['model']}"
ENUM = f"{APP}/{preset['enum']}"
PROBE = f"{APP}/{preset['probe_dir']}/SweepProbeEnum.php"
PROBE_RENAMED = f"{APP}/{preset['probe_dir']}/SweepProbeRenamed.php"

PROBE_BODY = """<?php

namespace {namespace};

enum {name}: string
{{
    case ProbeAlpha = 'probe_alpha';
    case ProbeBeta = 'probe_beta';
}}
"""

shape, phase = sys.argv[1], sys.argv[2]


def read(p):
    return open(p).read()


def write(p, s):
    open(p, "w").write(s)


def append_method():
    s = read(MODEL)
    i = s.rstrip().rfind("\n}")
    write(MODEL, s[:i] + preset["relation"] + s[i:])


def remove_method(name):
    s = read(MODEL)
    m = re.search(rf"\n    public function {name}\(", s)
    if not m:
        sys.exit(f"method {name} not found in {MODEL}")
    # Method bodies in these files are brace-balanced and indented four spaces,
    # so the first `\n    }` after the signature closes it.
    end = s.index("\n    }", m.start()) + len("\n    }")
    write(MODEL, s[: m.start()] + s[end:])


def edit_body():
    """Rewrite a method body without changing anything a dependent can see.

    The point of surface fingerprints is that this invalidates the file itself
    and nothing else, so warm output has to match cold anyway.
    """
    s = read(MODEL)
    find, replace = preset["body"]
    if find not in s:
        sys.exit(f"body to rewrite not found in {MODEL}")
    write(MODEL, s.replace(find, replace))


def edit_enum_case():
    s = read(ENUM)
    find, replace = preset["enum_case"]
    if find not in s:
        sys.exit(f"enum case not found in {ENUM}")
    write(ENUM, s.replace(find, replace))


def add_probe(path, name):
    write(path, PROBE_BODY.format(namespace=preset["probe_namespace"], name=name))


def remove(path):
    if os.path.exists(path):
        os.unlink(path)


def print_targets():
    print(preset["model"], preset["enum"])


actions = {
    ("targets", "list"): print_targets,
    ("append-method", "apply"): append_method,
    ("remove-method", "apply"): lambda: remove_method(preset["remove_method"]),
    ("edit-body", "apply"): edit_body,
    ("edit-enum-case", "apply"): edit_enum_case,
    ("add-file", "apply"): lambda: add_probe(PROBE, "SweepProbeEnum"),
    ("remove-file", "pre"): lambda: add_probe(PROBE, "SweepProbeEnum"),
    ("remove-file", "apply"): lambda: remove(PROBE),
    ("rename-file", "pre"): lambda: add_probe(PROBE, "SweepProbeEnum"),
    ("rename-file", "apply"): lambda: (remove(PROBE), add_probe(PROBE_RENAMED, "SweepProbeRenamed")),
    ("cleanup", "apply"): lambda: (remove(PROBE), remove(PROBE_RENAMED)),
}

action = actions.get((shape, phase))

if action:
    action()
