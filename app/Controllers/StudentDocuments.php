<?php

namespace App\Controllers;

use App\Models\DocumentRequestModel;
use App\Models\GeneratedDocumentModel;
use App\Models\StudentModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * The student-facing document request/status page — none existed before
 * this (every TOR/certificate request was registrar-entered). Only 'cor'
 * and 'certificate_enrollment' are exposed as self-requestable types for
 * now (the two whose gate tolerates a balance, since a student is
 * officially enrolled regardless of what they still owe);
 * DocumentRequestModel::request() itself is generic and can back other
 * self-request types later with no model changes.
 */
class StudentDocuments extends BaseController
{
    public function index()
    {
        $userId  = (int) auth()->id();
        $student = (new StudentModel())->where('user_id', $userId)->first();

        if ($student === null) {
            return redirect()->to('/student/dashboard')->with('errors', ['No student record is linked to your account.']);
        }

        $documents  = new GeneratedDocumentModel();
        $corEligibility = $documents->corEligibility((int) $student['student_id'], $userId);
        $coeEligibility = $documents->coeEligibility($userId);

        return view('student/documents', [
            'pageTitle'   => 'Documents',
            'corEligible' => $corEligibility['ok'],
            'corReason'   => $corEligibility['error'] ?? null,
            'coeEligible' => $coeEligibility['ok'],
            'coeReason'   => $coeEligibility['error'] ?? null,
            'requests'    => (new DocumentRequestModel())->forStudent((int) $student['student_id']),
            'generated'   => $documents->forStudent((int) $student['student_id']),
        ]);
    }

    public function requestCor()
    {
        return $this->requestDocument('cor', fn (GeneratedDocumentModel $m, array $student, int $userId) => $m->corEligibility((int) $student['student_id'], $userId));
    }

    public function requestCoe()
    {
        return $this->requestDocument('certificate_enrollment', fn (GeneratedDocumentModel $m, array $student, int $userId) => $m->coeEligibility($userId));
    }

    private function requestDocument(string $type, \Closure $checkEligibility)
    {
        $userId  = (int) auth()->id();
        $student = (new StudentModel())->where('user_id', $userId)->first();

        if ($student === null) {
            return redirect()->to('/student/documents')->with('errors', ['No student record is linked to your account.']);
        }

        $eligibility = $checkEligibility(new GeneratedDocumentModel(), $student, $userId);

        if (! $eligibility['ok']) {
            return redirect()->to('/student/documents')->with('errors', [$eligibility['error']]);
        }

        $purpose = trim((string) $this->request->getPost('purpose'));
        $result  = (new DocumentRequestModel())->request((int) $student['student_id'], $type, $purpose ?: null, $userId);

        if (! $result['ok']) {
            return redirect()->to('/student/documents')->with('errors', [$result['error']]);
        }

        return redirect()->to('/student/documents')->with('message', DocumentRequestModel::TYPE_LABELS[$type] . ' request submitted.');
    }

    public function download(int $id)
    {
        [$document, $path] = $this->authorizedDocument($id);

        return $this->response->download($path, null)->setFileName($document['document_type'] . '_' . $document['verification_code'] . '.pdf');
    }

    /**
     * Same authorization/file as download(), but served inline (not as an
     * attachment) so the student's "View" button can load it straight into
     * an <iframe> inside a modal instead of leaving the page.
     */
    public function view(int $id)
    {
        [$document, $path] = $this->authorizedDocument($id);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $document['document_type'] . '_' . $document['verification_code'] . '.pdf"')
            ->setBody(file_get_contents($path));
    }

    /**
     * @return array{0: array, 1: string} the document row and its file path
     */
    private function authorizedDocument(int $id): array
    {
        $documents = new GeneratedDocumentModel();
        $document  = $documents->find($id);

        if ($document === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $student = (new StudentModel())->where('user_id', (int) auth()->id())->first();
        $isOwner = $student !== null && (int) $document['student_id'] === (int) $student['student_id'];
        $isStaff = auth()->user()->can('documents.issue');

        if (! $isOwner && ! $isStaff) {
            throw PageNotFoundException::forPageNotFound();
        }

        $path = $documents->filePath($document);

        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return [$document, $path];
    }
}
