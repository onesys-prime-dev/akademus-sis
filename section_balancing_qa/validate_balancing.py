#!/usr/bin/env python3
"""
Strict validator + diagnostic/recommendation engine for student section
load balancing (enrollment_dataset.json -> balanced_sections.json).

USAGE
    python validate_balancing.py enrollment_dataset.json balanced_sections.json

HARD RULES (any violation -> exit code 1)
    H1. No section's student count exceeds its course's room max_capacity.
    H2. Every enrolled student is assigned to EXACTLY ONE section of their
        course: no student missing, no duplicate assignment, no foreign
        student id that doesn't belong to that course's roster.
    H3. The number of sections actually produced for a course must equal
        the structurally required minimum k = ceil(N / max_capacity). Too
        few sections means some section would have to exceed capacity;
        too many is wasteful and (per H-adjacent soft reasoning) unneeded.

SOFT RULE: load-balance variance (threshold is "worse than achievable",
not literally > 0 — see the note in `evaluate_course` for why)
    For a course with N students split into k sections, this validator
    is given the formula:
        L_target = ceil(N / k)
        Penalty  = sum_i (ActualSize_i - L_target)^2

    IMPORTANT DISCLOSED DESIGN DECISION: taken completely literally, this
    formula is non-zero even for the mathematically best possible split
    whenever N is not evenly divisible by k (e.g. N=82, k=3 -> the best
    possible split is [28, 27, 27], but against the single scalar
    L_target=28 that still scores (0)^2+(1)^2+(1)^2 = 2, not 0). Flagging
    every such case as a failure would mean the validator rejects the
    optimal achievable schedule for roughly half of all (N, k) pairs —
    not a meaningful signal for "is this balanced well."

    So this validator computes the formula exactly as specified, but
    compares the ACTUAL penalty against the MINIMUM ACHIEVABLE penalty
    for that (N, k) — i.e. the penalty of the divmod-optimal partition
    (the same partition `balancing_solver.py` produces). A course only
    fails the soft check if its actual penalty is STRICTLY WORSE than
    that achievable minimum, meaning real, fixable imbalance exists.
    Both the raw formula penalty and the achievable-minimum baseline are
    printed in every report, so nothing is hidden.

EXIT CODE
    0 if every course passes all hard rules and is at its achievable
      soft-balance optimum.
    1 otherwise (full diagnostic + recommended-fix report is printed).
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


def suggest_transfers(section_ids: list[str], actual_sizes: list[int], ideal_sizes: list[int]) -> list[str]:
    """Greedily pair current excess sections against current deficit
    sections (largest excess -> largest deficit) and describe the moves
    needed to reach the ideal partition. Both lists are paired by the
    same descending-sorted order used to compute the penalty, so index i
    in `ideal_sizes` is the best achievable size for the i-th largest
    actual section."""
    paired = sorted(zip(section_ids, actual_sizes, ideal_sizes), key=lambda x: -x[1])
    diffs = [(sid, actual - ideal) for sid, actual, ideal in paired]

    excess = [[sid, d] for sid, d in diffs if d > 0]
    deficit = [[sid, -d] for sid, d in diffs if d < 0]

    moves = []
    ei, di = 0, 0
    while ei < len(excess) and di < len(deficit):
        e_sid, e_amt = excess[ei]
        d_sid, d_amt = deficit[di]
        move = min(e_amt, d_amt)
        if move > 0:
            moves.append(f"Move {move} student(s) from {e_sid} to {d_sid}")
        excess[ei][1] -= move
        deficit[di][1] -= move
        if excess[ei][1] == 0:
            ei += 1
        if deficit[di][1] == 0:
            di += 1
    return moves


def evaluate_course(course: dict, sections: list[dict]) -> tuple[bool, list[str]]:
    course_id = course["course_id"]
    capacity = course["max_capacity"]
    roster = course["students"]
    n = len(roster)
    roster_set = set(roster)

    messages: list[str] = []
    passed = True

    # ---------------------------------------------------- H1: capacity
    for sec in sections:
        size = len(sec["students"])
        if size > capacity:
            passed = False
            messages.append(
                f"[FAIL] Course {course_id}: Section {sec['section_id']} has {size} students, "
                f"exceeding room max_capacity {capacity}."
            )
            messages.append(
                f"[RECOMMENDED FIX] Split Section {sec['section_id']} — move "
                f"{size - capacity} student(s) out to a new or less-full section of {course_id}."
            )

    # ---------------------------------------------------- H2: complete, exclusive partition
    seen: dict[str, str] = {}
    duplicates: list[tuple[str, str, str]] = []
    foreign: list[tuple[str, str]] = []
    for sec in sections:
        for student in sec["students"]:
            if student not in roster_set:
                foreign.append((student, sec["section_id"]))
                continue
            if student in seen:
                duplicates.append((student, seen[student], sec["section_id"]))
            else:
                seen[student] = sec["section_id"]

    missing = sorted(roster_set - seen.keys())

    if foreign:
        passed = False
        for student, sid in foreign[:10]:
            messages.append(f"[FAIL] Course {course_id}: Section {sid} contains student '{student}', who is not enrolled in {course_id}.")
        messages.append(f"[RECOMMENDED FIX] Remove {len(foreign)} foreign student assignment(s) not belonging to {course_id}'s roster.")

    if duplicates:
        passed = False
        for student, first_sid, second_sid in duplicates[:10]:
            messages.append(f"[FAIL] Course {course_id}: Student '{student}' is assigned to both {first_sid} and {second_sid}.")
        messages.append(f"[RECOMMENDED FIX] Remove {len(duplicates)} duplicate assignment(s) so each student appears in exactly one section.")

    if missing:
        passed = False
        messages.append(f"[FAIL] Course {course_id}: {len(missing)} enrolled student(s) are not assigned to any section (e.g. {missing[:5]}).")
        messages.append(f"[RECOMMENDED FIX] Assign the {len(missing)} unplaced student(s) to a section with remaining capacity, or open a new section.")

    # ---------------------------------------------------- H3: correct section count
    required_k = max(1, math.ceil(n / capacity))
    actual_k = len(sections)

    if actual_k != required_k:
        passed = False
        direction = "Increase" if actual_k < required_k else "Decrease"
        messages.append(
            f"[FAIL] Course {course_id}: {actual_k} section(s) were produced, but {n} students against "
            f"room capacity {capacity} structurally requires {required_k}."
        )
        messages.append(
            f"[RECOMMENDED FIX] {direction} section count k from {actual_k} to {required_k} for course "
            f"{course_id} as demand ({n}) {'exceeds' if actual_k < required_k else 'does not require'} "
            f"capacity ({capacity}) at the current section count."
        )
        # Section-count mismatch makes the variance comparison below meaningless
        # for this course (wrong k entirely) — skip it.
        return passed, messages

    if not sections:
        return passed, messages

    # ------------------------------------------- Soft: balance variance
    section_ids = [s["section_id"] for s in sections]
    actual_sizes = [len(s["students"]) for s in sections]

    l_target = math.ceil(n / required_k)
    actual_penalty = sum((size - l_target) ** 2 for size in actual_sizes)

    ideal_sizes = partition_sizes(n, required_k)
    min_achievable_penalty = sum((size - l_target) ** 2 for size in ideal_sizes)

    if actual_penalty > min_achievable_penalty:
        passed = False
        biggest = max(actual_sizes)
        smallest = min(actual_sizes)
        biggest_sid = section_ids[actual_sizes.index(biggest)]
        smallest_sid = section_ids[actual_sizes.index(smallest)]
        messages.append(
            f"[FAIL] Course {course_id}: Section {biggest_sid} has {biggest} students, Section "
            f"{smallest_sid} has {smallest} student(s). Load imbalance delta = {biggest - smallest}."
        )
        messages.append(
            f"  (variance penalty = {actual_penalty}, best achievable for N={n}/k={required_k} is "
            f"{min_achievable_penalty} with sizes {sorted(ideal_sizes, reverse=True)})"
        )
        for move in suggest_transfers(section_ids, actual_sizes, sorted(ideal_sizes, reverse=True)):
            messages.append(f"[RECOMMENDED FIX] {move} to approach the target distribution "
                             f"{sorted(ideal_sizes, reverse=True)}.")
    else:
        messages.append(
            f"[OK] Course {course_id}: {actual_k} section(s), sizes {sorted(actual_sizes, reverse=True)} "
            f"(target {sorted(ideal_sizes, reverse=True)}), variance penalty {actual_penalty} "
            f"(achievable minimum {min_achievable_penalty})."
        )

    return passed, messages


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: python validate_balancing.py enrollment_dataset.json balanced_sections.json", file=sys.stderr)
        return 1

    try:
        dataset = load_json(sys.argv[1])
        sections = load_json(sys.argv[2])
    except (OSError, json.JSONDecodeError) as e:
        print(f"Failed to load input: {e}", file=sys.stderr)
        return 1

    sections_by_course = defaultdict(list)
    for sec in sections:
        sections_by_course[sec["course_id"]].append(sec)

    all_passed = True
    print("=" * 78)
    print("STUDENT SECTION LOAD BALANCING — VALIDATION REPORT")
    print("=" * 78)

    for course in dataset["courses"]:
        course_id = course["course_id"]
        print(f"\n--- {course_id} "
              f"({len(course['students'])} students, capacity {course['max_capacity']}) ---")
        course_sections = sorted(sections_by_course.get(course_id, []), key=lambda s: s["section_id"])
        passed, messages = evaluate_course(course, course_sections)
        all_passed = all_passed and passed
        for m in messages:
            print(m)

    print()
    print("=" * 78)
    print(f"RESULT: {'PASS' if all_passed else 'FAIL'}")
    print("=" * 78)

    return 0 if all_passed else 1


if __name__ == "__main__":
    sys.exit(main())
