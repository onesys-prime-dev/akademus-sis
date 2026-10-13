<?php

namespace App\Controllers\Registrar;

use App\Controllers\BaseController;
use App\Libraries\RegistrarPortal;
use App\Models\DocumentRequestModel;
use App\Models\GeneratedDocumentModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Registrar's document request queue + generation — TOR, certificates of
 * enrollment/graduation/good-moral/candidacy. generate()/download() can
 * also be used ad hoc (no request behind it), e.g. a walk-in request
 * handled on the spot.
 */
class DocumentController extends BaseController
{
    public function index()
    {
        return view('registrar/documents_queue', [
            'pageTitle' => 'Document Requests',
            'requests'  => (new DocumentRequestModel())->pending(),
        ] + RegistrarPortal::shell('documents'));
    }

    /**
     * Type-ahead search over students, backing the "generate ad hoc"
     * search box — mirrors GradeModel::searchLockedGrades()'s pattern.
     */
    public function search()
    {
        $query = trim((string) $this->request->getGet('q'));

        if ($query === '') {
            return $this->response->setJSON(['ok' => true, 'results' => []]);
        }

        $results = db_connect()->table('students s')
            ->select('s.student_id, s.student_number, u.username')
            ->join('users u', 'u.id = s.user_id')
            ->groupStart()
                ->like('u.username', $query)
                ->orLike('s.student_number', $query)
            ->groupEnd()
            ->limit(20)
            ->get()
            ->getResultArray();

        return $this->response->setJSON(['ok' => true, 'results' => $results]);
    }

    public function generate()
    {
        $studentId = (int) $this->request->getPost('student_id');
        $type      = (string) $this->request->getPost('document_type');
        $requestId = $this->request->getPost('request_id') ? (int) $this->request->getPost('request_id') : null;

        if ($studentId === 0 || ! in_array($type, DocumentRequestModel::TYPES, true)) {
            return redirect()->back()->with('errors', ['Choose a student and document type.']);
        }

        $result = (new GeneratedDocumentModel())->generate($studentId, $type, (int) auth()->id(), $requestId);

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        if ($requestId !== null) {
            (new DocumentRequestModel())->markReleased($requestId, (int) auth()->id());
        }

        return redirect()->to('/registrar/documents/download/' . $result['id'])->with('message', 'Document generated.');
    }

    public function download($id)
    {
        $doc = (new GeneratedDocumentModel())->find((int) $id);

        if ($doc === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $path = (new GeneratedDocumentModel())->filePath($doc);

        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($path, null)->setFileName($doc['stored_filename']);
    }

    /**
     * Public verification lookup — outside every group filter, see
     * Routes.php. Must never expose more than document_type + issued date.
     */
    public function verify($code)
    {
        $result = (new GeneratedDocumentModel())->verify((string) $code);

        return view('documents/verify_result', [
            'pageTitle' => 'Document Verification',
            'result'    => $result,
        ]);
    }
}
