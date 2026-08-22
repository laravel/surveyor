"""Applies one named source mutation to an application checkout.

Usage: mutate.py <shape> <phase>

Phases: `pre` runs before the base cache is built, `apply` is the change under
test. Most shapes only use `apply`; the removal and rename shapes need the file
to exist in the base build first.

Which application, and which files inside it to touch, comes from the
environment: SURVEYOR_BENCH_APP for the checkout and SURVEYOR_BENCH_PRESET for
the set of targets. Every shape has to change generated output, or it proves
nothing, which is why the targets are named per application rather than guessed.
"""

import os
import re
import sys

APP = os.environ.get("SURVEYOR_BENCH_APP", "/Users/joetannenbaum/Herd/cloud")

PRESETS = {
    "cloud": {
        "model": "app/Models/Instance.php",
        "relation": "\n    public function sweepProbeRelation(): HasOne\n    {\n        return $this->hasOne(Daemon::class);\n    }\n",
        "remove_method": "activeScheduledAutoscaleOverride",
        "enum": "app/Enums/InstanceType.php",
        "enum_case": ("MANAGED_QUEUE = 'managed_queue'", "MANAGED_QUEUE = 'managed_queue_probe'"),
        "body": (
            "return $this->identifier;",
            "$identifier = $this->identifier;\n\n        return $identifier;",
        ),
        "probe_namespace": "App\\Enums",
        "probe_dir": "app/Enums",
    },
    "forge": {
        "model": "app/Models/Server.php",
        "relation": "\n    public function sweepProbeRelation(): HasOne\n    {\n        return $this->hasOne(ServerActivation::class);\n    }\n",
        "remove_method": "activation",
        "enum": "app/Enums/ServerType.php",
        "enum_case": ("case Cache = 'cache'", "case Cache = 'cache_probe'"),
        "body": (
            "return $this->hasOne(ServerActivation::class);",
            "$activation = $this->hasOne(ServerActivation::class);\n\n        return $activation;",
        ),
        "probe_namespace": "App\\Enums",
        "probe_dir": "app/Enums",
    },
}

preset = PRESETS[os.environ.get("SURVEYOR_BENCH_PRESET", "cloud")]

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
