<?php

namespace App\Models;

use CodeIgniter\Model;

class DepartmentModel extends Model
{
    protected $table      = 'departments';
    protected $primaryKey = 'dept_id';

    protected $allowedFields = ['code', 'name'];

    protected $useTimestamps = true;

    /**
     * Which department owns each program. Moves into the programs table once
     * programs stop being hardcoded filter strings.
     */
    public const PROGRAM_MAP = [
        'BS in Information Technology' => 'IT',
        'BS in Computer Science'       => 'CS',
    ];

    public static function programDept(string $program): ?string
    {
        return self::PROGRAM_MAP[$program] ?? null;
    }

    /**
     * Department code of a user, or null when they have none.
     */
    public function codeOfUser(int $userId): ?string
    {
        $row = db_connect()->table('users u')
            ->select('d.code')
            ->join('departments d', 'd.dept_id = u.department_id')
            ->where('u.id', $userId)
            ->get()
            ->getRowArray();

        return $row['code'] ?? null;
    }
}
