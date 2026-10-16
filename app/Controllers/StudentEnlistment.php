<?php

namespace App\Controllers;

use App\Libraries\StudentProgressService;
use App\Models\AcademicYearModel;
use App\Models\DepartmentModel;
use App\Models\EnlistmentOverrideModel;
use App\Models\EnrollmentModel;
use App\Models\GeneratedDocumentModel;
use App\Models\StudentEnlistmentModel;
use App\Models\StudentModel;
use App\Models\SubjectModel;
use App\Models\TuitionAssessmentModel;

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
                'pageTitle'          => 'Enlistment',
                'windowOpen'         => false,
                'activeTerm'         => null,
                'enlistment'         => null,
                'items'              => [],
                'dueSubjects'        => [],
                'eligible'           => true,
                'balance'            => 0.0,
                'cap'                => (int) (setting('Academic.maxStudentUnits') ?? 24),
                'unitsUsed'          => 0,
            ]);
        }

        $assessment = (new TuitionAssessmentModel())->forStudentYear((int) $student['student_id'], $activeTerm);
        $balance    = (float) ($assessment['balance'] ?? 0);
        $eligible   = ! ($balance > 0 && ! (bool) (setting('Finance.allowEnlistmentWithBalance') ?? true));

        $model      = new StudentEnlistmentModel();
        $enlistment = $model->openOrCreate((int) $student['student_id'], $activeTerm);
        $items      = $model->itemsFor((int) $enlistment['id']);
        $unitsUsed  = array_sum(array_column($items, 'units'));

        $programName = $student['program_id'] !== null ? DepartmentModel::programNameById((int) $student['program_id']) : null;
        $dueSubjects = [];

        if ($programName !== null) {
            $enrollments = new EnrollmentModel();
            $overrides   = new EnlistmentOverrideModel();
            $progress    = (new StudentProgressService())->forStudent((int) $student['student_id']);

            foreach ($progress['remaining'] ?? [] as $item) {
                if ($item['status'] === 'in_progress') {
                    continue;
                }

                $overrideGranted = false;

                if ($item['status'] === 'not_started' && ! empty($item['missing_prerequisites'])) {
                    if (! $overrides->hasOverride((int) $student['student_id'], $item['code'], $activeTerm)) {
                        continue;
                    }

                    $overrideGranted = true;
                }

                $catalog  = (new SubjectModel())->where('subject_code', $item['code'])->first();
                $lecUnits = $catalog['lecture_units'] ?? null;
                $labUnits = $catalog['laboratory_units'] ?? null;
                $isLab    = (bool) ($catalog['is_laboratory'] ?? false);

                $item['has_lecture']      = $catalog !== null ? ($lecUnits !== null ? (float) $lecUnits > 0 : ! $isLab) : false;
                $item['has_lab']          = $catalog !== null ? ($labUnits !== null ? (float) $labUnits > 0 : $isLab) : false;
                $item['override_granted'] = $overrideGranted;
                $item['corequisites']     = $enrollments->unmetCorequisites($userId, $item['code'], $activeTerm);
                $item['offerings']        = $enrollments->subjectOfferings($item['code'], $programName, $activeTerm);

                $dueSubjects[] = $item;
            }
        }

        return view('student/enlistment', [
            'pageTitle'   => 'Enlistment',
            'windowOpen'  => $windowOpen,
            'activeTerm'  => $activeTerm,
            'enlistment'  => $enlistment,
            'items'       => $items,
            'dueSubjects' => $dueSubjects,
            'eligible'    => $eligible,
            'balance'     => $balance,
            'cap'         => (int) (setting('Academic.maxStudentUnits') ?? 24),
            'unitsUsed'   => $unitsUsed,
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

    public function sendReminder()
    {
        $userId  = (int) auth()->id();
        $student = (new StudentModel())->where('user_id', $userId)->first();

        if ($student === null) {
            return redirect()->to('/student/enlistment')->with('errors', ['No student record is linked to your account.']);
        }

        $enlistmentId = (int) $this->request->getPost('enlistment_id');
        $result       = (new StudentEnlistmentModel())->sendReminder($enlistmentId, (int) $student['student_id']);

        if (! $result['ok']) {
            return redirect()->to('/student/enlistment')->with('errors', [$result['error']]);
        }

        return redirect()->to('/student/enlistment')->with('message', 'Reminder sent.');
    }

    public function printForm()
    {
        $userId  = (int) auth()->id();
        $student = (new StudentModel())->where('user_id', $userId)->first();

        if ($student === null) {
            return redirect()->to('/student/enlistment')->with('errors', ['No student record is linked to your account.']);
        }

        $documents   = new GeneratedDocumentModel();
        $eligibility = $documents->coeEligibility($userId);

        if (! $eligibility['ok']) {
            return redirect()->to('/student/enlistment')->with('errors', [$eligibility['error']]);
        }

        $result = $documents->generate((int) $student['student_id'], 'student_form', $userId);

        if (! $result['ok']) {
            return redirect()->to('/student/enlistment')->with('errors', [$result['error']]);
        }

        return redirect()->to('/student/documents/view/' . $result['id']);
    }
}
