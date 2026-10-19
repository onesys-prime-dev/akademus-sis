<?php

namespace App\Controllers\Dean;

use App\Controllers\BaseController;
use App\Libraries\DeanPortal;
use App\Libraries\SchedulingAuthorization;
use App\Models\DepartmentModel;
use App\Models\StudentEnlistmentModel;
use App\Models\StudentModel;

/**
 * Dean's step in the enlistment chain: chair_recommended -> dean_noted (or
 * returned to draft). Mirrors Dean\GradeChangeApprovalController, scoped by
 * college via SchedulingAuthorization::authorizedDepartmentIds() +
 * DeanController::programsOfColleges().
 */
class EnlistmentApprovalController extends BaseController
{
    public function index()
    {
        $programs = $this->authorizedPrograms();

        return view('dean/enlistment_approvals', [
            'pageTitle' => 'Enlistment Approvals',
            'requests'  => $programs === [] ? [] : (new StudentEnlistmentModel())->pendingDeanNote($programs),
        ] + DeanPortal::shell('enlistment-approvals'));
    }

    public function note()
    {
        return $this->decide(false);
    }

    public function returnToStudent()
    {
        return $this->decide(true);
    }

    private function decide(bool $isReturn)
    {
        $deanId = (int) auth()->id();
        $id     = (int) $this->request->getPost('id');
        $notes  = (string) $this->request->getPost('notes');

        $enlistment = (new StudentEnlistmentModel())->find($id);

        if ($enlistment === null || ! $this->ownsEnlistment($enlistment)) {
            return redirect()->back()->with('errors', ['That enlistment does not belong to your college.']);
        }

        $model  = new StudentEnlistmentModel();
        $result = $isReturn
            ? $model->returnToStudent($id, 'chair_recommended', $deanId, 'dean', $notes)
            : $model->deanNote($id, $deanId, 'note', $notes ?: null);

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->to('/dean/enlistment-approvals')->with('message', $isReturn ? 'Enlistment returned to student.' : 'Enlistment noted.');
    }

    private function ownsEnlistment(array $enlistment): bool
    {
        $student = (new StudentModel())->find((int) $enlistment['student_id']);

        if ($student === null || $student['program_id'] === null) {
            return false;
        }

        $programName = DepartmentModel::programNameById((int) $student['program_id']);

        return in_array($programName, $this->authorizedPrograms(), true);
    }

    private function authorizedPrograms(): array
    {
        $collegeIds = (new SchedulingAuthorization())->authorizedDepartmentIds((int) auth()->id());

        return DeanController::programsOfColleges($collegeIds);
    }
}
