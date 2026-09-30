<?php

namespace App\Controllers\API;

use App\Controllers\BaseController;
use App\Libraries\StudentWelcomeMailer;
use App\Models\StudentModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Events\Events;

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
     *
     * X-API-KEY auth now happens at the route/filter layer (ApiKeyFilter,
     * 'apikey' filter on the 'api' group in Routes.php) before this method is
     * even reached — matching how every other role group is protected, and
     * using a constant-time comparison instead of the plain !== this method
     * used to do inline.
     */
    public function promote()
    {
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

        // Brand-new student only (a retried handoff returns the existing one
        // and must not email again). Sent after the response so a slow SMTP
        // server never delays or fails the Admissions call.
        if (! empty($result['created'])) {
            $student      = $result['student'];
            $email        = (string) $data['email'];
            $first        = (string) $data['first_name'];
            $tempPassword = (string) $result['temp_password'];

            Events::on('post_system', static function () use ($student, $email, $first, $tempPassword): void {
                (new StudentWelcomeMailer())->send($email, $first, $student['student_number'], $tempPassword);
            });
        }

        return $this->respond([
            'success'         => true,
            'program_created' => (bool) ($result['program_created'] ?? false),
            'student'         => [
                'student_id'     => $result['student']['student_id'],
                'student_number' => $result['student']['student_number'],
                'applicant_id'   => $result['student']['applicant_id'],
                'student_status' => $result['student']['student_status'],
            ],
        ]);
    }
}
