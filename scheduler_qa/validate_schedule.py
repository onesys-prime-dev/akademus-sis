#!/usr/bin/env python3
"""
Strict validator for the university course-timetabling dataset (dataset.json).

USAGE
    python validate_schedule.py dataset.json schedule.json

EXPECTED SCHEDULE FORMAT
Your algorithm's output must be a JSON array of "session" assignments, one
entry per scheduled meeting. A course with sessions_per_week = N must appear
exactly N times, each at a different (day, start_time):

    [
      {"course_id": "CS101", "day": "Mon", "start_time": "08:00", "room_id": "LEC-A"},
      {"course_id": "CS101", "day": "Wed", "start_time": "08:00", "room_id": "LEC-A"},
      {"course_id": "CS101L", "day": "Tue", "start_time": "13:00", "room_id": "LAB-A"},
      ...
    ]

Notes on design choices:
  - professor_id is intentionally NOT part of a schedule entry. Professor-
    course assignment is a fixed INPUT (courses[i].assigned_professor), not
    something your algorithm decides — it only places each course's fixed
    sessions into (day, start_time, room). The validator derives the
    professor from the course record for every check.
  - end_time is derived as start_time + 1 hour (every block in this dataset
    is a 1-hour slot), not read from your output.
  - HARD CONSTRAINT #6 (corequisite non-overlap) is not numbered 1-5 in the
    original spec's "Hard Constraints" list, but the dataset's own field
    description says corequisite_courses "must not overlap" — that is a
    zero-tolerance structural requirement, not a matter of degree, so it is
    enforced as a hard constraint here. The SOFT score separately penalizes
    *how far apart* an already-non-overlapping corequisite pair ends up
    (different buildings / large time gaps) — see SOFT CONSTRAINT 2 below.
  - Schedule completeness (every course present exactly sessions_per_week
    times, at distinct slots, referencing real courses/rooms/timeslots) is
    enforced as hard constraint #0 — an incomplete or malformed schedule
    can't meaningfully be scored at all.

EXIT CODE
    0 if all hard constraints pass (regardless of soft score).
    1 if any hard constraint is violated, or the file fails to load/parse.
"""
from __future__ import annotations

import json
import sys
from collections import defaultdict
from dataclasses import dataclass, field


# ============================================================ Soft-score weights
# All tunable in one place. Final score is 100 minus the sum of all applied
# penalties, floored at 0.
WEIGHT_UNDERFILLED_ROOM = 25.0   # max penalty contribution from room-size mismatch, summed per session then capped
UNDERFILL_THRESHOLD = 0.5        # a session is only penalized if enrollment/capacity < this
WEIGHT_COREQ_BUILDING = 8.0      # flat penalty per corequisite pair split across buildings (at their closest session pair)
WEIGHT_COREQ_GAP = 12.0          # max penalty per corequisite pair for a large time gap (scaled by gap size)
GAP_FREE_HOURS = 2               # gap <= this (same day) incurs no penalty
GAP_MAX_HOURS = 8                # gap >= this (same day) incurs the FULL WEIGHT_COREQ_GAP penalty
COREQ_DIFFERENT_DAY_PENALTY = 10.0  # flat penalty per pair when no session pair shares a day at all


@dataclass
class Violation:
    rule: str
    message: str


@dataclass
class ValidationResult:
    hard_violations: list[Violation] = field(default_factory=list)
    soft_penalties: list[tuple[str, float]] = field(default_factory=list)

    @property
    def passed(self) -> bool:
        return len(self.hard_violations) == 0

    @property
    def soft_score(self) -> float:
        score = 100.0 - sum(p for _, p in self.soft_penalties)
        return max(0.0, round(score, 2))


def load_json(path: str) -> dict:
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def hour_of(hhmm: str) -> int:
    return int(hhmm.split(":")[0])


def validate(dataset: dict, schedule: list) -> ValidationResult:
    result = ValidationResult()

    rooms_by_id = {r["id"]: r for r in dataset["rooms"]}
    profs_by_id = {p["id"]: p for p in dataset["professors"]}
    courses_by_id = {c["id"]: c for c in dataset["courses"]}
    valid_timeslot_ids = {t["id"] for t in dataset["timeslots"]}

    # ---------------------------------------------------- 0. Structural sanity
    # Malformed entries are collected but don't crash the run — every
    # structurally invalid entry is reported, then excluded from the
    # remaining checks (which assume well-formed entries).
    clean_sessions = []
    sessions_by_course = defaultdict(list)

    for i, entry in enumerate(schedule):
        tag = f"schedule[{i}]"
        missing = [k for k in ("course_id", "day", "start_time", "room_id") if k not in entry]
        if missing:
            result.hard_violations.append(Violation("structure", f"{tag} missing field(s): {missing}"))
            continue

        course_id, day, start_time, room_id = entry["course_id"], entry["day"], entry["start_time"], entry["room_id"]

        if course_id not in courses_by_id:
            result.hard_violations.append(Violation("structure", f"{tag} references unknown course_id '{course_id}'"))
            continue
        if room_id not in rooms_by_id:
            result.hard_violations.append(Violation("structure", f"{tag} ({course_id}) references unknown room_id '{room_id}'"))
            continue
        timeslot_id = f"{day}-{start_time}"
        if timeslot_id not in valid_timeslot_ids:
            result.hard_violations.append(Violation("structure", f"{tag} ({course_id}) has invalid (day, start_time) = ({day}, {start_time}) — not a real timeslot"))
            continue

        clean = {"course_id": course_id, "day": day, "start_time": start_time, "room_id": room_id, "timeslot_id": timeslot_id}
        clean_sessions.append(clean)
        sessions_by_course[course_id].append(clean)

    # Completeness: every course must appear exactly sessions_per_week times,
    # at DISTINCT timeslots (no double-booking the same course into the same
    # slot twice, which would otherwise silently satisfy a naive count check).
    for course in dataset["courses"]:
        cid = course["id"]
        required = course["sessions_per_week"]
        sessions = sessions_by_course.get(cid, [])
        distinct_slots = {s["timeslot_id"] for s in sessions}

        if len(sessions) != required:
            result.hard_violations.append(Violation(
                "completeness",
                f"Course '{cid}' has {len(sessions)} session(s) scheduled, expected {required}."
            ))
        elif len(distinct_slots) != len(sessions):
            result.hard_violations.append(Violation(
                "completeness",
                f"Course '{cid}' has duplicate sessions at the same timeslot."
            ))

    # From here on, only use clean_sessions / sessions_by_course for the
    # constraint checks below — already-reported malformed entries are
    # excluded so they don't cascade into confusing secondary errors.

    # ---------------------------------------------------- 1. Room overlap
    room_time_map = defaultdict(list)  # (room_id, timeslot_id) -> [course_id, ...]
    for s in clean_sessions:
        room_time_map[(s["room_id"], s["timeslot_id"])].append(s["course_id"])

    for (room_id, timeslot_id), course_ids in room_time_map.items():
        if len(course_ids) > 1:
            result.hard_violations.append(Violation(
                "room_overlap",
                f"Room '{room_id}' at {timeslot_id} is double-booked by: {sorted(set(course_ids))}"
            ))

    # ---------------------------------------------------- 2. Professor overlap
    prof_time_map = defaultdict(list)  # (professor_id, timeslot_id) -> [course_id, ...]
    for s in clean_sessions:
        prof_id = courses_by_id[s["course_id"]]["assigned_professor"]
        prof_time_map[(prof_id, s["timeslot_id"])].append(s["course_id"])

    for (prof_id, timeslot_id), course_ids in prof_time_map.items():
        if len(set(course_ids)) > 1:
            result.hard_violations.append(Violation(
                "professor_overlap",
                f"Professor '{prof_id}' at {timeslot_id} is double-booked across: {sorted(set(course_ids))}"
            ))

    # ---------------------------------------------------- 3. Capacity
    for s in clean_sessions:
        course = courses_by_id[s["course_id"]]
        room = rooms_by_id[s["room_id"]]
        if course["expected_enrollment"] > room["capacity"]:
            result.hard_violations.append(Violation(
                "capacity",
                f"Course '{course['id']}' (enrollment {course['expected_enrollment']}) exceeds room "
                f"'{room['id']}' capacity ({room['capacity']}) at {s['timeslot_id']}."
            ))

    # ---------------------------------------------------- 4. Room type
    for s in clean_sessions:
        course = courses_by_id[s["course_id"]]
        room = rooms_by_id[s["room_id"]]
        if course["required_room_type"] != room["type"]:
            result.hard_violations.append(Violation(
                "room_type",
                f"Course '{course['id']}' requires room type '{course['required_room_type']}' "
                f"but was placed in '{room['id']}' (type '{room['type']}') at {s['timeslot_id']}."
            ))

    # ---------------------------------------------------- 5. Professor availability
    for s in clean_sessions:
        course = courses_by_id[s["course_id"]]
        prof = profs_by_id[course["assigned_professor"]]
        if s["timeslot_id"] in prof["unavailable_timeslots"]:
            result.hard_violations.append(Violation(
                "availability",
                f"Course '{course['id']}' is scheduled at {s['timeslot_id']}, but professor "
                f"'{prof['id']}' ({prof['name']}) is unavailable then."
            ))

    # ---------------------------------------------------- 6. Corequisite non-overlap
    checked_pairs = set()
    for course in dataset["courses"]:
        for coreq_id in course.get("corequisite_courses", []):
            pair = tuple(sorted((course["id"], coreq_id)))
            if pair in checked_pairs:
                continue
            checked_pairs.add(pair)

            a_slots = {s["timeslot_id"] for s in sessions_by_course.get(pair[0], [])}
            b_slots = {s["timeslot_id"] for s in sessions_by_course.get(pair[1], [])}
            overlap = a_slots & b_slots
            if overlap:
                result.hard_violations.append(Violation(
                    "corequisite_overlap",
                    f"Corequisite courses '{pair[0]}' and '{pair[1]}' share timeslot(s) {sorted(overlap)}."
                ))

    # ============================================================ SOFT SCORE
    # Only computed over the already-validated clean_sessions; a schedule
    # with hard violations can still get a soft score (useful for comparing
    # "how close" two broken candidate schedules are during development),
    # but main() below makes clear that passed=False overrides everything.

    # ---- Soft 1: small classes in oversized rooms ----
    underfill_total = 0.0
    for s in clean_sessions:
        course = courses_by_id[s["course_id"]]
        room = rooms_by_id[s["room_id"]]
        if room["capacity"] <= 0:
            continue
        utilization = course["expected_enrollment"] / room["capacity"]
        if utilization < UNDERFILL_THRESHOLD:
            underfill_total += (UNDERFILL_THRESHOLD - utilization) / UNDERFILL_THRESHOLD

    if underfill_total > 0:
        # Normalize by number of sessions so this doesn't blow out on a
        # large schedule; cap at the configured weight.
        penalty = min(WEIGHT_UNDERFILLED_ROOM, WEIGHT_UNDERFILLED_ROOM * underfill_total / max(1, len(clean_sessions)) * 10)
        result.soft_penalties.append(("Underfilled rooms (small classes in large halls)", round(penalty, 2)))

    # ---- Soft 2: corequisite building/time-gap quality ----
    for pair in checked_pairs:
        a_sessions = sessions_by_course.get(pair[0], [])
        b_sessions = sessions_by_course.get(pair[1], [])
        if not a_sessions or not b_sessions:
            continue  # already flagged as a completeness violation

        # Closest same-day gap, in hours, across all session pairs.
        same_day_gaps = []
        buildings_differ_at_min_gap = False
        for sa in a_sessions:
            for sb in b_sessions:
                if sa["day"] != sb["day"]:
                    continue
                gap = abs(hour_of(sa["start_time"]) - hour_of(sb["start_time"]))
                same_day_gaps.append((gap, sa["room_id"], sb["room_id"]))

        if not same_day_gaps:
            result.soft_penalties.append((f"Corequisite {pair} never shares a day", COREQ_DIFFERENT_DAY_PENALTY))
            continue

        same_day_gaps.sort(key=lambda g: g[0])
        min_gap, room_a, room_b = same_day_gaps[0]

        if min_gap > GAP_FREE_HOURS:
            span = max(1, GAP_MAX_HOURS - GAP_FREE_HOURS)
            fraction = min(1.0, (min_gap - GAP_FREE_HOURS) / span)
            result.soft_penalties.append((f"Corequisite {pair} time gap ({min_gap}h)", round(WEIGHT_COREQ_GAP * fraction, 2)))

        if rooms_by_id[room_a]["building"] != rooms_by_id[room_b]["building"]:
            result.soft_penalties.append((f"Corequisite {pair} split across buildings", WEIGHT_COREQ_BUILDING))

    return result


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: python validate_schedule.py dataset.json schedule.json", file=sys.stderr)
        return 1

    try:
        dataset = load_json(sys.argv[1])
        schedule = load_json(sys.argv[2])
    except (OSError, json.JSONDecodeError) as e:
        print(f"Failed to load input: {e}", file=sys.stderr)
        return 1

    if not isinstance(schedule, list):
        print("schedule.json must be a JSON array of session entries.", file=sys.stderr)
        return 1

    result = validate(dataset, schedule)

    print("=" * 70)
    print("HARD CONSTRAINTS")
    print("=" * 70)
    if result.passed:
        print("PASS — zero hard-constraint violations.\n")
    else:
        by_rule = defaultdict(list)
        for v in result.hard_violations:
            by_rule[v.rule].append(v.message)
        for rule, messages in by_rule.items():
            print(f"\n[{rule}] {len(messages)} violation(s):")
            for m in messages[:20]:
                print(f"  - {m}")
            if len(messages) > 20:
                print(f"  ... and {len(messages) - 20} more")
        print(f"\nTOTAL HARD VIOLATIONS: {len(result.hard_violations)}")

    print()
    print("=" * 70)
    print("SOFT CONSTRAINT SCORE")
    print("=" * 70)
    if result.soft_penalties:
        for label, penalty in result.soft_penalties:
            print(f"  -{penalty:6.2f}  {label}")
    else:
        print("  No soft-constraint penalties.")
    print(f"\nSOFT SCORE: {result.soft_score} / 100")

    print()
    print("=" * 70)
    print(f"RESULT: {'PASS' if result.passed else 'FAIL'} (hard constraints) "
          f"| Soft score: {result.soft_score}/100")
    print("=" * 70)

    return 0 if result.passed else 1


if __name__ == "__main__":
    sys.exit(main())
