<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\AcademicHealthService;

/**
 * Read-only academic-hierarchy integrity diagnostic (College -> Department
 * -> Program -> section/faculty relationships). See AcademicHealthService
 * for what's actually checked — this controller only renders its findings,
 * it introduces no new write/repair logic of its own.
 */
class AcademicHealthController extends BaseController
{
    public function index()
    {
        $findings = (new AcademicHealthService())->check();

        $bySeverity = ['critical' => 0, 'warning' => 0, 'informational' => 0];

        foreach ($findings as $f) {
            $bySeverity[$f['severity']] = ($bySeverity[$f['severity']] ?? 0) + 1;
        }

        usort($findings, static function (array $a, array $b): int {
            $order = ['critical' => 0, 'warning' => 1, 'informational' => 2];

            return $order[$a['severity']] <=> $order[$b['severity']];
        });

        return view('admin/academic_health/index', [
            'pageTitle'   => 'Academic Health',
            'activeAdmin' => 'academic-health',
            'findings'    => $findings,
            'bySeverity'  => $bySeverity,
        ]);
    }
}
