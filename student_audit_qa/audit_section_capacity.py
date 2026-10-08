#!/usr/bin/env python3
"""
Section Capacity Auditor.

Independently re-derives each section's headcount directly from
student registrations (not trusting any pipeline's internal counters)
and confirms no section exceeds its room's max_capacity.

USAGE
    python audit_section_capacity.py resolved_student_manifest.json
"""
from __future__ import annotations

import json
import sys
from collections import defaultdict


def load_json(path: str) -> dict:
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def main() -> int:
    if len(sys.argv) != 2:
        print("Usage: python audit_section_capacity.py <manifest.json>", file=sys.stderr)
        return 1

    try:
        manifest = load_json(sys.argv[1])
    except (OSError, json.JSONDecodeError) as e:
        print(f"Failed to load input: {e}", file=sys.stderr)
        return 1

    sections_by_id = {s["section_id"]: s for s in manifest["sections"]}
    headcounts = defaultdict(int)
    for student in manifest["students"]:
        for section_id in student["registered_sections"]:
            if section_id in sections_by_id:
                headcounts[section_id] += 1

    print("=" * 78)
    print("SECTION CAPACITY AUDIT")
    print("=" * 78)

    all_passed = True
    for section_id, section in sections_by_id.items():
        count = headcounts[section_id]
        cap = section["max_capacity"]
        if count > cap:
            all_passed = False
            print(f"[FAIL] Section {section_id} ({section['course_id']}): {count} students exceeds "
                  f"max_capacity {cap}.")
            print(f"[RECOMMENDED FIX] Open an additional section for {section['course_id']} or increase "
                  f"room capacity for {section_id}.")
        else:
            print(f"[OK] Section {section_id} ({section['course_id']}): {count} / {cap}.")

    print()
    print("=" * 78)
    print(f"RESULT: {'PASS — no section exceeds capacity' if all_passed else 'FAIL — capacity overflow detected'}")
    print("=" * 78)

    return 0 if all_passed else 1


if __name__ == "__main__":
    sys.exit(main())
