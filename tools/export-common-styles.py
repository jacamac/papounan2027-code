#!/usr/bin/env python3
"""Regenerate design-system/papounan-design-system.css from CrocoBuilder's export.

CrocoBuilder (DB-first) is the source of truth for tokens, classes and common
styles; it commits them to the papounan2027-builder repo. This script turns the
exported common styles into a readable CSS file, so they can be reviewed in Git
and re-imported if needed.

Usage:
    python3 tools/export-common-styles.py ../papounan2027-builder > design-system/papounan-design-system.css
"""
import json
import sys
from pathlib import Path

root = Path(sys.argv[1] if len(sys.argv) > 1 else "../papounan2027-builder")
styles = json.loads((root / "crocoblock-builder/globals/common-styles.json").read_text())

print("/* papounan-design-system.css: CrocoBuilder common styles (generated). */")
print("/* Source: papounan2027-builder/crocoblock-builder/globals/common-styles.json */")
print("/* Do not edit by hand: change them in CrocoBuilder, commit, then regenerate. */")
for item in sorted(styles, key=lambda s: s.get("sort_order", 0)):
    decl = item.get("style_payload", {}).get("base", {}).get("decl", {})
    if not decl:
        continue
    print()
    if item.get("name") and item["name"] != item["selector"]:
        print(f"/* {item['name']} */")
    print(f"{item['selector']} {{")
    for prop, value in decl.items():
        print(f"\t{prop}: {value};")
    print("}")
