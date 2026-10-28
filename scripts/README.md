# scripts/run-tests.sh

Runs PHPUnit and tells you only what you actually need to know: did this run
introduce a failure that wasn't already there.

## Usage

```bash
scripts/run-tests.sh                                          # full suite
scripts/run-tests.sh tests/database/SomeFileTest.php           # one file
scripts/run-tests.sh tests/database/SomeFileTest.php --filter testName
```

Exit code 0 = no new failures. Exit code 1 = something not in the baseline
failed — check the output.

Full PHPUnit output always goes to `build/logs/phpunit-<timestamp>.log`
(gitignored). The script's own stdout is just the summary: PHPUnit's own
tally line, how many baseline failures showed up (expected, doesn't fail the
run), and any new failures by name.

## `known-failing-tests.txt`

One `ClassName::methodName` per line. Tests listed here are treated as
"expected to fail" — they don't make the script exit non-zero. Everything
else that fails is reported as new.

**Only add a test after confirming it fails in isolation** —
`scripts/run-tests.sh tests/path/To/TheFile.php` on just that file, not
because it showed up failing in a big combined run. See the next section for
why that distinction matters here specifically.

## Known limitation: the full suite is not currently reliable as one run

Confirmed 2026-10-28: running the complete ~463-test suite in a single
PHPUnit process produces a large batch of failures — including entire files
(`OfferingScheduleTest`, `OfferingPlacementRequestTest`,
`CourseOfferingMergeScheduleTest`) where *every* test in the file fails —
that then pass 100% clean seconds later when re-run alone or in a small
group. This points at shared-state contention (most likely the shared
`akademus_sis_test` MySQL database also being hit by other concurrent
sessions/processes on this machine — the same pattern seen repeatedly
during this project's merge-scheduling work), not real code regressions.

Separately, there's a real, reproducible, pre-existing bug
(`EnrollmentModel::meetingsFor()` — `$termAcademicYear` null) that
genuinely breaks a wide set of tests on its own, confirmed via isolated
re-runs and a diff against a known-clean commit. That one belongs in the
baseline; the full-run-only noise does not.

**Practical upshot:** don't trust a single full-suite run's failure list at
face value. For day-to-day use, run the specific file(s)/area you're
touching — that's fast, and the baseline comparison is meaningful there.
Treat a full-suite run as a rough smoke test, and verify anything
surprising with an isolated re-run of just that file before concluding it's
real.

`known-failing-tests.txt` is intentionally incomplete right now — it only
has entries individually confirmed in isolation, not everything that showed
up in one noisy full run. Expanding it properly means triaging the rest of
the suite file-by-file, which nobody has done yet.
