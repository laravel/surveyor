"""Applies one named source mutation to the Cloud checkout.

Usage: mutate.py <shape> <phase>

Phases: `pre` runs before the base cache is built, `apply` is the change under
test. Most shapes only use `apply`; the removal and rename shapes need the file
to exist in the base build first.
"""

import os
import re
import sys

APP = "/Users/joetannenbaum/Herd/cloud"
INSTANCE = f"{APP}/app/Models/Instance.php"
ENUM = f"{APP}/app/Enums/InstanceType.php"
PROBE = f"{APP}/app/Enums/SweepProbeEnum.php"
PROBE_RENAMED = f"{APP}/app/Enums/SweepProbeRenamed.php"

PROBE_BODY = """<?php

namespace App\\Enums;

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
    s = read(INSTANCE)
    i = s.rstrip().rfind("\n}")
    probe = (
        "\n    public function sweepProbeRelation(): HasOne\n"
        "    {\n        return $this->hasOne(Daemon::class);\n    }\n"
    )
    write(INSTANCE, s[:i] + probe + s[i:])


def remove_method(name):
    s = read(INSTANCE)
    m = re.search(rf"\n    public function {name}\(", s)
    if not m:
        sys.exit(f"method {name} not found")
    # Method bodies in this file are brace-balanced and indented four spaces,
    # so the first `\n    }` after the signature closes it.
    end = s.index("\n    }", m.start()) + len("\n    }")
    write(INSTANCE, s[: m.start()] + s[end:])


def edit_enum_case():
    s = read(ENUM)
    if "MANAGED_QUEUE = 'managed_queue'" not in s:
        sys.exit("enum case not found")
    write(ENUM, s.replace("MANAGED_QUEUE = 'managed_queue'", "MANAGED_QUEUE = 'managed_queue_probe'"))


def add_probe(path, name):
    write(path, PROBE_BODY.format(name=name))


def remove(path):
    if os.path.exists(path):
        os.unlink(path)


actions = {
    ("append-method", "apply"): append_method,
    ("remove-method", "apply"): lambda: remove_method("activeScheduledAutoscaleOverride"),
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
