# Student Section Load Balancing Engine: Architecture & Validation

**Subsystem:** Student Section Load Balancing (`section_balancing_qa/`)
**Document type:** Technical case study
**Status:** Validated — 4/4 test cases PASS, 0 avoidable imbalance

---

## 1. Executive Summary

Splitting a large enrolled class into multiple sections under a fixed
room capacity is an instance of **Multiway Number Partitioning** (also
known as **Balanced Bin Packing**): given `N` students and a maximum
capacity `C` per room, divide `N` into the fewest possible groups such
that no group exceeds `C`, while keeping the groups as close in size to
each other as possible. Done naively — e.g. filling sections
first-come-first-served until each hits capacity — this produces
**lopsided distributions**: a course of 14 students against a 13-seat
room would naively yield one section of 13 and a second section of just
1, rather than the sensible 7/7 split.

This engine (`balancing_solver.py`) eliminates that failure mode by
**dynamically computing the target section count and target load before
assigning a single student**, rather than greedily filling sections and
dealing with the leftover. Student assignment becomes the easy, final
step of an already-solved partitioning problem, not a source of
imbalance itself.

The engine was tested against four deliberately distinct cases —
an evenly-divisible split, a large cohort with a remainder, an
odd-number squeeze, and a single-section fit — and an independent
diagnostic validator (`validate_balancing.py`) confirmed **all four
produced the mathematically optimal partition**, with **zero capacity
violations, zero misplaced/duplicated students, and zero avoidable load
imbalance**.

---

## 2. Mathematical Strategy & Algorithmic Design

### 2.1 Required section count

For a course with `N` enrolled students and a room capacity `C`, the
minimum number of sections that could possibly hold everyone without
exceeding capacity is:

```
k = ceil(N / C)
```

This is a hard floor, not a target to aim near — any fewer sections and
at least one would mathematically have to exceed `C` regardless of how
students are arranged.

### 2.2 Dynamic target load

Given that section count `k`, the ideal average load per section is:

```
L = ceil(N / k)
```

`L` is the ceiling of the true average `N/k`, so it represents the size
of the *largest* section in a perfectly balanced split — every other
section will be `L` or `L - 1`.

### 2.3 O(1) multiway partitioning via `divmod`

Rather than searching for a balanced split, the engine computes it
directly in constant time using integer division:

```python
base, remainder = divmod(N, k)
sizes = [base + 1] * remainder + [base] * (k - remainder)
```

This produces exactly `remainder` sections of size `base + 1` and
`k - remainder` sections of size `base`. This is **provably the
minimum-spread partition of N into k integer parts** — no other way of
splitting `N` students into `k` groups has a smaller gap between its
largest and smallest group. Students are then assigned into these
pre-sized sections in enrollment order (largest section filled first),
making the whole operation a single linear pass with no backtracking,
search, or iteration needed.

**Worked examples from the test suite:**

| Course | N | C | k = ceil(N/C) | L = ceil(N/k) | divmod(N, k) | Resulting sizes |
|--------|---|---|----------------|----------------|---------------|------------------|
| ITS101 | 14 | 13 | 2 | 7 | base=7, rem=0 | [7, 7] |
| CS101 | 82 | 30 | 3 | 28 | base=27, rem=1 | [28, 27, 27] |
| ENG200 | 43 | 20 | 3 | 15 | base=14, rem=1 | [15, 14, 14] |
| BUS400 | 18 | 25 | 1 | 18 | base=18, rem=0 | [18] |

---

## 3. Achievable-Optimum Validation Philosophy

### 3.1 The formula, taken literally, rejects correct answers

The specified soft-balance metric is:

```
Penalty = Σ (ActualSize_i - L_target)^2    where L_target = ceil(N / k)
```

Taken completely literally, this formula produces a **non-zero penalty
even for the best possible split** whenever `N` doesn't divide evenly by
`k`. Take `CS101` (N=82, k=3): the correct, optimal partition is
`[28, 27, 27]`. Scored against the single scalar `L_target = 28`:

```
(28 - 28)^2 + (27 - 28)^2 + (27 - 28)^2 = 0 + 1 + 1 = 2
```

A validator that flags **any** non-zero result as a failure would reject
this schedule — even though no better partition of 82 students into 3
sections exists. Roughly half of all `(N, k)` pairs have a remainder,
so a literal `penalty > 0` threshold would produce **false positives on
a huge fraction of legitimately optimal schedules.**

### 3.2 The fix: compare against the achievable minimum, not zero

`validate_balancing.py` computes the formula exactly as specified, but
also computes a second number: the penalty of the **divmod-optimal
partition** for that same `(N, k)` — the best any solver could possibly
achieve. A course only fails the soft check if its **actual** penalty is
*strictly worse* than that achievable minimum:

```
FAIL only if:  Penalty_actual  >  Penalty_min_achievable
```

This single change turns the metric from "is this perfectly uniform"
(impossible whenever there's a remainder) into "is this as good as it
could possibly be" (achievable, and the only meaningful question to
ask). Both numbers are always printed in the report — nothing is hidden
behind the pass/fail verdict.

### 3.3 Proof this still catches real imbalance

The design is only legitimate if it still fails genuinely bad schedules.
Self-test: `ITS101` (N=14, k=2, achievable minimum = 0 with `[7, 7]`)
deliberately rebalanced into a lopsided `[13, 1]` split:

```
Penalty_actual = (13-7)^2 + (1-7)^2 = 36 + 36 = 72
Penalty_min_achievable = 0
72 > 0  ->  FAIL
```

The validator correctly rejected this, confirming the achievable-minimum
comparison does not simply rubber-stamp every input — it still catches
real, fixable imbalance while giving a pass to the legitimately optimal
remainder-based splits.

---

## 4. Diagnostic & Self-Healing Engine

Every validation run produces one of two outcomes per course: an `[OK]`
line with the achieved vs. achievable penalty, or a structured failure
report consisting of exactly two parts:

1. **Problem Statement** — the concrete, numeric nature of the failure.
2. **Recommended System Solution** — an actionable instruction describing
   exactly what to change to resolve it.

### 4.1 Hard-rule diagnostics

| Violation | Problem statement example | Recommended fix example |
|---|---|---|
| Capacity exceeded | `Section ITS101-SEC1 has 15 students, exceeding room max_capacity 13.` | `Split Section ITS101-SEC1 — move 2 student(s) out...` |
| Student assigned twice | `Student 'CS101-S014' is assigned to both CS101-SEC1 and CS101-SEC2.` | `Remove 1 duplicate assignment(s)...` |
| Student unplaced | `3 enrolled student(s) are not assigned to any section.` | `Assign the 3 unplaced student(s)...` |
| Wrong section count | `3 section(s) were produced, but 82 students against capacity 30 structurally requires 3.` *(mismatch case)* | `Increase section count k from 2 to 3 for course CS101 as demand (82) exceeds capacity (30).` |

### 4.2 Soft-rule diagnostics (the self-healing recommendation)

When a course's actual penalty exceeds the achievable minimum, the
engine reports the exact imbalance and a concrete transfer plan, using a
greedy largest-excess-to-largest-deficit matching between the actual
section sizes and the ideal (divmod) sizes:

```
[FAIL] Course ITS101: Section ITS101-SEC1 has 13 students, Section
       ITS101-SEC2 has 1 student(s). Load imbalance delta = 12.
  (variance penalty = 72, best achievable for N=14/k=2 is 0 with sizes [7, 7])
[RECOMMENDED FIX] Move 6 student(s) from ITS101-SEC1 to ITS101-SEC2
                  to approach the target distribution [7, 7].
```

This was confirmed live: the self-test described in Section 3.3
produced this exact output, including the precisely-computed transfer
count (6 students) needed to restore the 7/7 balance — not just a
generic "rebalance needed" message.

---

## 5. Test Case Audit Results

| Course | Enrolled (N) | Room Capacity (C) | Required k | Sections Produced | Sizes | Target Sizes | Variance Penalty | Achievable Min | Result |
|--------|--------------|--------------------|-------------|---------------------|-------|----------------|--------------------|------------------|--------|
| ITS101 | 14 | 13 | 2 | 2 | [7, 7] | [7, 7] | 0 | 0 | **PASS** |
| CS101 | 82 | 30 | 3 | 3 | [28, 27, 27] | [28, 27, 27] | 2 | 2 | **PASS** |
| ENG200 | 43 | 20 | 3 | 3 | [15, 14, 14] | [15, 14, 14] | 2 | 2 | **PASS** |
| BUS400 | 18 | 25 | 1 | 1 | [18] | [18] | 0 | 0 | **PASS** |

**Overall result: 4 / 4 courses PASS (100%).** Every course hit its
mathematically optimal partition exactly — zero capacity violations,
zero student-assignment errors, zero section-count mismatches, and zero
*avoidable* soft-balance penalty across all 157 students and 9 generated
sections.

The engine's diagnostic layer was additionally proven to correctly
**reject** a deliberately broken schedule (Section 3.3), confirming the
100% pass rate reflects genuine correctness rather than a validator that
simply never fails.
