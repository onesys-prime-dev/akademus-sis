<?php

namespace App\Models;

use CodeIgniter\Model;

class DepartmentModel extends Model
{
    protected $table      = 'departments';
    protected $primaryKey = 'dept_id';

    protected $allowedFields = ['code', 'name', 'location', 'established_year'];

    protected $useTimestamps = true;

    /**
     * A few programs were seeded (plotted_schedules/faculty_assignments/etc.)
     * with display names that predate the real `programs` table and don't
     * match `programs.program_name` verbatim. New programs need no entry
     * here — they resolve straight off program_name.
     */
    private const LEGACY_PROGRAM_NAMES = [
        'BSIT' => 'BS in Information Technology',
        'BSCS' => 'BS in Computer Science',
    ];

    /**
     * Sub-department (the chairperson-headed "Department" in the UI) that a
     * user belongs to, or null when they have none.
     */
    public function subDeptOfUser(int $userId): ?array
    {
        return db_connect()->table('users u')
            ->select('sd.sub_dept_id, sd.code, sd.name')
            ->join('sub_departments sd', 'sd.sub_dept_id = u.sub_department_id')
            ->where('u.id', $userId)
            ->get()
            ->getRowArray();
    }

    /**
     * Department code of a user, or null when they have none.
     */
    public function codeOfUser(int $userId): ?string
    {
        return $this->subDeptOfUser($userId)['code'] ?? null;
    }

    /**
     * Active programs belonging to a sub-department, as the display-name
     * values used throughout scheduling (plotted_schedules, faculty_assignments,
     * curriculum_presets, ...).
     */
    public function programsOfSubDept(int $subDeptId): array
    {
        $rows = db_connect()->table('programs')
            ->where('sub_department_id', $subDeptId)
            ->where('is_active', 1)
            ->orderBy('program_name')
            ->get()
            ->getResultArray();

        return array_map(
            static fn (array $r) => self::LEGACY_PROGRAM_NAMES[$r['program_code']] ?? $r['program_name'],
            $rows
        );
    }

    /**
     * Sub-department code that owns the program with this display name.
     */
    public static function programDept(string $program): ?string
    {
        $row = self::programRow($program);

        if ($row === null) {
            return null;
        }

        return db_connect()->table('sub_departments')
            ->where('sub_dept_id', $row['sub_department_id'])
            ->get()
            ->getRowArray()['code'] ?? null;
    }

    /**
     * programs.program_id for the program with this display name, or null.
     */
    public static function programIdOfName(string $program): ?int
    {
        $row = self::programRow($program);

        return $row === null ? null : (int) $row['program_id'];
    }

    /**
     * The display-name value (matching programsOfSubDept()) for a program_id.
     */
    public static function programNameById(int $programId): ?string
    {
        $row = db_connect()->table('programs')->where('program_id', $programId)->get()->getRowArray();

        if ($row === null) {
            return null;
        }

        return self::LEGACY_PROGRAM_NAMES[$row['program_code']] ?? $row['program_name'];
    }

    private static function programRow(string $program): ?array
    {
        $code = array_search($program, self::LEGACY_PROGRAM_NAMES, true);

        $builder = db_connect()->table('programs');

        $row = $code !== false
            ? $builder->where('program_code', $code)->get()->getRowArray()
            : $builder->where('program_name', $program)->get()->getRowArray();

        return $row;
    }
}
