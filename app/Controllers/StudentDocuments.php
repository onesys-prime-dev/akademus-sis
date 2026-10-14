<?php

namespace App\Controllers;

use App\Models\DocumentRequestModel;
use App\Models\GeneratedDocumentModel;
use App\Models\StudentModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * The student-facing document request/status page — none existed before
 * this (every TOR/certificate request was registrar-entered). Only 'cor'
 * is exposed as a requestable type for now; DocumentRequestModel::request()
 * itself is generic and can back other self-request types later with no
 * model changes.
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

        $eligibility = (new GeneratedDocumentModel())->corEligibility((int) $student['student_id'], $userId);

        return view('student/documents', [
            'pageTitle'   => 'Documents',
            'corEligible' => $eligibility['ok'],
            'corReason'   => $eligibility['error'] ?? null,
            'requests'    => (new DocumentRequestModel())->forStudent((int) $student['student_id']),
            'generated'   => (new GeneratedDocumentModel())->forStudent((int) $student['student_id']),
        ]);
    }

    public function requestCor()
    {
        $userId  = (int) auth()->id();
        $student = (new StudentModel())->where('user_id', $userId)->first();

        if ($student === null) {
            return redirect()->to('/student/documents')->with('errors', ['No student record is linked to your account.']);
        }

        $eligibility = (new GeneratedDocumentModel())->corEligibility((int) $student['student_id'], $userId);

        if (! $eligibility['ok']) {
            return redirect()->to('/student/documents')->with('errors', [$eligibility['error']]);
        }

        $purpose = trim((string) $this->request->getPost('purpose'));
        $result  = (new DocumentRequestModel())->request((int) $student['student_id'], 'cor', $purpose ?: null, $userId);

        if (! $result['ok']) {
            return redirect()->to('/student/documents')->with('errors', [$result['error']]);
        }

        return redirect()->to('/student/documents')->with('message', 'Your Certificate of Registration request was submitted.');
    }

    public function download(int $id)
    {
        $document = (new GeneratedDocumentModel())->find($id);

        if ($document === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $student = (new StudentModel())->where('user_id', (int) auth()->id())->first();
        $isOwner = $student !== null && (int) $document['student_id'] === (int) $student['student_id'];
        $isStaff = auth()->user()->can('documents.issue');

        if (! $isOwner && ! $isStaff) {
            throw PageNotFoundException::forPageNotFound();
        }

        $path = (new GeneratedDocumentModel())->filePath($document);

        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($path, null)->setFileName($document['document_type'] . '_' . $document['verification_code'] . '.pdf');
    }
}
