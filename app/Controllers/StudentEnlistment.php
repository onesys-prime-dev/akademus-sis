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
 * The student-facing enlistment wizard: info confirm -> course selection ->
 * review & submit -> status tracker, all one page (app/Views/student/
 * enlistment.php) — the enlistment header's own `status` is the source of
 * truth for which of those sections is currently relevant, so there's no
 * separate "current step" column to keep in sync. Info-update reuses the
 * existing StudentController::updateProfile() route as-is.
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

        $studentDetail = (new StudentModel())->findDetailed((int) $student['student_id']);

        // Enlistment is pre-registration for the UPCOMING term, done while
        // the active one is still running — not a second shot at the term
        // the student already has live classes in. Everything the cart
        // itself is built against (its academic_year, subject offerings,
        // curriculum-semester comparison, corequisite/override lookups) is
        // keyed off the upcoming term. The one thing that stays keyed off
        // the ACTIVE term is the tuition-balance eligibility check below —
        // whether a balance blocks pre-registration is about the student's
        // CURRENT standing, not an assessment for a term they're not
        // enrolled in yet.
        $ayModel    = new AcademicYearModel();
        $enlistTerm = $ayModel->upcomingFormatted();
        $activeTerm = $ayModel->activeFormatted();
        $windowOpen = $ayModel->isEnlistmentOpen();

        if ($enlistTerm === null) {
            return view('student/enlistment', [
                'pageTitle'          => 'Enlistment',
                'windowOpen'         => false,
                'activeTerm'         => $activeTerm,
                'enlistTerm'         => null,
                'enlistment'         => null,
                'items'              => [],
                'dueSubjects'        => [],
                'eligible'           => true,
                'balance'            => 0.0,
                'cap'                => (int) (setting('Academic.maxStudentUnits') ?? 24),
                'unitsUsed'          => 0,
                'studentDetail'      => $studentDetail,
            ]);
        }

        $assessment = $activeTerm !== null ? (new TuitionAssessmentModel())->forStudentYear((int) $student['student_id'], $activeTerm) : null;
        $balance    = (float) ($assessment['balance'] ?? 0);
        $eligible   = ! ($balance > 0 && ! (bool) (setting('Finance.allowEnlistmentWithBalance') ?? true));

        $model      = new StudentEnlistmentModel();
        $enlistment = $model->openOrCreate((int) $student['student_id'], $enlistTerm);
        $items      = $model->itemsFor((int) $enlistment['id']);
        $unitsUsed  = array_sum(array_column($items, 'units'));

        $programName = $student['program_id'] !== null ? DepartmentModel::programNameById((int) $student['program_id']) : null;
        $dueSubjects = [];

        // Maps academic_years.semester ('1st'/'2nd'/'Summer') to the code
        // curriculum_subjects.semester actually stores ('1'/'2'/'3') — the
        // two tables spell "semester" differently. A subject the curriculum
        // assigns to a different term than the one being enlisted into
        // (e.g. a Summer-only subject while enlisting for 2nd Semester) is
        // filtered out below — it isn't due yet and has no offerings queried
        // against this term anyway, so it'd only ever render as unselectable
        // noise. It'll appear on its own once its actual term comes around.
        $semesterCodes = ['1st' => '1', '2nd' => '2', 'Summer' => '3'];
        $enlistRow     = $ayModel->upcomingTerm();
        $enlistSemCode = $enlistRow !== null ? ($semesterCodes[$enlistRow['semester']] ?? null) : null;

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
                    if (! $overrides->hasOverride((int) $student['student_id'], $item['code'], $enlistTerm)) {
                        continue;
                    }

                    $overrideGranted = true;
                }

                $catalog  = (new SubjectModel())->where('subject_code', $item['code'])->first();
                $lecUnits = $catalog['lecture_units'] ?? null;
                $labUnits = $catalog['laboratory_units'] ?? null;
                $isLab    = (bool) ($catalog['is_laboratory'] ?? false);

                $curriculumSem = null;

                if ($catalog !== null && $student['curriculum_id'] !== null) {
                    $curriculumSem = db_connect()->table('curriculum_subjects')
                        ->select('semester')
                        ->where('curriculum_id', $student['curriculum_id'])
                        ->where('subject_id', $catalog['subject_id'])
                        ->get()
                        ->getRowArray()['semester'] ?? null;
                }

                // Only show what's actually due THIS enlistment term — a
                // subject the curriculum assigns to a different term (e.g.
                // Summer, while enlisting for 2nd Semester) isn't selectable
                // here anyway (no offerings get queried against this term for
                // it), so listing it was pure noise. It'll appear on its own
                // once that term comes around. A subject with no curriculum
                // term assigned at all ($curriculumSem === null) still shows,
                // since there's nothing to say it ISN'T due now.
                if ($curriculumSem !== null && $curriculumSem !== $enlistSemCode) {
                    continue;
                }

                $item['has_lecture']      = $catalog !== null ? ($lecUnits !== null ? (float) $lecUnits > 0 : ! $isLab) : false;
                $item['has_lab']          = $catalog !== null ? ($labUnits !== null ? (float) $labUnits > 0 : $isLab) : false;
                $item['override_granted'] = $overrideGranted;
                $item['corequisites']     = $enrollments->unmetCorequisites($userId, $item['code'], $enlistTerm);
                $item['offerings']        = $enrollments->subjectOfferings($item['code'], $programName, $enlistTerm);

                $dueSubjects[] = $item;
            }
        }

        return view('student/enlistment', [
            'pageTitle'   => 'Enlistment',
            'windowOpen'  => $windowOpen,
            'activeTerm'  => $activeTerm,
            'enlistTerm'  => $enlistTerm,
            'enlistment'  => $enlistment,
            'items'       => $items,
            'dueSubjects' => $dueSubjects,
            'eligible'    => $eligible,
            'balance'     => $balance,
            'cap'         => (int) (setting('Academic.maxStudentUnits') ?? 24),
            'unitsUsed'   => $unitsUsed,
            'studentDetail' => $studentDetail,
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
            return redirect()->to('/student/enlistment')->with('errors', ['Enlistment not found.']);
        }

        $result = (new StudentEnlistmentModel())->addItem($enlistmentId, $userId, $sectionId, $subjectCode);

        if (! $result['ok']) {
            return redirect()->to('/student/enlistment')->with('errors', [$result['error']]);
        }

        return redirect()->to('/student/enlistment')->with('message', "{$subjectCode} added to your enlistment.");
    }

    /**
     * The checkbox-driven counterpart to addItem(): add every checked
     * "sectionId|subjectCode" selection from the Course Selection table in
     * one pass, instead of the student clicking a separate "Add" button
     * (and page reload) per row. Each selection is still independently
     * re-validated by StudentEnlistmentModel::addItem() — a partial failure
     * (e.g. one subject filled up between page load and submit) never
     * blocks the others.
     */
    public function addItems()
    {
        $userId  = (int) auth()->id();
        $student = (new StudentModel())->where('user_id', $userId)->first();

        if ($student === null) {
            return redirect()->to('/student/enlistment')->with('errors', ['No student record is linked to your account.']);
        }

        $enlistmentId = (int) $this->request->getPost('enlistment_id');
        $selections   = (array) $this->request->getPost('selections');

        if ($selections === []) {
            return redirect()->to('/student/enlistment')->with('errors', ['Select at least one subject to add.']);
        }

        $enlistment = (new StudentEnlistmentModel())->find($enlistmentId);

        if ($enlistment === null || (int) $enlistment['student_id'] !== (int) $student['student_id']) {
            return redirect()->to('/student/enlistment')->with('errors', ['Enlistment not found.']);
        }

        $model   = new StudentEnlistmentModel();
        $added   = [];
        $failed  = [];

        foreach ($selections as $selection) {
            [$sectionId, $subjectCode] = array_pad(explode('|', (string) $selection, 2), 2, null);

            if ($sectionId === null || $subjectCode === null || ! ctype_digit($sectionId)) {
                continue;
            }

            $result = $model->addItem($enlistmentId, $userId, (int) $sectionId, $subjectCode);

            if ($result['ok']) {
                $added[] = $subjectCode;
            } else {
                $failed[] = "{$subjectCode}: {$result['error']}";
            }
        }

        if ($added === [] && $failed === []) {
            return redirect()->to('/student/enlistment')->with('errors', ['Nothing was added.']);
        }

        $redirect = redirect()->to('/student/enlistment');

        if ($added !== []) {
            $redirect = $redirect->with('message', 'Added to your enlistment: ' . implode(', ', $added) . '.');
        }

        if ($failed !== []) {
            $redirect = $redirect->with('errors', $failed);
        }

        return $redirect;
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
            return redirect()->to('/student/enlistment')->with('errors', ['Enlistment not found.']);
        }

        $result = (new StudentEnlistmentModel())->removeItem($enlistmentId, $itemId);

        if (! $result['ok']) {
            return redirect()->to('/student/enlistment')->with('errors', [$result['error']]);
        }

        return redirect()->to('/student/enlistment')->with('message', 'Removed from your enlistment.');
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

        return redirect()->to('/student/enlistment')->with('message', 'Your enlistment was submitted for review.');
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

        // Uses corEligibility(), not coeEligibility() — this just needs
        // "currently enrolled," not a payment check, and corEligibility()
        // is the one with that looser rule (see its own docblock). Was
        // previously written as coeEligibility() back when COE was the
        // payment-independent one of the two; swapped to keep this
        // print-right-after-enrolling action payment-independent now that
        // the two have traded rules.
        $documents   = new GeneratedDocumentModel();
        $eligibility = $documents->corEligibility((int) $student['student_id'], $userId);

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
