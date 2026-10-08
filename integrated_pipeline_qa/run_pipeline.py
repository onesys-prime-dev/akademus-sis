#!/usr/bin/env python3
"""
Unified 2-Stage Pipeline Engine: Section Load Balancing -> CSP Timetable
Scheduling.

STAGE 1 — SECTION LOAD BALANCER
    For each course with N enrolled students and room capacity C:
        k = ceil(N / C)                      (minimum sections required)
        base, remainder = divmod(N, k)       (O(1) optimal partition)
        sizes = [base+1]*remainder + [base]*(k-remainder)
    Students are sliced into these pre-sized groups in enrollment order,
    producing concrete section objects (e.g. ITS101-SEC1, ITS101-SEC2),
    each inheriting its course's required_room_type, assigned_professor,
    and sessions_per_week.

    Corequisite pairing: for each (courseA, courseB) in
    dataset["corequisite_pairs"], Stage 1's generated sections are paired
    positionally (A's 1st section <-> B's 1st section, etc.) up to the
    shorter of the two section counts. Each paired section records the
    other's section_id — this is what Stage 2 uses to keep corequisite
    SECTIONS (not whole courses) from overlapping in time.

STAGE 2 — CSP TIMETABLE SCHEDULER
    Every section produced by Stage 1 becomes one or more schedulable
    variables (one per required weekly session), exactly as in the
    standalone scheduling engine (see scheduler_qa/solver.py): MRV
    (Minimum Remaining Values) variable ordering, forward-checked domains,
    and value-ordering scored for soft-quality (room-fit, corequisite
    proximity). Hard constraints enforced structurally: room overlap,
    professor overlap, capacity (against the SECTION's size, not the
    course's total enrollment), room type, professor availability, and
    corequisite-section overlap.

    This is the key integration point: Stage 2 never sees "a course with
    55 students" — it only ever sees discrete, already-right-sized
    sections. Decoupling sectioning from scheduling this way keeps the
    CSP's branching factor tied to the number of SECTIONS, not the number
    of STUDENTS, which is what prevents state-space explosion as
    enrollment grows (see Documentation/ for the full argument).

USAGE
    python run_pipeline.py pipeline_dataset.json integrated_schedule.json
"""
from __future__ import annotations

import json
import math
import random
import sys
from collections import defaultdict

MAX_BACKTRACK_STEPS = 400_000
MAX_ATTEMPTS = 30


def load_json(path: str) -> dict:
    with open(path, encoding="utf-8") as f:
        return json.load(f)


# ============================================================ STAGE 1


def partition_sizes(n: int, k: int) -> list[int]:
    base, remainder = divmod(n, k)
    return [base + 1] * remainder + [base] * (k - remainder)


def stage1_balance(dataset: dict) -> list[dict]:
    """Returns a list of section dicts:
    {section_id, course_id, department, students, size,
     required_room_type, assigned_professor, sessions_per_week,
     corequisite_section_id}
    """
    sections_by_course: dict[str, list[dict]] = {}

    for course in dataset["courses"]:
        cid = course["course_id"]
        n = len(course["students"])
        capacity = course["max_capacity"]
        k = max(1, math.ceil(n / capacity))
        sizes = partition_sizes(n, k)

        sections = []
        cursor = 0
        for i, size in enumerate(sizes, start=1):
            chunk = course["students"][cursor:cursor + size]
            cursor += size
            sections.append({
                "section_id": f"{cid}-SEC{i}",
                "course_id": cid,
                "department": course["department"],
                "students": chunk,
                "size": size,
                "required_room_type": course["required_room_type"],
                "assigned_professor": course["assigned_professor"],
                "sessions_per_week": course["sessions_per_week"],
                "corequisite_section_id": None,
            })
        sections_by_course[cid] = sections

    # Positional corequisite pairing between generated sections.
    for course_a, course_b in dataset.get("corequisite_pairs", []):
        secs_a = sections_by_course.get(course_a, [])
        secs_b = sections_by_course.get(course_b, [])
        for sa, sb in zip(secs_a, secs_b):
            sa["corequisite_section_id"] = sb["section_id"]
            sb["corequisite_section_id"] = sa["section_id"]

    all_sections = [s for secs in sections_by_course.values() for s in secs]
    return all_sections


# ============================================================ STAGE 2


class CSPScheduler:
    def __init__(self, dataset: dict, sections: list[dict], seed: int):
        self.rng = random.Random(seed)
        self.rooms = dataset["rooms"]
        self.room_by_id = {r["id"]: r for r in self.rooms}
        self.professors = {p["id"]: p for p in dataset["professors"]}
        self.timeslots = [t["id"] for t in dataset["timeslots"]]
        self.timeslot_info = {t["id"]: t for t in dataset["timeslots"]}
        self.sections = {s["section_id"]: s for s in sections}

        self.candidate_rooms: dict[str, list[dict]] = {}
        for sid, sec in self.sections.items():
            rooms = [
                r for r in self.rooms
                if r["type"] == sec["required_room_type"] and r["capacity"] >= sec["size"]
            ]
            rooms.sort(key=lambda r: r["capacity"])
            self.candidate_rooms[sid] = rooms

        self.free_timeslots: dict[str, list[str]] = {}
        for sid, sec in self.sections.items():
            prof = self.professors[sec["assigned_professor"]]
            unavailable = set(prof["unavailable_timeslots"])
            self.free_timeslots[sid] = [t for t in self.timeslots if t not in unavailable]

        self.variables: list[tuple[str, int]] = []
        for sid, sec in self.sections.items():
            for i in range(sec["sessions_per_week"]):
                self.variables.append((sid, i))

        self.assignment: dict[tuple[str, int], tuple[str, str]] = {}
        self.room_booked: set[tuple[str, str]] = set()
        self.prof_booked: set[tuple[str, str]] = set()
        self.section_slots: dict[str, set[str]] = defaultdict(set)
        self.steps = 0

    def domain_for(self, var: tuple[str, int]) -> list[tuple[str, str]]:
        sid, _ = var
        sec = self.sections[sid]
        prof_id = sec["assigned_professor"]

        forbidden = set()
        coreq_sid = sec.get("corequisite_section_id")
        if coreq_sid:
            forbidden |= self.section_slots.get(coreq_sid, set())

        used_by_self = self.section_slots.get(sid, set())

        options = []
        for t in self.free_timeslots[sid]:
            if t in used_by_self or t in forbidden:
                continue
            if (prof_id, t) in self.prof_booked:
                continue
            for r in self.candidate_rooms[sid]:
                if (r["id"], t) in self.room_booked:
                    continue
                options.append((t, r["id"]))
        return options

    def score_candidate(self, var: tuple[str, int], timeslot_id: str, room_id: str) -> float:
        sid, _ = var
        sec = self.sections[sid]
        room = self.room_by_id[room_id]

        waste = (room["capacity"] - sec["size"]) / max(1, room["capacity"])
        score = waste * 10.0

        coreq_sid = sec.get("corequisite_section_id")
        if coreq_sid:
            other_slots = self.section_slots.get(coreq_sid, set())
            day = self.timeslot_info[timeslot_id]["day"]
            hour = int(self.timeslot_info[timeslot_id]["start_time"].split(":")[0])
            best_gap = None
            for ot in other_slots:
                oinfo = self.timeslot_info[ot]
                if oinfo["day"] != day:
                    continue
                gap = abs(int(oinfo["start_time"].split(":")[0]) - hour)
                if best_gap is None or gap < best_gap:
                    best_gap = gap
            if other_slots:
                score += 5.0 if best_gap is None else best_gap * 1.0
        return score

    def ordered_domain(self, var: tuple[str, int]) -> list[tuple[str, str]]:
        options = self.domain_for(var)
        scored = [(self.score_candidate(var, t, r), t, r) for (t, r) in options]
        scored.sort(key=lambda x: (round(x[0], 3), self.rng.random()))
        return [(t, r) for (_, t, r) in scored]

    def assign(self, var: tuple[str, int], timeslot_id: str, room_id: str) -> None:
        sid, _ = var
        sec = self.sections[sid]
        prof_id = sec["assigned_professor"]
        self.assignment[var] = (timeslot_id, room_id)
        self.room_booked.add((room_id, timeslot_id))
        self.prof_booked.add((prof_id, timeslot_id))
        self.section_slots[sid].add(timeslot_id)

    def unassign(self, var: tuple[str, int]) -> None:
        sid, _ = var
        sec = self.sections[sid]
        prof_id = sec["assigned_professor"]
        timeslot_id, room_id = self.assignment.pop(var)
        self.room_booked.discard((room_id, timeslot_id))
        self.prof_booked.discard((prof_id, timeslot_id))
        self.section_slots[sid].discard(timeslot_id)

    def pick_next_variable(self, unassigned: list[tuple[str, int]]) -> tuple[tuple[str, int], int]:
        best_var, best_size = None, None
        for var in unassigned:
            size = len(self.domain_for(var))
            if best_size is None or size < best_size:
                best_var, best_size = var, size
                if size == 0:
                    break
        return best_var, best_size

    def solve(self) -> bool:
        return self._backtrack(list(self.variables))

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

    def to_output(self) -> list[dict]:
        sessions_by_section = defaultdict(list)
        for (sid, _idx), (timeslot_id, room_id) in self.assignment.items():
            info = self.timeslot_info[timeslot_id]
            sessions_by_section[sid].append({
                "day": info["day"], "start_time": info["start_time"], "room_id": room_id,
            })

        out = []
        for sid, sec in self.sections.items():
            sessions = sorted(sessions_by_section[sid], key=lambda s: (s["day"], s["start_time"]))
            out.append({
                "section_id": sid,
                "course_id": sec["course_id"],
                "professor": sec["assigned_professor"],
                "students": sec["students"],
                "sessions": sessions,
            })
        out.sort(key=lambda e: e["section_id"])
        return out


def stage2_schedule(dataset: dict, sections: list[dict]) -> list[dict]:
    for attempt in range(1, MAX_ATTEMPTS + 1):
        scheduler = CSPScheduler(dataset, sections, seed=attempt)
        print(f"  Stage 2 attempt {attempt}/{MAX_ATTEMPTS} (backtracking search)...")
        if scheduler.solve():
            print(f"  Solved in {scheduler.steps} search step(s), 0 of which backtracked past the first try." if scheduler.steps == len(scheduler.variables) else f"  Solved in {scheduler.steps} search steps.")
            return scheduler.to_output()
    raise RuntimeError("Stage 2 failed to find a feasible schedule within the attempt budget.")


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: python run_pipeline.py pipeline_dataset.json integrated_schedule.json", file=sys.stderr)
        return 1

    dataset = load_json(sys.argv[1])
    out_path = sys.argv[2]

    sys.setrecursionlimit(10_000)

    print("=== STAGE 1: Section Load Balancer ===")
    sections = stage1_balance(dataset)
    for sec in sections:
        print(f"  {sec['section_id']}: {sec['size']} students (course {sec['course_id']}, "
              f"room type {sec['required_room_type']}, coreq -> {sec['corequisite_section_id']})")

    print("\n=== STAGE 2: CSP Timetable Scheduler ===")
    schedule = stage2_schedule(dataset, sections)

    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(schedule, f, indent=2)

    total_sessions = sum(len(s["sessions"]) for s in schedule)
    print(f"\nWrote {len(schedule)} sections ({total_sessions} total sessions) to {out_path}.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
