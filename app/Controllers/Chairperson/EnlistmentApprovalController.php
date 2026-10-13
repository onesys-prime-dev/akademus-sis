<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Libraries\SchedulingAuthorization;
use App\Models\DepartmentModel;
use App\Models\StudentEnlistmentModel;

/**
 * Chairperson's step in the enlistment chain: submitted -> chair_recommended
 * (or returned to draft). Mirrors Chairperson\GradeApprovalController's
 * change-request queue exactly — same program-scoping via
 * SchedulingAuthorization::authorizedSubDeptIds() + DepartmentModel::
 * programsOfSubDept().
 */
class EnlistmentApprovalController extends BaseController
{
    public function index()
    {
        $programs = $this->authorizedPrograms();

        return view('chairperson/enlistment_approvals', [
            'pageTitle' => 'Enlistment Approvals',
            'user'      => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => 'Chairperson'],
            'requests'  => (new StudentEnlistmentModel())->pendingChairRecommendation($programs),
        ]);
    }

    public function recommend()
    {
        return $this->decide(false);
    }

    public function returnToStudent()
    {
        return $this->decide(true);
    }

    private function decide(bool $isReturn)
    {
        $chairId = (int) auth()->id();
        $id      = (int) $this->request->getPost('id');
        $notes   = (string) $this->request->getPost('notes');

        $enlistment = (new StudentEnlistmentModel())->find($id);

        if ($enlistment === null || ! $this->ownsEnlistment($enlistment)) {
            return redirect()->back()->with('errors', ['That cart does not belong to your department.']);
        }

        $model  = new StudentEnlistmentModel();
        $result = $isReturn
            ? $model->returnToStudent($id, 'submitted', $chairId, 'chairperson', $notes)
            : $model->chairRecommend($id, $chairId, 'recommend', $notes ?: null);

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->to('/chairperson/enlistment-approvals')->with('message', $isReturn ? 'Cart returned to student.' : 'Cart recommended.');
    }

    private function ownsEnlistment(array $enlistment): bool
    {
        $student = (new \App\Models\StudentModel())->find((int) $enlistment['student_id']);

        if ($student === null || $student['program_id'] === null) {
            return false;
        }

        $programName = DepartmentModel::programNameById((int) $student['program_id']);

        return in_array($programName, $this->authorizedPrograms(), true);
    }

    private function authorizedPrograms(): array
    {
        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds((int) auth()->id());
        $programs   = [];
        $deptModel  = new DepartmentModel();

        foreach ($subDeptIds as $id) {
            $programs = array_merge($programs, $deptModel->programsOfSubDept($id));
        }

        return array_values(array_unique($programs));
    }
}
