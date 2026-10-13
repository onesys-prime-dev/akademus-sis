<?php

namespace App\Controllers\Registrar;

use App\Controllers\BaseController;
use App\Models\GradeModel;

/**
 * Registrar-side grade approval + lock — the final two stages of the chain
 * (dean_noted -> registrar_approved -> locked). Institution-wide: the
 * registrar has no "own department" to scope by, unlike
 * Chairperson\GradeApprovalController / Dean\GradeApprovalController.
 */
class GradeApprovalController extends BaseController
{
    public function index()
    {
        $gradeModel = new GradeModel();

        return view('registrar/grade_approvals', [
            'pageTitle'      => 'Grade Approvals',
            'user'           => ['name' => auth()->user()->username ?? 'Registrar', 'role' => 'Registrar'],
            'classes'        => $gradeModel->notedForRegistrar(),
            'approvedClasses' => $gradeModel->approvedForRegistrar(),
            'lockedClasses'  => $gradeModel->lockedClasses(),
        ]);
    }

    public function show()
    {
        $context = $this->contextFromPayload($this->request->getGet());

        if ($context === null) {
            return redirect()->to('/registrar/grade-approvals')->with('error', 'Missing class context.');
        }

        $result = (new GradeModel())->rosterWithGrades($context['academic_year'], $context['program'], $context['year_level'], $context['section'], $context['subject_code']);

        return view('registrar/grade_approval_class', [
            'pageTitle' => 'Grade Approvals — ' . $context['subject_code'],
            'user'      => ['name' => auth()->user()->username ?? 'Registrar', 'role' => 'Registrar'],
            'context'   => $context,
            'section'   => $result['section'],
            'offering'  => $result['offering'],
            'roster'    => $result['roster'],
        ]);
    }

    public function approve()
    {
        return $this->transition('approveClassForRegistrar', 'Could not approve this class.');
    }

    public function lock()
    {
        return $this->transition('lockClass', 'Could not lock this class.');
    }

    private function transition(string $method, string $defaultError)
    {
        $registrarId = (int) auth()->id();
        $payload     = $this->request->getJSON(true);
        $context     = $this->contextFromPayload(is_array($payload) ? $payload : []);

        if ($context === null) {
            return $this->fail('Missing class context.');
        }

        $gradeModel = new GradeModel();
        $result     = $gradeModel->rosterWithGrades($context['academic_year'], $context['program'], $context['year_level'], $context['section'], $context['subject_code']);

        if ($result['roster'] === []) {
            return $this->fail('This class has no enrolled students.');
        }

        $enrollmentIds = array_map(static fn (array $r) => (int) $r['enrollment_id'], $result['roster']);

        $outcome = $method === 'lockClass'
            ? $gradeModel->lockClass($enrollmentIds, $registrarId)
            : $gradeModel->approveClassForRegistrar($enrollmentIds, $registrarId, isset($payload['comments']) ? (string) $payload['comments'] : null);

        if (! $outcome['ok']) {
            return $this->fail($outcome['error'] ?? $defaultError);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    private function contextFromPayload(array $payload): ?array
    {
        $keys    = ['academic_year', 'program', 'year_level', 'section', 'subject_code'];
        $context = [];

        foreach ($keys as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key]) || $payload[$key] === '') {
                return null;
            }

            $context[$key] = $payload[$key];
        }

        return $context;
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}
