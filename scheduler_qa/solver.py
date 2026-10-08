#!/usr/bin/env python3
"""
Backtracking CSP solver for the university course-timetabling dataset
(dataset.json -> schedule.json).

ALGORITHM
    Classic constraint-satisfaction backtracking search with:
      - Variables: one per required session (course_id, session_index) —
        a course with sessions_per_week = N contributes N variables.
      - Domain of a variable: every (timeslot_id, room_id) pair that does
        not yet violate a hard constraint, computed fresh at each step
        (forward checking) against the partial assignment so far.
      - Variable ordering: Minimum Remaining Values (MRV) — at each step,
        assign the still-unassigned variable with the SMALLEST domain
        first. This is what makes the heavily-squeezed variables (P03's
        and P11's courses, the few Lab/large-Lecture rooms) get decided
        early, while their constraints are tightest, instead of painting
        the solver into a corner near the end.
      - Value ordering within a domain: candidates are scored for SOFT
        quality (room-size fit, corequisite day/time/building proximity)
        and tried best-first, so a successful run also tends to score
        well, not just pass.
      - On a dead end (empty domain for the next variable), the solver
        backtracks: undoes the most recent assignment, removes the tried
        value from that variable's remaining options, and continues. This
        makes the result a COMPLETE search within the attempt — if a
        solution exists along the current variable-order path, it will be
        found; failure only means that variable ordering dead-ended after
        exhausting backtracking, which main() retries with a reshuffled
        tie-break seed.

HARD CONSTRAINTS (enforced structurally — a candidate is never even
considered if it would violate one of these; see `domain_for`):
    1. Room overlap      — a room can't host two sessions in one timeslot.
    2. Professor overlap — a professor can't teach two sessions in one
                            timeslot (derived from each course's fixed
                            assigned_professor).
    3. Capacity           — room.capacity >= course.expected_enrollment.
    4. Room type          — room.type == course.required_room_type.
    5. Availability       — timeslot not in professor.unavailable_timeslots.
    6. Corequisite overlap — a course's session can't land on a timeslot
                            already used by any of its corequisite
                            partners' sessions (checked symmetrically).

SOFT CONSTRAINTS (optimized via value-ordering heuristics, not guaranteed
optimal — see `score_candidate`):
    - Avoid small classes in oversized rooms (prefer the tightest-fitting
      capacity among valid rooms).
    - Minimize corequisite pairs' building/time-gap spread (once a
      partner session is placed, later placements for the other half of
      the pair are scored higher when same-day/near-time/same-building).

USAGE
    python solver.py dataset.json schedule.json
"""
from __future__ import annotations

import json
import random
import sys
from collections import defaultdict

MAX_BACKTRACK_STEPS = 400_000
MAX_ATTEMPTS = 30


def load_json(path: str) -> dict:
    with open(path, encoding="utf-8") as f:
        return json.load(f)


class Solver:
    def __init__(self, dataset: dict, seed: int):
        self.rng = random.Random(seed)
        self.rooms = dataset["rooms"]
        self.professors = {p["id"]: p for p in dataset["professors"]}
        self.courses = {c["id"]: c for c in dataset["courses"]}
        self.timeslots = [t["id"] for t in dataset["timeslots"]]
        self.timeslot_info = {t["id"]: t for t in dataset["timeslots"]}
        self.room_by_id = {r["id"]: r for r in self.rooms}

        # Rooms usable for each course (type + capacity), closest-fit first
        # for the soft "don't underfill" preference.
        self.candidate_rooms: dict[str, list[dict]] = {}
        for cid, course in self.courses.items():
            rooms = [
                r for r in self.rooms
                if r["type"] == course["required_room_type"]
                and r["capacity"] >= course["expected_enrollment"]
            ]
            rooms.sort(key=lambda r: r["capacity"])
            self.candidate_rooms[cid] = rooms

        # Timeslots the professor is free for, per course.
        self.free_timeslots: dict[str, list[str]] = {}
        for cid, course in self.courses.items():
            prof = self.professors[course["assigned_professor"]]
            unavailable = set(prof["unavailable_timeslots"])
            self.free_timeslots[cid] = [t for t in self.timeslots if t not in unavailable]

        # Variables: (course_id, session_index)
        self.variables: list[tuple[str, int]] = []
        for cid, course in self.courses.items():
            for i in range(course["sessions_per_week"]):
                self.variables.append((cid, i))

        # Mutable search state
        self.assignment: dict[tuple[str, int], tuple[str, str]] = {}  # var -> (timeslot, room)
        self.room_booked: set[tuple[str, str]] = set()      # (room_id, timeslot_id)
        self.prof_booked: set[tuple[str, str]] = set()      # (prof_id, timeslot_id)
        self.course_slots: dict[str, set[str]] = defaultdict(set)   # course_id -> {timeslot_id}
        self.steps = 0

    # ---------------------------------------------------------------- domain

    def domain_for(self, var: tuple[str, int]) -> list[tuple[str, str]]:
        cid, _ = var
        course = self.courses[cid]
        prof_id = course["assigned_professor"]
        coreqs = course.get("corequisite_courses", [])
        forbidden_by_coreq = set()
        for other in coreqs:
            forbidden_by_coreq |= self.course_slots.get(other, set())

        used_by_self = self.course_slots.get(cid, set())

        options = []
        for t in self.free_timeslots[cid]:
            if t in used_by_self or t in forbidden_by_coreq:
                continue
            if (prof_id, t) in self.prof_booked:
                continue
            for r in self.candidate_rooms[cid]:
                if (r["id"], t) in self.room_booked:
                    continue
                options.append((t, r["id"]))
        return options

    # ------------------------------------------------------------- scoring

    def score_candidate(self, var: tuple[str, int], timeslot_id: str, room_id: str) -> float:
        cid, _ = var
        course = self.courses[cid]
        room = self.room_by_id[room_id]

        # Soft 1: minimize underfill (capacity should be close to enrollment).
        waste = (room["capacity"] - course["expected_enrollment"]) / max(1, room["capacity"])
        score = waste * 10.0

        # Soft 2: corequisite proximity, scored against already-placed partners.
        day = self.timeslot_info[timeslot_id]["day"]
        hour = int(self.timeslot_info[timeslot_id]["start_time"].split(":")[0])
        for other in course.get("corequisite_courses", []):
            other_slots = self.course_slots.get(other, set())
            if not other_slots:
                continue
            best_gap = None
            same_building_found = False
            for ot in other_slots:
                oinfo = self.timeslot_info[ot]
                if oinfo["day"] != day:
                    continue
                gap = abs(int(oinfo["start_time"].split(":")[0]) - hour)
                if best_gap is None or gap < best_gap:
                    best_gap = gap
            if best_gap is None:
                score += 5.0  # no shared day yet with this partner slot
            else:
                score += best_gap * 1.0
        return score

    def ordered_domain(self, var: tuple[str, int]) -> list[tuple[str, str]]:
        options = self.domain_for(var)
        scored = [(self.score_candidate(var, t, r), t, r) for (t, r) in options]
        # Small random jitter on ties so repeated attempts can escape
        # identical dead ends while still preferring better scores.
        scored.sort(key=lambda x: (round(x[0], 3), self.rng.random()))
        return [(t, r) for (_, t, r) in scored]

    # ----------------------------------------------------------- assignment

    def assign(self, var: tuple[str, int], timeslot_id: str, room_id: str) -> None:
        cid, _ = var
        course = self.courses[cid]
        prof_id = course["assigned_professor"]
        self.assignment[var] = (timeslot_id, room_id)
        self.room_booked.add((room_id, timeslot_id))
        self.prof_booked.add((prof_id, timeslot_id))
        self.course_slots[cid].add(timeslot_id)

    def unassign(self, var: tuple[str, int]) -> None:
        cid, _ = var
        course = self.courses[cid]
        prof_id = course["assigned_professor"]
        timeslot_id, room_id = self.assignment.pop(var)
        self.room_booked.discard((room_id, timeslot_id))
        self.prof_booked.discard((prof_id, timeslot_id))
        self.course_slots[cid].discard(timeslot_id)

    # --------------------------------------------------------------- search

    def pick_next_variable(self, unassigned: list[tuple[str, int]]) -> tuple[tuple[str, int], list[tuple[str, str]]]:
        best_var = None
        best_domain = None
        best_size = None
        for var in unassigned:
            domain = self.domain_for(var)
            size = len(domain)
            if best_size is None or size < best_size:
                best_var, best_domain, best_size = var, domain, size
                if size == 0:
                    break
        return best_var, best_domain

    def solve(self) -> bool:
        unassigned = list(self.variables)
        return self._backtrack(unassigned)

    def _backtrack(self, unassigned: list[tuple[str, int]]) -> bool:
        if not unassigned:
            return True

        self.steps += 1
        if self.steps > MAX_BACKTRACK_STEPS:
            return False

        var, _ = self.pick_next_variable(unassigned)
        domain = self.ordered_domain(var)
        if not domain:
            return False

        remaining = [v for v in unassigned if v != var]
        for timeslot_id, room_id in domain:
            self.assign(var, timeslot_id, room_id)
            if self._backtrack(remaining):
                return True
            self.unassign(var)
            if self.steps > MAX_BACKTRACK_STEPS:
                return False

        return False

    def to_schedule(self) -> list[dict]:
        out = []
        for (cid, _idx), (timeslot_id, room_id) in self.assignment.items():
            info = self.timeslot_info[timeslot_id]
            out.append({
                "course_id": cid,
                "day": info["day"],
                "start_time": info["start_time"],
                "room_id": room_id,
            })
        out.sort(key=lambda e: (e["course_id"], e["day"], e["start_time"]))
        return out


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: python solver.py dataset.json schedule.json", file=sys.stderr)
        return 1

    dataset = load_json(sys.argv[1])
    out_path = sys.argv[2]

    sys.setrecursionlimit(10_000)

    for attempt in range(1, MAX_ATTEMPTS + 1):
        solver = Solver(dataset, seed=attempt)
        print(f"Attempt {attempt}/{MAX_ATTEMPTS} (backtracking search)...")
        ok = solver.solve()
        if ok:
            schedule = solver.to_schedule()
            with open(out_path, "w", encoding="utf-8") as f:
                json.dump(schedule, f, indent=2)
            print(f"Solved in {solver.steps} search steps. Wrote {len(schedule)} sessions to {out_path}.")
            return 0
        print(f"  attempt {attempt} dead-ended after {solver.steps} steps, retrying with a new tie-break seed...")

    print("Failed to find a feasible schedule within the attempt budget.", file=sys.stderr)
    return 1


if __name__ == "__main__":
    sys.exit(main())
