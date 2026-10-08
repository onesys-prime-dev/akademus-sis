#!/usr/bin/env python3
"""
Dynamic Target Capacity & Multiway Number Partitioning solver for student
section load balancing.

ALGORITHM
For each course with N enrolled students and a room max_capacity C:
    1. Compute the minimum number of sections required:
           k = ceil(N / C)
    2. Partition N as evenly as possible across k sections using integer
       divmod (the exact multiway partition that minimizes the spread
       between the largest and smallest section):
           base, remainder = divmod(N, k)
           sizes = [base + 1] * remainder + [base] * (k - remainder)
       This yields `remainder` sections of size (base+1) and the rest of
       size `base` — e.g. N=82, k=3 -> base=27, remainder=1 -> [28, 27, 27].
    3. Assign students to sections in enrollment order, largest section
       first (SEC1 gets the first `sizes[0]` students, SEC2 the next
       `sizes[1]`, etc.) — a stable, reproducible split.

This partition is provably optimal for minimizing max-min spread (and,
equivalently, the sum-of-squared-deviation variance penalty used by
validate_balancing.py) for a fixed k: no other integer partition of N
into k parts has a smaller spread than a divmod split.

USAGE
    python balancing_solver.py enrollment_dataset.json balanced_sections.json
"""
from __future__ import annotations

import json
import math
import sys


def load_json(path: str) -> dict:
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def partition_sizes(n: int, k: int) -> list[int]:
    base, remainder = divmod(n, k)
    return [base + 1] * remainder + [base] * (k - remainder)


def solve_course(course: dict) -> list[dict]:
    course_id = course["course_id"]
    capacity = course["max_capacity"]
    students = course["students"]
    n = len(students)

    k = max(1, math.ceil(n / capacity))
    sizes = partition_sizes(n, k)

    sections = []
    cursor = 0
    for i, size in enumerate(sizes, start=1):
        chunk = students[cursor:cursor + size]
        cursor += size
        sections.append({
            "section_id": f"{course_id}-SEC{i}",
            "course_id": course_id,
            "students": chunk,
        })
    return sections


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: python balancing_solver.py enrollment_dataset.json balanced_sections.json", file=sys.stderr)
        return 1

    dataset = load_json(sys.argv[1])
    out_path = sys.argv[2]

    all_sections = []
    for course in dataset["courses"]:
        sections = solve_course(course)
        all_sections.extend(sections)
        sizes = [len(s["students"]) for s in sections]
        print(f"{course['course_id']}: {len(course['students'])} students, capacity {course['max_capacity']} "
              f"-> {len(sections)} section(s), sizes {sizes}")

    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(all_sections, f, indent=2)

    print(f"Wrote {len(all_sections)} sections to {out_path}.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
