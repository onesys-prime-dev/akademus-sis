#!/usr/bin/env python3
"""
End-to-end validator for the integrated 2-stage pipeline
(pipeline_dataset.json -> integrated_schedule.json).

Independently re-derives every fact from the dataset rather than trusting
run_pipeline.py's internal state — this validator does not import or call
any code from run_pipeline.py.

STAGE 1 AUDIT (per course)
    - Every enrolled student assigned to exactly one section (no missing,
      no duplicate, no foreign student).
    - No section exceeds its course's max_capacity.
    - Soft balance: achievable-minimum variance check. Per the same
      disclosed reasoning as validate_balancing.py, the literal formula
      Penalty = sum((actual_i - L_target)^2) is non-zero even for the
      best possible split whenever N doesn't divide evenly by k, so this
      validator compares actual penalty against the penalty of the
      divmod-optimal partition for that (N, k), and requires
      Penalty_actual - Penalty_min == 0 (i.e. the schedule achieved the
      true optimum, not merely "some" balance).

STAGE 2 AUDIT (across all sections' scheduled sessions)
    - Zero room double-bookings at any (room, timeslot).
    - Zero professor double-bookings at any (professor, timeslot).
    - Every session's room capacity >= that session's section size.
    - Every session's room type matches the section's course's
      required_room_type.
    - Every session's professor is available at that timeslot.
    - Every section has exactly `sessions_per_week` sessions, at
      distinct timeslots.
    - Corequisite SECTIONS (re-derived independently from
      dataset["corequisite_pairs"] + positional pairing, not read from
      the pipeline's output) never share a timeslot.

USAGE
    python validate_pipeline.py pipeline_dataset.json integrated_schedule.json
"""
from __future__ import annotations

import json
import math
import sys
from collections import defaultdict


def load_json(path: str) -> dict:
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def partition_sizes(n: int, k: int) -> list[int]:
    base, remainder = divmod(n, k)
    return [base + 1] * remainder + [base] * (k - remainder)


def independent_expected_sections(dataset: dict) -> dict:
    """Recomputes, from scratch, what Stage 1 SHOULD have produced —
    used to check the pipeline's actual output against, never trusting
    the pipeline's own section objects for this."""
    expected = {}
    for course in dataset["courses"]:
        cid = course["course_id"]
        n = len(course["students"])
        k = max(1, math.ceil(n / course["max_capacity"]))
        sizes = partition_sizes(n, k)
        expected[cid] = {
            "n": n, "k": k, "sizes": sizes,
            "roster": set(course["students"]),
            "max_capacity": course["max_capacity"],
            "required_room_type": course["required_room_type"],
            "sessions_per_week": course["sessions_per_week"],
        }
    return expected


def independent_coreq_section_pairs(dataset: dict, schedule: list[dict]) -> set[tuple[str, str]]:
    """Re-derives which SECTION ids are corequisite partners, purely from
    dataset["corequisite_pairs"] + each course's sections sorted by
    section_id (positional pairing) — independent of anything the
    pipeline recorded internally."""
    sections_by_course = defaultdict(list)
    for sec in schedule:
        sections_by_course[sec["course_id"]].append(sec["section_id"])
    for secs in sections_by_course.values():
        secs.sort()

    pairs = set()
    for course_a, course_b in dataset.get("corequisite_pairs", []):
        secs_a = sections_by_course.get(course_a, [])
        secs_b = sections_by_course.get(course_b, [])
        for sa, sb in zip(secs_a, secs_b):
            pairs.add(tuple(sorted((sa, sb))))
    return pairs


def stage1_audit(expected: dict, schedule: list[dict]) -> tuple[bool, list[str]]:
    messages = []
    passed = True

    sections_by_course = defaultdict(list)
    for sec in schedule:
        sections_by_course[sec["course_id"]].append(sec)

    for cid, exp in expected.items():
        course_sections = sorted(sections_by_course.get(cid, []), key=lambda s: s["section_id"])
        n, k, sizes, roster, capacity = exp["n"], exp["k"], exp["sizes"], exp["roster"], exp["max_capacity"]

        # Capacity
        for sec in course_sections:
            if len(sec["students"]) > capacity:
                passed = False
                messages.append(f"[FAIL] Stage 1 / {cid}: Section {sec['section_id']} has "
                                 f"{len(sec['students'])} students, exceeding max_capacity {capacity}.")
                messages.append(f"[RECOMMENDED FIX] Split Section {sec['section_id']} to bring it under capacity {capacity}.")

        # Complete, exclusive partition
        seen = {}
        duplicates, foreign = [], []
        for sec in course_sections:
            for student in sec["students"]:
                if student not in roster:
                    foreign.append((student, sec["section_id"]))
                    continue
                if student in seen:
                    duplicates.append((student, seen[student], sec["section_id"]))
                else:
                    seen[student] = sec["section_id"]
        missing = sorted(roster - seen.keys())

        if foreign:
            passed = False
            messages.append(f"[FAIL] Stage 1 / {cid}: {len(foreign)} foreign student assignment(s) found.")
            messages.append(f"[RECOMMENDED FIX] Remove {len(foreign)} foreign student assignment(s) from {cid}'s sections.")
        if duplicates:
            passed = False
            messages.append(f"[FAIL] Stage 1 / {cid}: {len(duplicates)} student(s) double-assigned across sections.")
            messages.append(f"[RECOMMENDED FIX] Remove {len(duplicates)} duplicate assignment(s).")
        if missing:
            passed = False
            messages.append(f"[FAIL] Stage 1 / {cid}: {len(missing)} enrolled student(s) unassigned (e.g. {missing[:5]}).")
            messages.append(f"[RECOMMENDED FIX] Assign the {len(missing)} unplaced student(s) to a section with room.")

        actual_k = len(course_sections)
        if actual_k != k:
            passed = False
            messages.append(f"[FAIL] Stage 1 / {cid}: {actual_k} section(s) produced, but N={n} against "
                             f"capacity {capacity} requires k={k}.")
            messages.append(f"[RECOMMENDED FIX] Adjust section count from {actual_k} to {k} for {cid}.")
            continue

        if not course_sections:
            continue

        l_target = math.ceil(n / k)
        actual_sizes = [len(s["students"]) for s in course_sections]
        actual_penalty = sum((s - l_target) ** 2 for s in actual_sizes)
        min_penalty = sum((s - l_target) ** 2 for s in sizes)

        if actual_penalty - min_penalty != 0:
            passed = False
            biggest, smallest = max(actual_sizes), min(actual_sizes)
            messages.append(f"[FAIL] Stage 1 / {cid}: variance penalty {actual_penalty} exceeds achievable "
                             f"minimum {min_penalty} (target sizes {sorted(sizes, reverse=True)}). "
                             f"Load imbalance delta = {biggest - smallest}.")
            messages.append(f"[RECOMMENDED FIX] Re-balance {cid}'s sections toward {sorted(sizes, reverse=True)}.")
        else:
            messages.append(f"[OK] Stage 1 / {cid}: {actual_k} section(s), sizes {sorted(actual_sizes, reverse=True)} "
                             f"== achievable optimum (penalty {actual_penalty}, min {min_penalty}).")

    return passed, messages


def stage2_audit(dataset: dict, expected: dict, schedule: list[dict]) -> tuple[bool, list[str]]:
    messages = []
    passed = True

    rooms_by_id = {r["id"]: r for r in dataset["rooms"]}
    profs_by_id = {p["id"]: p for p in dataset["professors"]}
    coreq_pairs = independent_coreq_section_pairs(dataset, schedule)

    room_time_map = defaultdict(list)
    prof_time_map = defaultdict(list)
    sessions_by_section = {}

    for sec in schedule:
        sid = sec["section_id"]
        cid = sec["course_id"]
        prof_id = sec["professor"]
        exp = expected[cid]
        sess_list = sec["sessions"]
        sessions_by_section[sid] = sess_list

        # Completeness
        timeslot_ids = [f"{s['day']}-{s['start_time']}" for s in sess_list]
        if len(sess_list) != exp["sessions_per_week"]:
            passed = False
            messages.append(f"[FAIL] Stage 2 / {sid}: has {len(sess_list)} session(s), expected {exp['sessions_per_week']}.")
            messages.append(f"[RECOMMENDED FIX] Schedule the missing session(s) for {sid}.")
        if len(set(timeslot_ids)) != len(timeslot_ids):
            passed = False
            messages.append(f"[FAIL] Stage 2 / {sid}: duplicate timeslot(s) in its own session list.")

        for s, tid in zip(sess_list, timeslot_ids):
            room = rooms_by_id.get(s["room_id"])
            prof = profs_by_id.get(prof_id)

            if room is None:
                passed = False
                messages.append(f"[FAIL] Stage 2 / {sid}: references unknown room '{s['room_id']}'.")
                continue

            # Capacity
            if len(sec["students"]) > room["capacity"]:
                passed = False
                messages.append(f"[FAIL] Stage 2 / {sid} @ {tid}: section size {len(sec['students'])} "
                                 f"exceeds room {room['id']} capacity {room['capacity']}.")
                messages.append(f"[RECOMMENDED FIX] Move {sid} to a room with capacity >= {len(sec['students'])}.")

            # Room type
            if room["type"] != exp["required_room_type"]:
                passed = False
                messages.append(f"[FAIL] Stage 2 / {sid} @ {tid}: requires room type '{exp['required_room_type']}', "
                                 f"but was placed in '{room['id']}' (type '{room['type']}').")
                messages.append(f"[RECOMMENDED FIX] Reassign {sid} to a {exp['required_room_type']}-type room.")

            # Professor availability
            if prof and tid in prof["unavailable_timeslots"]:
                passed = False
                messages.append(f"[FAIL] Stage 2 / {sid} @ {tid}: professor '{prof_id}' is unavailable then.")
                messages.append(f"[RECOMMENDED FIX] Reschedule {sid}'s session at {tid} to a timeslot {prof_id} is free.")

            room_time_map[(room["id"], tid)].append(sid)
            prof_time_map[(prof_id, tid)].append(sid)

    for (room_id, tid), sids in room_time_map.items():
        if len(set(sids)) > 1:
            passed = False
            messages.append(f"[FAIL] Stage 2: Room '{room_id}' at {tid} is double-booked by {sorted(set(sids))}.")
            messages.append(f"[RECOMMENDED FIX] Move one of {sorted(set(sids))} to a different room or timeslot.")

    for (prof_id, tid), sids in prof_time_map.items():
        if len(set(sids)) > 1:
            passed = False
            messages.append(f"[FAIL] Stage 2: Professor '{prof_id}' at {tid} is double-booked across {sorted(set(sids))}.")
            messages.append(f"[RECOMMENDED FIX] Reschedule one of {sorted(set(sids))} to a timeslot {prof_id} is free.")

    for sid_a, sid_b in coreq_pairs:
        slots_a = {f"{s['day']}-{s['start_time']}" for s in sessions_by_section.get(sid_a, [])}
        slots_b = {f"{s['day']}-{s['start_time']}" for s in sessions_by_section.get(sid_b, [])}
        overlap = slots_a & slots_b
        if overlap:
            passed = False
            messages.append(f"[FAIL] Stage 2: Corequisite sections '{sid_a}' and '{sid_b}' overlap at {sorted(overlap)}.")
            messages.append(f"[RECOMMENDED FIX] Move one of {sid_a}/{sid_b} to a non-overlapping timeslot.")

    if passed:
        messages.insert(0, "[OK] Stage 2: zero room collisions, zero professor double-bookings, "
                            "100% capacity/type fit, zero corequisite overlaps.")

    return passed, messages


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: python validate_pipeline.py pipeline_dataset.json integrated_schedule.json", file=sys.stderr)
        return 1

    try:
        dataset = load_json(sys.argv[1])
        schedule = load_json(sys.argv[2])
    except (OSError, json.JSONDecodeError) as e:
        print(f"Failed to load input: {e}", file=sys.stderr)
        return 1

    expected = independent_expected_sections(dataset)

    print("=" * 78)
    print("INTEGRATED 2-STAGE PIPELINE — VALIDATION REPORT")
    print("=" * 78)

    print("\n### STAGE 1 AUDIT (Section Load Balancer) ###")
    s1_pass, s1_messages = stage1_audit(expected, schedule)
    for m in s1_messages:
        print(m)

    print("\n### STAGE 2 AUDIT (CSP Timetable Scheduler) ###")
    s2_pass, s2_messages = stage2_audit(dataset, expected, schedule)
    for m in s2_messages:
        print(m)

    overall = s1_pass and s2_pass
    print()
    print("=" * 78)
    print(f"STAGE 1: {'PASS' if s1_pass else 'FAIL'}   |   STAGE 2: {'PASS' if s2_pass else 'FAIL'}")
    print(f"OVERALL RESULT: {'PASS' if overall else 'FAIL'}")
    print("=" * 78)

    return 0 if overall else 1


if __name__ == "__main__":
    sys.exit(main())
