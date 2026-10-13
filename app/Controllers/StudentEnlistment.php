<?php

namespace App\Controllers;

use App\Models\AcademicYearModel;
use App\Models\DepartmentModel;
use App\Models\EnrollmentModel;
use App\Models\StudentEnlistmentModel;
use App\Models\StudentModel;

/**
 * The student-facing cart wizard: info confirm -> course selection -> review
 * & submit -> status tracker, all one page (app/Views/student/enlistment.php)
 * — the cart header's own `status` is the source of truth for which of
 * those sections is currently relevant, so there's no separate "current
 * step" column to keep in sync. Info-update reuses the existing
 * StudentController::updateProfile() route as-is.
 */
class StudentEnlistment extends BaseController
{
    public function wizard()
    {
        $userId  = (int) auth()->id();
        $student = (new StudentModel())->where('user_id', $userId)->first();

        if ($student === null) {
            return redirect()->to('/student/dashboard')->with('errors', ['No student record is linked to your account.']);
        }

        $ayModel      = new AcademicYearModel();
        $activeTerm   = $ayModel->activeFormatted();
        $windowOpen   = $ayModel->isEnlistmentOpen();

        if ($activeTerm === null) {
            return view('student/enlistment', [
                'pageTitle'   => 'Enlistment',
                'windowOpen'  => false,
                'activeTerm'  => null,
                'enlistment'  => null,
                'items'       => [],
                'options'     => [],
                'cap'         => (int) (setting('Academic.maxStudentUnits') ?? 24),
                'unitsUsed'   => 0,
            ]);
        }

        $model      = new StudentEnlistmentModel();
        $enlistment = $model->openOrCreate((int) $student['student_id'], $activeTerm);
        $items      = $model->itemsFor((int) $enlistment['id']);
        $unitsUsed  = array_sum(array_column($items, 'units'));

        $programName = $student['program_id'] !== null ? DepartmentModel::programNameById((int) $student['program_id']) : null;
        $options     = $programName !== null ? (new EnrollmentModel())->classOptions($programName, $activeTerm) : [];

        return view('student/enlistment', [
            'pageTitle'  => 'Enlistment',
            'windowOpen' => $windowOpen,
            'activeTerm' => $activeTerm,
            'enlistment' => $enlistment,
            'items'      => $items,
            'options'    => $options,
            'cap'        => (int) (setting('Academic.maxStudentUnits') ?? 24),
            'unitsUsed'  => $unitsUsed,
        ]);
    }

    public function addItem()
    {
        $userId  = (int) auth()->id();
        $student = (new StudentModel())->where('user_id', $userId)->first();

        if ($student === null) {
            return redirect()->to('/student/enlistment')->with('errors', ['No student record is linked to your account.']);
        }

        $enlistmentId = (int) $this->request->getPost('enlistment_id');
        $sectionId    = (int) $this->request->getPost('section_id');
        $subjectCode  = (string) $this->request->getPost('subject_code');

        $enlistment = (new StudentEnlistmentModel())->find($enlistmentId);

        if ($enlistment === null || (int) $enlistment['student_id'] !== (int) $student['student_id']) {
            return redirect()->to('/student/enlistment')->with('errors', ['Cart not found.']);
        }

        $result = (new StudentEnlistmentModel())->addItem($enlistmentId, $userId, $sectionId, $subjectCode);

        if (! $result['ok']) {
            return redirect()->to('/student/enlistment')->with('errors', [$result['error']]);
        }

        return redirect()->to('/student/enlistment')->with('message', "{$subjectCode} added to your cart.");
    }

    public function removeItem()
    {
        $userId  = (int) auth()->id();
        $student = (new StudentModel())->where('user_id', $userId)->first();

        if ($student === null) {
            return redirect()->to('/student/enlistment')->with('errors', ['No student record is linked to your account.']);
        }

        $enlistmentId = (int) $this->request->getPost('enlistment_id');
        $itemId       = (int) $this->request->getPost('item_id');

        $enlistment = (new StudentEnlistmentModel())->find($enlistmentId);

        if ($enlistment === null || (int) $enlistment['student_id'] !== (int) $student['student_id']) {
            return redirect()->to('/student/enlistment')->with('errors', ['Cart not found.']);
        }

        $result = (new StudentEnlistmentModel())->removeItem($enlistmentId, $itemId);

        if (! $result['ok']) {
            return redirect()->to('/student/enlistment')->with('errors', [$result['error']]);
        }

        return redirect()->to('/student/enlistment')->with('message', 'Removed from your cart.');
    }

    public function submit()
    {
        $userId  = (int) auth()->id();
        $student = (new StudentModel())->where('user_id', $userId)->first();

        if ($student === null) {
            return redirect()->to('/student/enlistment')->with('errors', ['No student record is linked to your account.']);
        }

        $enlistmentId = (int) $this->request->getPost('enlistment_id');
        $result       = (new StudentEnlistmentModel())->submit($enlistmentId, (int) $student['student_id']);

        if (! $result['ok']) {
            return redirect()->to('/student/enlistment')->with('errors', [$result['error']]);
        }

        return redirect()->to('/student/enlistment')->with('message', 'Your enlistment cart was submitted for review.');
    }
}
