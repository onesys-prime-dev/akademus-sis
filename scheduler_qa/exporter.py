#!/usr/bin/env python3
"""
Exports a solved schedule (schedule.json, validated against dataset.json)
into two SIS-ready artifacts:

    1. master_schedule.csv — flat, chronologically-sorted CSV for DB ingestion.
    2. timetable.html      — a styled weekly grid (rooms x timeslots),
                              color-coded by department.

USAGE
    python exporter.py dataset.json schedule.json

OUTPUT (written next to the input files)
    master_schedule.csv
    timetable.html

Grouping choice: the grid is organized by ROOM (columns) rather than by
professor. With only 10 rooms vs. 15 professors, a room-column grid fits
a normal screen width without horizontal scrolling, and "which room is
free/busy when" is the more common use case for a department's room
calendar than a deep per-professor roster.
"""
from __future__ import annotations

import csv
import json
import sys

DAY_ORDER = ["Mon", "Tue", "Wed", "Thu", "Fri"]
HOUR_ORDER = [f"{h:02d}:00" for h in range(8, 17)]  # 08:00 .. 16:00 (last 1h block ends 17:00)

DEPARTMENT_COLORS = {
    "Computer Science": "#4C6EF5",  # blue
    "Mathematics": "#F59F00",       # amber
    "Physics": "#12B886",           # teal
    "Engineering": "#E64980",       # pink
    "Business": "#7048E8",          # violet
}
DEFAULT_COLOR = "#868E96"  # grey fallback for any unmapped department


def load_json(path: str) -> dict:
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def end_time_of(start_time: str) -> str:
    hour = int(start_time.split(":")[0])
    return f"{hour + 1:02d}:00"


def day_sort_key(day: str) -> int:
    return DAY_ORDER.index(day) if day in DAY_ORDER else len(DAY_ORDER)


def build_rows(dataset: dict, schedule: list[dict]) -> list[dict]:
    courses_by_id = {c["id"]: c for c in dataset["courses"]}
    professors_by_id = {p["id"]: p for p in dataset["professors"]}
    rooms_by_id = {r["id"]: r for r in dataset["rooms"]}

    rows = []
    for entry in schedule:
        course = courses_by_id[entry["course_id"]]
        prof = professors_by_id[course["assigned_professor"]]
        room = rooms_by_id[entry["room_id"]]

        rows.append({
            "course_id": course["id"],
            "course_name": course["name"],
            "department": course["department"],
            "professor_name": prof["name"],
            "day": entry["day"],
            "start_time": entry["start_time"],
            "end_time": end_time_of(entry["start_time"]),
            "room_id": room["id"],
            "building": room["building"],
        })

    rows.sort(key=lambda r: (day_sort_key(r["day"]), r["start_time"], r["room_id"]))
    return rows


def write_csv(rows: list[dict], out_path: str) -> None:
    fieldnames = [
        ("course_id", "Course ID"),
        ("course_name", "Course Name"),
        ("department", "Department"),
        ("professor_name", "Professor Name"),
        ("day", "Day"),
        ("start_time", "Start Time"),
        ("end_time", "End Time"),
        ("room_id", "Room ID"),
        ("building", "Building"),
    ]
    with open(out_path, "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow([label for _, label in fieldnames])
        for row in rows:
            writer.writerow([row[key] for key, _ in fieldnames])


def write_html(dataset: dict, rows: list[dict], out_path: str) -> None:
    rooms = sorted(dataset["rooms"], key=lambda r: (r["building"], r["id"]))
    room_ids = [r["id"] for r in rooms]

    # cell_lookup[(day, hour)][room_id] -> row dict (the session there, if any)
    cell_lookup: dict[tuple[str, str], dict[str, dict]] = {}
    for row in rows:
        key = (row["day"], row["start_time"])
        cell_lookup.setdefault(key, {})[row["room_id"]] = row

    legend_items = "".join(
        f'<span class="legend-item"><span class="swatch" style="background:{color}"></span>{dept}</span>'
        for dept, color in DEPARTMENT_COLORS.items()
    )

    header_cells = "".join(
        f'<th>{r["id"]}<br><span class="room-sub">{r["name"]} &middot; {r["building"]} &middot; cap {r["capacity"]}</span></th>'
        for r in rooms
    )

    body_rows = []
    for day in DAY_ORDER:
        for hour in HOUR_ORDER:
            cells = cell_lookup.get((day, hour), {})
            row_cells = []
            for room_id in room_ids:
                session = cells.get(room_id)
                if session:
                    color = DEPARTMENT_COLORS.get(session["department"], DEFAULT_COLOR)
                    row_cells.append(
                        f'<td class="busy" style="background:{color}20;border-left:4px solid {color}">'
                        f'<div class="course">{session["course_id"]}</div>'
                        f'<div class="prof">{session["professor_name"]}</div>'
                        f'</td>'
                    )
                else:
                    row_cells.append('<td class="free"></td>')
            body_rows.append(
                f'<tr><th class="time-col">{day}<br>{hour}</th>{"".join(row_cells)}</tr>'
            )

    html = f"""<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Weekly Timetable</title>
<style>
  body {{
    font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    background: #f4f5f7;
    color: #1b1f24;
    margin: 24px;
  }}
  h1 {{ font-size: 20px; margin-bottom: 4px; }}
  .subtitle {{ color: #5c6370; margin-bottom: 16px; font-size: 13px; }}
  .legend {{ margin-bottom: 16px; display: flex; flex-wrap: wrap; gap: 14px; }}
  .legend-item {{ font-size: 13px; display: flex; align-items: center; gap: 6px; }}
  .swatch {{ width: 12px; height: 12px; border-radius: 2px; display: inline-block; }}
  table {{
    border-collapse: collapse;
    width: 100%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    font-size: 12px;
  }}
  th, td {{
    border: 1px solid #e4e6eb;
    padding: 4px 6px;
    text-align: left;
    vertical-align: top;
  }}
  thead th {{
    background: #1b1f24;
    color: #fff;
    position: sticky;
    top: 0;
    text-align: center;
    font-size: 11px;
  }}
  .room-sub {{ font-size: 9px; color: #c7cad1; font-weight: normal; }}
  .time-col {{
    background: #f0f1f4;
    white-space: nowrap;
    font-size: 11px;
    text-align: center;
  }}
  td.free {{ background: #fafbfc; }}
  td.busy {{ min-width: 90px; }}
  .course {{ font-weight: 700; font-size: 12px; }}
  .prof {{ font-size: 10px; color: #4b5160; }}
  tbody tr:nth-child(9n) td, tbody tr:nth-child(9n) th.time-col {{
    border-bottom: 2px solid #c7cad1;
  }}
</style>
</head>
<body>
  <h1>Weekly Timetable &mdash; by Room</h1>
  <div class="subtitle">Generated from dataset.json + schedule.json. Columns = rooms, rows = Mon&ndash;Fri in 1-hour blocks (08:00&ndash;17:00).</div>
  <div class="legend">{legend_items}</div>
  <table>
    <thead>
      <tr><th class="time-col">Day / Time</th>{header_cells}</tr>
    </thead>
    <tbody>
      {"".join(body_rows)}
    </tbody>
  </table>
</body>
</html>
"""
    with open(out_path, "w", encoding="utf-8") as f:
        f.write(html)


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: python exporter.py dataset.json schedule.json", file=sys.stderr)
        return 1

    dataset = load_json(sys.argv[1])
    schedule = load_json(sys.argv[2])

    rows = build_rows(dataset, schedule)
    write_csv(rows, "master_schedule.csv")
    write_html(dataset, rows, "timetable.html")

    print(f"Wrote master_schedule.csv ({len(rows)} rows) and timetable.html.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
