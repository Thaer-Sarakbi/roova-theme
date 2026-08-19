#!/usr/bin/env python3
"""Build a .pot file for the Roova theme by scanning its PHP sources.

Handles the gettext calls the theme actually uses: __(), _e(), esc_html__(),
esc_html_e(), esc_attr__(), esc_attr_e(), _x(), and _n().
"""

import os
import re
import sys
from collections import OrderedDict

THEME = sys.argv[1]
OUT = sys.argv[2]

SINGULAR = r"(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)"
PLURAL = r"_n"
CONTEXT = r"(?:_x|esc_html_x|esc_attr_x)"

# A quoted PHP string: '...' or "..." with escaped quotes allowed.
STR = r"""(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")"""

RE_SINGULAR = re.compile(SINGULAR + r"\(\s*" + STR + r"\s*,\s*'roova'\s*\)")
RE_PLURAL = re.compile(PLURAL + r"\(\s*" + STR + r"\s*,\s*" + STR + r"\s*,")
RE_CONTEXT = re.compile(CONTEXT + r"\(\s*" + STR + r"\s*,\s*" + STR + r"\s*,\s*'roova'\s*\)")


def unquote(single, double):
    """Return the PHP string value with its escapes resolved."""
    if single is not None:
        return single.replace("\\'", "'").replace("\\\\", "\\")
    return (
        double.replace('\\"', '"')
        .replace("\\n", "\n")
        .replace("\\t", "\t")
        .replace("\\\\", "\\")
    )


def escape(value):
    return (
        value.replace("\\", "\\\\")
        .replace('"', '\\"')
        .replace("\n", "\\n")
        .replace("\t", "\\t")
    )


entries = OrderedDict()


def add(key, block, reference):
    if key in entries:
        if reference not in entries[key]["refs"]:
            entries[key]["refs"].append(reference)
        return
    entries[key] = {"block": block, "refs": [reference]}


for root, dirs, files in os.walk(THEME):
    dirs[:] = [d for d in dirs if not d.startswith(".")]
    for name in sorted(files):
        if not name.endswith(".php"):
            continue

        path = os.path.join(root, name)
        rel = os.path.relpath(path, THEME)

        with open(path, encoding="utf-8") as handle:
            lines = handle.read().split("\n")

        for number, line in enumerate(lines, start=1):
            reference = "%s:%d" % (rel, number)

            for match in RE_CONTEXT.finditer(line):
                text = unquote(match.group(1), match.group(2))
                context = unquote(match.group(3), match.group(4))
                add(
                    (context, text, None),
                    'msgctxt "%s"\nmsgid "%s"\nmsgstr ""' % (escape(context), escape(text)),
                    reference,
                )

            for match in RE_PLURAL.finditer(line):
                single = unquote(match.group(1), match.group(2))
                plural = unquote(match.group(3), match.group(4))
                add(
                    (None, single, plural),
                    'msgid "%s"\nmsgid_plural "%s"\nmsgstr[0] ""\nmsgstr[1] ""'
                    % (escape(single), escape(plural)),
                    reference,
                )

            for match in RE_SINGULAR.finditer(line):
                text = unquote(match.group(1), match.group(2))
                add(
                    (None, text, None),
                    'msgid "%s"\nmsgstr ""' % escape(text),
                    reference,
                )

header = '''# Copyright (C) 2026 Roova
# This file is distributed under the GNU General Public License v2 or later.
msgid ""
msgstr ""
"Project-Id-Version: Roova 1.0.0\\n"
"Report-Msgid-Bugs-To: \\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
"X-Domain: roova\\n"
'''

with open(OUT, "w", encoding="utf-8") as handle:
    handle.write(header)
    for entry in entries.values():
        handle.write("\n")
        handle.write("#: " + " ".join(entry["refs"]) + "\n")
        handle.write(entry["block"] + "\n")

print("%d strings" % len(entries))
