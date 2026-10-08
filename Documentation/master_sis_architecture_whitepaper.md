# Master SIS Architecture Whitepaper
## Scheduling, Sectioning & Conflict-Resolution Engine Suite

**Document type:** Master Executive Whitepaper
**Scope:** Consolidation of 4 independently validated subsystems + master orchestrator
**Status:** ALL SUBSYSTEMS VALIDATED — 100% hard-constraint compliance, 0 backtracks, ~2.03s total suite execution

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Subsystem 1: Timetable Scheduling Engine (`scheduler_qa`)](#2-subsystem-1-timetable-scheduling-engine-scheduler_qa)
3. [Subsystem 2: Section Load Balancer (`section_balancing_qa`)](#3-subsystem-2-section-load-balancer-section_balancing_qa)
4. [Subsystem 3: Integrated 2-Stage Pipeline (`integrated_pipeline_qa`)](#4-subsystem-3-integrated-2-stage-pipeline-integrated_pipeline_qa)
5. [Subsystem 4: Student Schedule Conflict Auto-Resolver (`student_audit_qa`)](#5-subsystem-4-student-schedule-conflict-auto-resolver-student_audit_qa)
6. [System Orchestration & Diagnostic QA Suite (`main.py`)](#6-system-orchestration--diagnostic-qa-suite-mainpy)
7. [Appendix: Consolidated Metrics](#7-appendix-consolidated-metrics)

---

## 1. Executive Summary

### 1.1 Architectural overview

The Student Information System's academic-operations core is built from
four independently-developed, independently-validated subsystems, each
solving a distinct combinatorial problem in university scheduling, plus
a master orchestrator that runs and audits all four as a single suite:

| # | Subsystem | Problem Solved |
|---|---|---|
| 1 | `scheduler_qa` | Assign every course session a (day, time, room) with no overlaps |
| 2 | `section_balancing_qa` | Split oversized course enrollments into balanced sections |
| 3 | `integrated_pipeline_qa` | Compose (1) and (2) into one enrollment-to-timetable pipeline |
| 4 | `student_audit_qa` | Catch and resolve conflicts in an *individual* student's combined schedule |

Each was built, tested, and documented as a standalone unit before being
composed — the architecture is deliberately a suite of narrow, provably
correct tools rather than one monolithic scheduler, for the same reason
a compiler is built as separate lexer/parser/codegen stages: each stage
is independently testable, independently provable, and composable
without re-deriving correctness at the seams.

### 1.2 Mathematical classification

Both of the suite's core combinatorial problems belong to well-known
**NP-hard / NP-complete** complexity classes:

- **University course timetabling** (Subsystem 1) generalizes graph
  coloring and multi-dimensional bin packing: assigning rooms and times
  to courses such that no professor, room, or corequisite relationship
  conflicts is NP-complete in general; no known algorithm solves the
  general case in polynomial time.
- **Student section load balancing** (Subsystem 2) is an instance of
  **Multiway Number Partitioning** (Balanced Bin Packing): dividing N
  students into k groups under a capacity ceiling while minimizing size
  variance.

Neither subsystem attempts to "solve NP-hardness away." Instead, each
exploits the **specific structure** of its problem instance — heuristic
variable ordering for the CSP (Subsystem 1), and a closed-form
arithmetic partition for the partitioning problem (Subsystem 2) — to
reach a provably optimal or near-optimal solution in practical,
polynomial time for realistic university-scale inputs.

### 1.3 Microservice decoupling strategy

The suite's defining architectural decision is **decoupling sectioning
from scheduling** (formalized in Subsystem 3): a scheduler that tries to
reason about raw student enrollment directly must either explode its
variable count to one-per-student, or fail outright once enrollment
exceeds a single room's capacity. Resolving "how many sections, how
many students in each" *before* scheduling begins — as pure O(1)
arithmetic, not search — means the CSP scheduler's branching factor is
bound to the number of **sections**, never the number of **students**.
This single design choice is what keeps the whole suite tractable at
scale.

### 1.4 Suite-wide performance summary

Running all four subsystems end-to-end through the master orchestrator
(`main.py`):

```
┌────────────────────────────────────┬────────┬───────────┬────────────┬────────┐
│ Module                             │ Time   │ Hard Pass │ Soft Delta │ Status │
├────────────────────────────────────┼────────┼───────────┼────────────┼────────┤
│ Scheduler QA (CSP + MRV)           │ 0.608s │ 100.0%    │ 72.0       │ [PASS] │
│ Section Load Balancing QA          │ 0.412s │ 100.0%    │ 0.0        │ [PASS] │
│ Integrated 2-Stage Pipeline QA     │ 0.422s │ 100.0%    │ 0.0        │ [PASS] │
│ Student Conflict Auto-Resolver QA  │ 0.595s │ 100.0%    │ n/a        │ [PASS] │
└────────────────────────────────────┴────────┴───────────┴────────────┴────────┘

OVERALL: PASS   (total ≈ 2.03s)
```

**100% hard-constraint compliance across all four subsystems, zero CSP
backtracking anywhere in the suite, total execution time ≈ 2.03 seconds**
— for a suite collectively solving two distinct NP-hard problem classes
across 40+ courses, 150+ students, and 10+ rooms of test data.

---

## 2. Subsystem 1: Timetable Scheduling Engine (`scheduler_qa`)

### 2.1 Formulation

Modeled as a classic **Constraint Satisfaction Problem**: one variable
per required weekly session (a course meeting `N` times/week contributes
`N` variables — 67 variables total across 40 courses), whose domain is
every `(timeslot, room)` pair not yet ruled out by a hard constraint,
recomputed fresh at each step against the current partial assignment
(forward checking).

### 2.2 Algorithmic strategy: MRV, zero-backtrack resolution

At every step, the solver assigns the **still-unassigned variable with
the smallest live domain first** (Minimum Remaining Values). This
matters because the test dataset's bottlenecks — two professors each
carrying 5 courses against 13–14 blocked timeslots out of 45, tight
Lab-capacity-to-enrollment fits, only 2 rooms large enough for the
biggest Lecture courses — are not evenly distributed. MRV forces the
solver to resolve these tight constraints **while the most options still
exist**, rather than leaving them to dead-end against an already-full
schedule.

**Result: all 67 sessions placed in exactly 67 search steps, zero
backtracking required.** Every first decision the solver made remained
valid for the entire search — the strongest possible signal that the
heuristic ordering matched the problem's structure.

### 2.3 Hard constraints vs. soft penalties

| Hard constraint | Result |
|---|---|
| Room overlap | 0 violations |
| Professor overlap | 0 violations |
| Capacity | 0 violations |
| Room type match | 0 violations |
| Professor availability | 0 violations |
| Corequisite overlap | 0 violations |

**Soft score: 28.0 / 100 — and this is the achievable ceiling, not a
shortfall.** All 72 lost points trace to a single cause: 9 Lecture+Lab
corequisite pairs (e.g. `CS101`/`CS101L`), each incurring an -8.00
"split across buildings" penalty. Cross-referencing the room list by
building shows **no building on the test campus contains both a
Lecture-type and a Lab-type room** (North Hall / South Wing = Lecture
only; East Annex = Lab only) — every possible placement of these 9
pairs is split across buildings by construction. Meanwhile, the
solver's value-ordering heuristic achieved **zero underfill penalty**
and **zero corequisite time-gap penalty** — every pair got the best
possible same-day proximity. The 28.0/100 is therefore a **facilities
layout constraint**, not an algorithmic one; fixing it requires
co-locating a Lab with Lecture capacity, not changing the solver.

---

## 3. Subsystem 2: Section Load Balancer (`section_balancing_qa`)

### 3.1 Formulation

An instance of **Balanced Bin Packing / Multiway Number Partitioning**:
given `N` enrolled students and room capacity `C`, divide `N` into the
fewest groups that fit under `C`, while keeping group sizes as close as
possible.

### 3.2 Algorithmic strategy: O(1) partitioning

```
k = ceil(N / C)             # minimum sections required — a hard floor
L = ceil(N / k)             # the size of the largest section in a perfect split
base, remainder = divmod(N, k)
sizes = [base+1]*remainder + [base]*(k-remainder)   # provably minimum-spread partition
```

No search is involved — this is pure integer arithmetic, computed once
per course. The four test cases confirm it reaches the intuitively
correct answer every time:

| Course | N | C | k | Sizes |
|---|---|---|---|---|
| ITS101 | 14 | 13 | 2 | [7, 7] |
| CS101 | 82 | 30 | 3 | [28, 27, 27] |
| ENG200 | 43 | 20 | 3 | [15, 14, 14] |
| BUS400 | 18 | 25 | 1 | [18] |

### 3.3 Achievable-optimum quality metric

The naive soft-balance formula `Σ(ActualSize − L_target)²` is **non-zero
even for the best possible split** whenever `N` doesn't divide evenly by
`k` — scoring the provably optimal `[28, 27, 27]` split for 82 students
over 3 sections as `(28-28)² + (27-28)² + (27-28)² = 2`, not 0. A
validator flagging any non-zero result as failure would reject roughly
half of all legitimately optimal schedules.

The fix: compare the **actual** penalty against the penalty of the
**divmod-optimal partition** for that same `(N, k)` — the best any
solver could possibly achieve — and fail only when actual is strictly
worse:

```
FAIL only if:  Penalty_actual  >  Penalty_min_achievable
```

This was proven to still catch real imbalance: a self-test deliberately
rebalancing ITS101 into a lopsided `[13, 1]` split scored
`Penalty_actual = 72` against `Penalty_min_achievable = 0` — correctly
flagged, with the engine recommending the exact fix: *"Move 6 student(s)
from ITS101-SEC1 to ITS101-SEC2."*

**Result: 4/4 test cases hit their exact divmod-optimal partition —
`Penalty_actual − Penalty_min = 0` for every course.**

---

## 4. Subsystem 3: Integrated 2-Stage Pipeline (`integrated_pipeline_qa`)

### 4.1 Pipeline flow

```
 raw enrollment demand         Stage 1 output                final timetable
┌───────────────────┐  Stage 1  ┌────────────────────┐ Stage 2  ┌────────────────────┐
│ courses, N         │ ────────▶│ discrete, sized      │────────▶│ sections + sessions │
│ students, rooms,   │ Section  │ SECTION objects      │  CSP    │ + rooms + days/times │
│ professors         │ Balancer │ (coreq-paired)       │Scheduler│ (validated)          │
└───────────────────┘           └────────────────────┘          └────────────────────┘
```

Stage 1 (Subsystem 2's engine) converts raw enrollment into `k`
right-sized section objects and resolves **course-level** corequisites
into **section-level** pairings (ITS101's 1st section paired with
ITS101L's 1st section, specifically — not every section of either
course against every section of the other). Stage 2 (Subsystem 1's
engine) then schedules those sections exactly as it would any other CSP
instance.

### 4.2 State-space pruning: why decoupling prevents explosion

A CSP's search cost is driven by its variable count and per-variable
branching factor. Without Stage 1, Stage 2 would have to reason about
either one variable per **student** (combinatorially larger) or one
variable per **course** — which is simply unsolvable once `N` exceeds
any single room's capacity, since no real room could ever satisfy that
variable's capacity constraint. Stage 1's O(1) partitioning resolves
this *before* search begins, so:

- **Stage 2's variable count is bounded by sections × sessions/week,
  never by student count.** The test dataset's 103 enrolled students
  produced only **12 CSP variables** across 7 sections.
- **Students are invisible to Stage 2's search entirely** — they are
  payload Stage 1 already finalized.

### 4.3 Audit results

```
=== STAGE 1: Section Load Balancer ===
  ITS101-SEC1: 7 students ... coreq -> ITS101L-SEC1
  ITS101-SEC2: 7 students ... coreq -> ITS101L-SEC2
  ITS101L-SEC1: 7 students ... coreq -> ITS101-SEC1
  ITS101L-SEC2: 7 students ... coreq -> ITS101-SEC2
  CS101-SEC1: 28 students ... coreq -> None
  CS101-SEC2: 27 students ... coreq -> None
  MATH201-SEC1: 20 students ... coreq -> None

=== STAGE 2: CSP Timetable Scheduler ===
  Solved in 12 search step(s), 0 of which backtracked past the first try.

STAGE 1: PASS   |   STAGE 2: PASS
OVERALL RESULT: PASS
```

An independent validator (re-deriving every fact from the dataset, never
trusting the pipeline's internal state) confirmed zero room collisions,
zero professor double-bookings, 100% capacity/type fit, and zero
corequisite-section overlaps across all multi-department courses
(Information Technology, Computer Science, Mathematics). A deliberately
forced room collision (self-test) was correctly caught and attributed
to Stage 2 specifically, with Stage 1's audit remaining PASS — proof the
dual-stage audit discriminates real failures rather than passing
everything unconditionally.

---

## 5. Subsystem 4: Student Schedule Conflict Auto-Resolver (`student_audit_qa`)

### 5.1 Problem domain

A room scheduler and a section-balancing engine can both be individually
perfect and a student can still end up with an impossible personal
schedule — the moment they register for two sections that were each
scheduled validly on their own, but which happen to meet at the same
time. This is invisible to both other subsystems: it is purely a
property of one specific student's *combination* of registrations, not
of the master timetable or the balancing engine in isolation.

### 5.2 Resolution engine

**Pairwise overlap detection:** each student's registered sections are
flattened into a personal weekly schedule, and every pair of *different*
sections is checked for a shared `(day, start_time)`.

**Capacity-aware auto-resolution:** on a detected conflict, the resolver
searches the master timetable for an alternate section of the same
course satisfying **both** hard constraints simultaneously:

1. **Time Non-Interference** — zero overlap with the student's remaining schedule.
2. **Capacity Limit Enforcement** — `current_enrollment(alt) + 1 ≤ max_capacity(alt)`.

### 5.3 Atomic state updates & fail-safe logic

A successful reassignment updates both the student's registration and
the live section headcounts **together** — the source section is
decremented and the target incremented as one atomic step, so headcounts
are never observed in an inconsistent in-between state. If no alternate
satisfies both constraints, the resolver does **not** partially mutate
anything — it leaves the student's registration untouched and reports:

```
[FAIL - CAPACITY EXHAUSTED] No alternate section ... satisfies both
    time non-interference and capacity headroom.
[RECOMMENDED FIX] Open additional section for course X or Y, or
    increase room capacity for an existing alternate section.
```

### 5.4 Verified result

Tested against 10 students (11 sections), with one deliberate edge case
(`Student X`, registered into two sections colliding at Mon/Wed 09:00):

| Stage | Result |
|---|---|
| Pre-fix audit | 9/10 conflict-free, 1 real conflict detected |
| Auto-resolution | `Student X` moved CS101-SEC1 → CS101-SEC2, landing at exactly 4/4 capacity (tight boundary, not exceeded) |
| Post-fix schedule audit | **10/10 students conflict-free**, exit code 0 |
| Post-fix capacity audit | **All 11 sections within capacity**, exit code 0 |

Fault-injection self-tests additionally confirmed: (a) the resolver
correctly falls back to the *other* side of a conflict when the first
alternate loses headroom, and (b) a genuine dead-end (both sides
exhausted) correctly triggers `[FAIL - CAPACITY EXHAUSTED]`, leaves the
student's data untouched, and exits 1 — proving the 100% pass rate on
the real dataset reflects genuine correctness, not a validator that
never fails.

---

## 6. System Orchestration & Diagnostic QA Suite (`main.py`)

### 6.1 Architecture

A root-level master CLI orchestrator runs all four subsystems as
isolated subprocesses — never importing their code directly, invoking
each exactly as a human operator would (`python <script> <args>`) with
`cwd` scoped to that subsystem's own directory. This guarantees zero
cross-module file contention and zero shared process state; a failure
in one subsystem cannot corrupt another's working files.

**Cross-platform resilience:** Windows consoles default to a legacy
CP1252 codepage that cannot render the dashboard's Unicode box-drawing
characters. The orchestrator attempts a UTF-8 stdout/stderr reconfigure
at startup and falls back to ASCII box-drawing (`+`, `-`, `|`) if that
reconfigure isn't available — discovered and fixed during this suite's
own verification, not a hypothetical concern.

**CLI flags:**

| Flag | Behavior |
|---|---|
| *(none)* | Run all 4 modules sequentially |
| `--module <name>` | Run exactly one (`scheduler`, `balancing`, `integrated`, `student_audit`) |
| `--verbose` | Stream full stdout/stderr from every step live |
| `--report` | Write an aggregated JSON summary to `master_audit_report.json` |

### 6.2 Benchmark summary

```
┌────────────────────────────────────┬────────┬───────────┬────────────┬────────┐
│ Module                             │ Time   │ Hard Pass │ Soft Delta │ Status │
├────────────────────────────────────┼────────┼───────────┼────────────┼────────┤
│ Scheduler QA (CSP + MRV)           │ 0.608s │ 100.0%    │ 72.0       │ [PASS] │
│ Section Load Balancing QA          │ 0.412s │ 100.0%    │ 0.0        │ [PASS] │
│ Integrated 2-Stage Pipeline QA     │ 0.422s │ 100.0%    │ 0.0        │ [PASS] │
│ Student Conflict Auto-Resolver QA  │ 0.595s │ 100.0%    │ n/a        │ [PASS] │
└────────────────────────────────────┴────────┴───────────┴────────────┴────────┘
OVERALL: PASS   (total ≈ 2.03s)
```

### 6.3 Mutation testing & fault-injection validation summary

Every subsystem's diagnostic/recommendation engine was proven capable of
genuinely **failing**, not merely never failing, via deliberate fault
injection — confirming the suite's 100% pass rate on real data reflects
correctness, not a validator with no teeth:

| Subsystem | Fault injected | Result |
|---|---|---|
| Scheduler | Deliberately broken schedule (room/professor overlaps, capacity, type, availability violations) | All 5+ hard-violation categories correctly flagged, exit code 1 |
| Section Balancer | Lopsided 13/1 split of a 14-student course | Correctly flagged (`penalty 72 > min 0`), exact "move 6 students" fix recommended |
| Integrated Pipeline | Forced room double-booking between two unrelated sections | Correctly isolated to Stage 2 only (Stage 1 remained PASS), exact fix recommended |
| Student Conflict Resolver | Two scenarios: partial capacity loss (fallback test) and total capacity exhaustion (dead-end test) | Fallback correctly repaired via the other conflict side; dead-end correctly reported `[FAIL - CAPACITY EXHAUSTED]` with zero data corruption |

In every case, the diagnostic engine identified the exact failure
location, computed a concrete and verified remediation, and (where
resolution wasn't possible) left system state unmodified rather than
guessing.

---

## 7. Appendix: Consolidated Metrics

| Metric | Value |
|---|---|
| Total subsystems | 4 |
| Total suite execution time | ≈ 2.03s |
| Overall hard-constraint pass rate | 100% |
| Total CSP variables solved (Subsystem 1) | 67 |
| Backtracking steps required (Subsystem 1) | 0 |
| Total CSP variables solved (Subsystem 3, Stage 2) | 12 |
| Backtracking steps required (Subsystem 3) | 0 |
| Section-balancing test cases at achievable optimum | 4 / 4 |
| Integrated pipeline courses audited | 4 (multi-department) |
| Students audited for personal schedule conflicts | 10 |
| Students with real conflicts detected | 1 (correctly identified, zero false positives) |
| Students conflict-free after auto-resolution | 10 / 10 |
| Sections exceeding capacity after resolution | 0 |
| Fault-injection self-tests run across the suite | 6 |
| Fault-injection self-tests correctly caught | 6 / 6 |
