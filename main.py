#!/usr/bin/env python3
"""
Master CLI Orchestrator for the scheduling/sectioning QA microservices.

Sequentially (or selectively) runs each standalone QA pipeline — each one
already independently validated and documented in its own directory — as
an isolated subprocess, aggregates their pass/fail status and key
metrics, and renders a single consolidated dashboard.

MODULES
    scheduler       scheduler_qa/        CSP + MRV Timetable Engine
    balancing       section_balancing_qa/ Multiway Section Load Balancer
    integrated      integrated_pipeline_qa/  2-Stage Sectioning -> Scheduling Pipeline
    student_audit   student_audit_qa/    Student Conflict Detection & Auto-Resolver

Each module's steps run with `cwd` set to that module's own directory, so
every relative path a module's scripts reference (dataset.json,
schedule.json, etc.) resolves inside its own sandbox — zero cross-module
file contention, zero shared process state. This script never imports
any module's code directly; every module is invoked exactly as a human
operator would from its own README, via `python <script> <args>`.

USAGE
    python main.py                                  # run all 4 modules
    python main.py --module student_audit            # run just one
    python main.py --verbose                         # stream full output
    python main.py --report                          # also write master_audit_report.json
"""
from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parent

# Windows consoles often default to a legacy codepage (cp1252) that can't
# encode the box-drawing characters used by the dashboard table. Force
# UTF-8 output where the runtime supports it; fall back silently (ASCII
# box-drawing substitution) if it doesn't.
try:
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")
    _UNICODE_BOX = True
except (AttributeError, OSError):
    _UNICODE_BOX = False

USE_COLOR = sys.stdout.isatty()


def c(code: str, text: str) -> str:
    if not USE_COLOR:
        return text
    return f"\033[{code}m{text}\033[0m"


def green(text: str) -> str:
    return c("32;1", text)


def red(text: str) -> str:
    return c("31;1", text)


def dim(text: str) -> str:
    return c("2", text)


def bold(text: str) -> str:
    return c("1", text)


# ============================================================ MODULE REGISTRY

MODULES: dict[str, dict] = {
    "scheduler": {
        "display": "Scheduler QA (CSP + MRV)",
        "dir": "scheduler_qa",
        "steps": [
            ("solve", ["solver.py", "dataset.json", "schedule.json"]),
            ("validate", ["validate_schedule.py", "dataset.json", "schedule.json"]),
        ],
        "metric_steps": ["validate"],
        "soft_kind": "score100",
    },
    "balancing": {
        "display": "Section Load Balancing QA",
        "dir": "section_balancing_qa",
        "steps": [
            ("solve", ["balancing_solver.py", "enrollment_dataset.json", "balanced_sections.json"]),
            ("validate", ["validate_balancing.py", "enrollment_dataset.json", "balanced_sections.json"]),
        ],
        "metric_steps": ["validate"],
        "soft_kind": "achievable_delta",
    },
    "integrated": {
        "display": "Integrated 2-Stage Pipeline QA",
        "dir": "integrated_pipeline_qa",
        "steps": [
            ("solve", ["run_pipeline.py", "pipeline_dataset.json", "integrated_schedule.json"]),
            ("validate", ["validate_pipeline.py", "pipeline_dataset.json", "integrated_schedule.json"]),
        ],
        "metric_steps": ["validate"],
        "soft_kind": "achievable_delta",
    },
    "student_audit": {
        "display": "Student Conflict Auto-Resolver QA",
        "dir": "student_audit_qa",
        "steps": [
            ("resolve", ["resolve_student_conflicts.py", "student_manifest.json", "resolved_student_manifest.json"]),
            ("audit_schedule", ["audit_student_schedules.py", "resolved_student_manifest.json"]),
            ("audit_capacity", ["audit_section_capacity.py", "resolved_student_manifest.json"]),
        ],
        "metric_steps": ["audit_schedule", "audit_capacity"],
        "soft_kind": "none",
    },
}

MODULE_ORDER = ["scheduler", "balancing", "integrated", "student_audit"]


# ============================================================ EXECUTION


def run_step(module_dir: Path, args: list[str], verbose: bool) -> dict:
    cmd = [sys.executable, *args]
    try:
        result = subprocess.run(
            cmd, cwd=module_dir, capture_output=True, text=True, timeout=180,
        )
        if verbose:
            if result.stdout:
                print(result.stdout, end="" if result.stdout.endswith("\n") else "\n")
            if result.stderr:
                print(dim(result.stderr), end="" if result.stderr.endswith("\n") else "\n")
        return {
            "command": " ".join(args),
            "returncode": result.returncode,
            "stdout": result.stdout,
            "stderr": result.stderr,
            "ok": result.returncode == 0,
            "error": None,
        }
    except (OSError, subprocess.SubprocessError) as e:
        if verbose:
            print(red(f"  !! Failed to execute: {' '.join(args)} ({e})"))
        return {
            "command": " ".join(args),
            "returncode": None,
            "stdout": "",
            "stderr": str(e),
            "ok": False,
            "error": str(e),
        }


def run_module(key: str, verbose: bool) -> dict:
    config = MODULES[key]
    module_dir = ROOT / config["dir"]
    display = config["display"]

    if verbose:
        print(bold(f"\n=== {display} ({config['dir']}/) ==="))

    start = time.perf_counter()
    step_results: dict[str, dict] = {}
    module_ok = True

    for step_name, args in config["steps"]:
        if verbose:
            print(dim(f"-- step: {step_name} ({' '.join(args)}) --"))
        result = run_step(module_dir, args, verbose)
        step_results[step_name] = result
        if not result["ok"]:
            module_ok = False
            break  # downstream steps depend on this one's output; no point continuing

    elapsed = time.perf_counter() - start

    metric_text = "\n".join(
        step_results[s]["stdout"] for s in config["metric_steps"] if s in step_results
    )
    hard_pass_rate = extract_hard_pass_rate(metric_text, module_ok)
    soft_delta, soft_label = extract_soft_metric(config["soft_kind"], metric_text)
    recommendations = extract_recommendations(step_results)

    return {
        "module": key,
        "display": display,
        "ok": module_ok,
        "elapsed_seconds": round(elapsed, 3),
        "hard_pass_rate": hard_pass_rate,
        "soft_delta": soft_delta,
        "soft_label": soft_label,
        "recommendations": recommendations,
        "steps": step_results,
    }


# ============================================================ METRIC EXTRACTION


def extract_hard_pass_rate(text: str, module_ok: bool) -> float:
    ok_markers = len(re.findall(r"\[OK\]", text))
    fail_markers = len(re.findall(r"\[FAIL", text))
    if ok_markers + fail_markers > 0:
        return round(100.0 * ok_markers / (ok_markers + fail_markers), 1)
    return 100.0 if module_ok else 0.0


def extract_soft_metric(kind: str, text: str) -> tuple[float | None, str]:
    if kind == "score100":
        m = re.search(r"SOFT SCORE:\s*([\d.]+)\s*/\s*100", text)
        if m:
            score = float(m.group(1))
            return round(100.0 - score, 2), f"score {score}/100"
        return None, "n/a"

    if kind == "achievable_delta":
        # Two validators phrase this differently:
        #   section_balancing_qa: "variance penalty 2 (achievable minimum 2)"
        #   integrated_pipeline_qa: "(penalty 1, min 1)"
        pairs = re.findall(r"penalty\s+([\d.]+)\s*\(achievable minimum\s*([\d.]+)\)", text)
        pairs += re.findall(r"\(penalty\s+([\d.]+),\s*min\s+([\d.]+)\)", text)
        if not pairs:
            return None, "n/a"
        delta = sum(float(a) - float(b) for a, b in pairs)
        return round(delta, 2), f"{len(pairs)} course(s)/section(s) checked"

    return None, "n/a"  # 'none' kind — this module has no soft-quality concept


def extract_recommendations(step_results: dict[str, dict]) -> list[str]:
    recs = []
    for result in step_results.values():
        for line in result["stdout"].splitlines():
            if line.startswith("[RECOMMENDED FIX]") or line.startswith("[FAIL"):
                recs.append(line.strip())
    return recs[:10]


# ============================================================ DASHBOARD


def render_dashboard(results: list[dict]) -> None:
    headers = ["Module", "Time", "Hard Pass", "Soft Delta", "Status"]
    rows = []
    for r in results:
        status = green("[PASS]") if r["ok"] else red("[FAIL]")
        soft = f"{r['soft_delta']}" if r["soft_delta"] is not None else "n/a"
        rows.append([
            r["display"],
            f"{r['elapsed_seconds']}s",
            f"{r['hard_pass_rate']}%",
            soft,
            status,
        ])

    widths = [max(len(headers[i]), *(len(strip_ansi(row[i])) for row in rows)) for i in range(len(headers))] if rows else [len(h) for h in headers]

    v, h, tl, tr, bl, br, cross = ("│", "─", "┌", "┐", "└", "┘", "┼") if _UNICODE_BOX else ("|", "-", "+", "+", "+", "+", "+")
    t_down, t_up = ("┬", "┴") if _UNICODE_BOX else ("+", "+")

    def fmt_row(cells: list[str]) -> str:
        padded = []
        for i, cell in enumerate(cells):
            pad = widths[i] - len(strip_ansi(cell))
            padded.append(cell + " " * pad)
        return f"{v} " + f" {v} ".join(padded) + f" {v}"

    def border(left: str, right: str, joiner: str) -> str:
        return left + joiner.join(h * (w + 2) for w in widths) + right

    print()
    print(bold("MASTER QA DASHBOARD"))
    print(border(tl, tr, t_down))
    print(fmt_row(headers))
    print(border("├" if _UNICODE_BOX else "+", "┤" if _UNICODE_BOX else "+", cross))
    for row in rows:
        print(fmt_row(row))
    print(border(bl, br, t_up))

    overall_ok = all(r["ok"] for r in results)
    print()
    print(bold("OVERALL: ") + (green("PASS") if overall_ok else red("FAIL")))

    failed = [r for r in results if not r["ok"]]
    if failed:
        print()
        print(bold("Diagnostics for failed module(s):"))
        for r in failed:
            print(f"  {red('[' + r['display'] + ']')}")
            for rec in r["recommendations"]:
                print(f"    {rec}")
            if not r["recommendations"]:
                print("    (no structured diagnostic lines captured — re-run with --verbose)")


def strip_ansi(text: str) -> str:
    return re.sub(r"\033\[[0-9;]*m", "", text)


# ============================================================ MAIN


def main() -> int:
    parser = argparse.ArgumentParser(description="Master orchestrator for the scheduling/sectioning QA microservices.")
    parser.add_argument("--module", choices=MODULE_ORDER, help="Run only this module instead of all four.")
    parser.add_argument("--verbose", action="store_true", help="Stream full stdout/stderr from each step.")
    parser.add_argument("--report", action="store_true", help="Write an aggregated JSON summary to master_audit_report.json.")
    args = parser.parse_args()

    modules_to_run = [args.module] if args.module else MODULE_ORDER

    print(bold(f"Running {len(modules_to_run)} module(s): {', '.join(modules_to_run)}"))

    results = []
    for key in modules_to_run:
        config = MODULES[key]
        module_dir = ROOT / config["dir"]
        if not module_dir.is_dir():
            print(red(f"!! Module directory not found: {module_dir}"))
            results.append({
                "module": key, "display": config["display"], "ok": False,
                "elapsed_seconds": 0.0, "hard_pass_rate": 0.0, "soft_delta": None,
                "soft_label": "n/a", "recommendations": [f"Module directory missing: {module_dir}"],
                "steps": {},
            })
            continue
        results.append(run_module(key, args.verbose))

    render_dashboard(results)

    if args.report:
        report_path = ROOT / "master_audit_report.json"
        serializable = []
        for r in results:
            entry = dict(r)
            entry["steps"] = {
                name: {k: v for k, v in step.items() if k != "stdout" or len(v) < 20000}
                for name, step in r["steps"].items()
            }
            serializable.append(entry)
        with open(report_path, "w", encoding="utf-8") as f:
            json.dump({"modules": serializable, "overall_pass": all(r["ok"] for r in results)}, f, indent=2)
        print(f"\nWrote aggregated report to {report_path}")

    return 0 if all(r["ok"] for r in results) else 1


if __name__ == "__main__":
    sys.exit(main())
