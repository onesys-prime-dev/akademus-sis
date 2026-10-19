<?php

namespace App\Controllers\Registrar;

use App\Controllers\BaseController;
use App\Libraries\RegistrarPortal;
use App\Models\PaymentScheduleModel;
use App\Models\StudentEnlistmentModel;
use App\Models\TuitionAssessmentModel;

/**
 * Registrar's final step: dean_noted -> enrolled/conversion_failed. The
 * approve action is where StudentEnlistmentModel::registrarApprove()
 * actually converts cart items into real enrollments (see that method's
 * docblock) — institution-wide queue, no program scoping, same as
 * GradeChangeRequestModel::pendingRegistrarFinal().
 */
class EnlistmentController extends BaseController
{
    public function index()
    {
        return view('registrar/enlistments', [
            'pageTitle' => 'Enlistment Approvals',
            'requests'  => (new StudentEnlistmentModel())->pendingRegistrarApproval(),
        ] + RegistrarPortal::shell('enlistments'));
    }

    public function approve()
    {
        $registrarId = (int) auth()->id();
        $id          = (int) $this->request->getPost('id');
        $notes       = (string) $this->request->getPost('notes');

        $result = (new StudentEnlistmentModel())->registrarApprove($id, $registrarId, $notes ?: null);

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        $message = $result['failed'] === []
            ? 'Enlistment approved and the student was enlisted into every selected subject.'
            : 'Enlistment approved with some subjects failing conversion: ' . implode('; ', array_column($result['failed'], 'error'));

        $message .= ' ' . $this->assessmentSummary((int) $result['student_id'], (string) $result['academic_year']);

        return redirect()->to('/registrar/enlistments')->with('message', trim($message));
    }

    /**
     * A short, human-readable readout of the tuition assessment + payment
     * schedule that TuitionAssessmentService generated automatically inside
     * enrollOne() — surfaced here so the registrar sees what the student
     * now owes instead of it happening silently in the background.
     */
    private function assessmentSummary(int $studentId, string $academicYear): string
    {
        $assessment = (new TuitionAssessmentModel())->forStudentYear($studentId, $academicYear);

        if ($assessment === null || (float) $assessment['total_amount'] <= 0) {
            return 'No tuition rate is set for this student\'s program/year yet — tuition could not be assessed.';
        }

        $schedule = (new PaymentScheduleModel())->withBalances((int) $assessment['assessment_id'], (float) $assessment['amount_paid']);
        $total    = number_format((float) $assessment['total_amount'], 2);

        if (count($schedule) <= 1) {
            return "Tuition assessed: ₱{$total}, due in full.";
        }

        $first = $schedule[0];

        return "Tuition assessed: ₱{$total} across " . count($schedule) . ' installments ('
            . esc($first['label']) . ': ₱' . number_format((float) $first['amount'], 2) . ' due first).';
    }

    public function returnToStudent()
    {
        $registrarId = (int) auth()->id();
        $id          = (int) $this->request->getPost('id');
        $notes       = (string) $this->request->getPost('notes');

        $result = (new StudentEnlistmentModel())->returnToStudent($id, 'dean_noted', $registrarId, 'registrar', $notes);

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->to('/registrar/enlistments')->with('message', 'Enlistment returned to student.');
    }
}
