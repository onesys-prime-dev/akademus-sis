#!/usr/bin/env python3
"""
Student Schedule Conflict Auditor.

Cross-references each student's actual course registrations against a
master section timetable, builds each student's individual weekly
schedule, and runs pairwise time-overlap detection across every pair of
that student's registered sections.

ALGORITHM
    1. Build a section_id -> sessions lookup from the master timetable
       (`sections`), where each session is a (day, start_time) pair.
    2. For each student, resolve their `registered_sections` list into
       a flat list of (section_id, course_id, day, start_time) tuples
       — this is the student's individual weekly timetable.
    3. For every pair of DIFFERENT sections in that timetable, check for
       a shared (day, start_time) — a direct double-booking. (Two
       sessions of the SAME section at the same slot is not a conflict;
       it is simply that section meeting, so only cross-section pairs
       are compared.)
    4. On a conflict, look for an alternate section of one of the two
       conflicting courses (another `section_id` sharing that
       `course_id` in the master timetable) whose sessions overlap with
       NONE of the student's remaining schedule (every other registered
       section, including the one left in place) — and recommend moving
       the student there if one exists.

DIAGNOSTIC OUTPUT
    [FAIL] <student> has overlapping sections: <Course A> vs <Course B>
           on <Day> at <Time>.
    [RECOMMENDED FIX] Move <student> from <section> (<day> <time>) to
           <alternate section> (<day> <time>) to resolve the conflict.

USAGE
    python audit_student_schedules.py student_manifest.json
"""
from __future__ import annotations

import json
import sys
from collections import defaultdict


def load_json(path: str) -> dict:
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def build_timetable(sections: list[dict]) -> dict[str, dict]:
    return {s["section_id"]: s for s in sections}


def student_schedule(student: dict, timetable: dict[str, dict]) -> list[dict]:
    """Flattens a student's registered sections into individual session
    entries: {section_id, course_id, day, start_time}."""
    schedule = []
    for section_id in student["registered_sections"]:
        section = timetable.get(section_id)
        if section is None:
            continue
        for session in section["sessions"]:
            schedule.append({
                "section_id": section_id,
                "course_id": section["course_id"],
                "day": session["day"],
                "start_time": session["start_time"],
            })
    return schedule


def find_conflicts(schedule: list[dict]) -> list[tuple[dict, dict]]:
    """Pairwise overlap detection across DIFFERENT sections only."""
    conflicts = []
    seen_pairs = set()
    for i in range(len(schedule)):
        for j in range(i + 1, len(schedule)):
            a, b = schedule[i], schedule[j]
            if a["section_id"] == b["section_id"]:
                continue
            if a["day"] == b["day"] and a["start_time"] == b["start_time"]:
                pair_key = tuple(sorted((a["section_id"], b["section_id"])) + [a["day"], a["start_time"]])
                if pair_key in seen_pairs:
                    continue
                seen_pairs.add(pair_key)
                conflicts.append((a, b))
    return conflicts


def find_alternate_section(
    course_id: str,
    exclude_section_id: str,
    rest_of_schedule: list[dict],
    timetable: dict[str, dict],
) -> dict | None:
    """Finds another section of `course_id` (not `exclude_section_id`)
    whose sessions overlap with none of `rest_of_schedule`."""
    rest_slots = {(s["day"], s["start_time"]) for s in rest_of_schedule}

    for section_id, section in timetable.items():
        if section["course_id"] != course_id or section_id == exclude_section_id:
            continue
        section_slots = {(s["day"], s["start_time"]) for s in section["sessions"]}
        if not (section_slots & rest_slots):
            return section
    return None


def audit_student(student: dict, timetable: dict[str, dict]) -> tuple[bool, list[str]]:
    sid = student["student_id"]
    name = student["name"]
    label = f"{name} ({sid})"

    schedule = student_schedule(student, timetable)
    conflicts = find_conflicts(schedule)

    if not conflicts:
        sections = ", ".join(student["registered_sections"])
        return True, [f"[OK] {label}: {len(student['registered_sections'])} section(s) registered "
                      f"({sections}), zero schedule conflicts."]

    messages = []
    for a, b in conflicts:
        messages.append(
            f"[FAIL] {label} has overlapping sections: {a['course_id']} ({a['section_id']}) vs "
            f"{b['course_id']} ({b['section_id']}) on {a['day']} at {a['start_time']}."
        )

        rest = [s for s in schedule if s["section_id"] not in (a["section_id"], b["section_id"])]

        fixed = False
        for conflicting, keep in ((a, b), (b, a)):
            rest_plus_keep = rest + [s for s in schedule if s["section_id"] == keep["section_id"]]
            alt = find_alternate_section(conflicting["course_id"], conflicting["section_id"], rest_plus_keep, timetable)
            if alt:
                alt_time = f"{alt['sessions'][0]['day']} {alt['sessions'][0]['start_time']}"
                messages.append(
                    f"[RECOMMENDED FIX] Move {label} from {conflicting['section_id']} "
                    f"({conflicting['day']} {conflicting['start_time']}) to {alt['section_id']} ({alt_time}) "
                    f"to resolve the conflict."
                )
                fixed = True
                break

        if not fixed:
            messages.append(
                f"[RECOMMENDED FIX] No conflict-free alternate section exists for {a['course_id']} or "
                f"{b['course_id']} under {label}'s current schedule — a new section may need to be opened."
            )

    return False, messages


def main() -> int:
    if len(sys.argv) != 2:
        print("Usage: python audit_student_schedules.py student_manifest.json", file=sys.stderr)
        return 1

    try:
        manifest = load_json(sys.argv[1])
    except (OSError, json.JSONDecodeError) as e:
        print(f"Failed to load input: {e}", file=sys.stderr)
        return 1

    timetable = build_timetable(manifest["sections"])

    print("=" * 78)
    print("STUDENT SCHEDULE CONFLICT AUDIT")
    print("=" * 78)

    all_passed = True
    total_conflicts = 0

    for student in manifest["students"]:
        passed, messages = audit_student(student, timetable)
        all_passed = all_passed and passed
        if not passed:
            total_conflicts += 1
        print()
        for m in messages:
            print(m)

    print()
    print("=" * 78)
    print(f"Students audited: {len(manifest['students'])}   |   Students with conflicts: {total_conflicts}")
    print(f"RESULT: {'PASS — zero schedule conflicts' if all_passed else 'FAIL — conflicts detected'}")
    print("=" * 78)

    return 0 if all_passed else 1


if __name__ == "__main__":
    sys.exit(main())
