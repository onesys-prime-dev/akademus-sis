<?php

namespace App\Controllers\API;

use App\Controllers\BaseController;
use App\Models\StudentModel;
use CodeIgniter\API\ResponseTrait;

class StudentAPIController extends BaseController
{
    use ResponseTrait;

    /**
     * The Admission -> SIS handoff. Called by the (separate) Admissions
     * Portal once an applicant has committed enough to become a real SIS
     * student (see docs/sis-development-roadmap for the recommended trigger
     * point: Reservation Fee marked PAID). Idempotent on applicant_id — a
     * retried call with the same applicant_id returns the existing student
     * rather than creating a duplicate.
     */
    public function promote()
    {
        $key = $this->request->getHeaderLine('X-API-KEY');

        if ($key === '' || $key !== env('API_KEY')) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Unauthorized',
                ]);
        }

        $data = $this->request->getJSON(true);

        if (! is_array($data)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid JSON payload.',
                ]);
        }

        $required = [
            'applicant_id',
            'first_name',
            'last_name',
            'email',
        ];

        foreach ($required as $field) {
            if (! isset($data[$field]) || trim((string) $data[$field]) === '') {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'success' => false,
                        'message' => "{$field} is required",
                    ]);
            }
        }

        if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'success' => false,
                    'message' => 'email is not a valid email address',
                ]);
        }

        $result = (new StudentModel())->promoteFromApplicant($data);

        if (! $result['ok']) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'success' => false,
                    'message' => $result['error'],
                ]);
        }

        return $this->respond([
            'success' => true,
            'student' => [
                'student_id'     => $result['student']['student_id'],
                'student_number' => $result['student']['student_number'],
                'applicant_id'   => $result['student']['applicant_id'],
                'student_status' => $result['student']['student_status'],
            ],
        ]);
    }
}
