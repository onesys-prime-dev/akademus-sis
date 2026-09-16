<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentModel extends Model
{
    protected $table      = 'students';
    protected $primaryKey = 'student_id';

    protected $allowedFields = [
        'user_id',
        'applicant_id',
        'student_number',
        'program_id',
        'admission_date',
        'student_status',
    ];

    protected $useTimestamps = true;

    /**
     * The real Admission -> SIS handoff. Creates a Shield user (group =
     * student) plus a students row for a newly-admitted applicant, or
     * returns the existing student untouched if this applicant_id has
     * already been promoted — the webhook that calls this can be retried
     * safely by Admissions without risk of a duplicate account.
     *
     * @return array{ok: bool, error?: string, student?: array}
     */
    public function promoteFromApplicant(array $data): array
    {
        $db = $this->db;
        $db->transStart();

        // Idempotency check happens *inside* the transaction, locked, so two
        // concurrent retries of the same webhook can't both pass the check
        // and both insert.
        $existing = $this->lockByApplicantId($data['applicant_id']);

        if ($existing !== null) {
            $db->transComplete();

            return ['ok' => true, 'student' => $this->hydrate($existing)];
        }

        $programId = null;

        if (! empty($data['program_code'])) {
            $program = (new ProgramModel())->where('program_code', $data['program_code'])->first();

            if ($program === null) {
                $db->transRollback();

                return ['ok' => false, 'error' => "Unknown program code: {$data['program_code']}"];
            }

            $programId = (int) $program['program_id'];
        }

        $users = auth()->getProvider();

        // Reject rather than silently reuse an existing account under a
        // different applicant_id — a real email collision is a data problem
        // to surface, not paper over.
        if ($users->findByCredentials(['email' => $data['email']]) !== null) {
            $db->transRollback();

            return ['ok' => false, 'error' => 'A user with this email already exists.'];
        }

        // Shield's own UserModel::$allowedFields is limited to username/
        // status/active/last_active — it silently drops anything else, so
        // the custom columns (first_name etc.) are set via a raw update
        // afterward, matching the existing convention in this codebase for
        // every other custom users column (see ChairpersonController::
        // assignStudent writing section_id the same way).
        $users->save([
            'username' => $this->usernameFor($data),
            'active'   => 1,
        ]);

        $userId = $users->getInsertID();

        $db->table('users')->where('id', $userId)->update([
            'first_name'     => $data['first_name'],
            'middle_name'    => $data['middle_name'] ?? null,
            'last_name'      => $data['last_name'],
            'contact_number' => $data['contact_number'] ?? null,
        ]);

        $user = $users->findById($userId);

        $user->createEmailIdentity([
            'email'    => $data['email'],
            'password' => bin2hex(random_bytes(6)),
        ]);

        $user->addGroup('student');

        $studentNumber = $this->nextStudentNumber();

        $this->insert([
            'user_id'         => $userId,
            'applicant_id'    => $data['applicant_id'],
            'student_number'  => $studentNumber,
            'program_id'      => $programId,
            'admission_date'  => $data['admission_date'] ?? date('Y-m-d H:i:s'),
            'student_status'  => 'active',
        ]);

        $student = $this->find($this->getInsertID());

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not create the student record. Please retry.'];
        }

        return ['ok' => true, 'student' => $this->hydrate($student)];
    }

    /**
     * Row-locks the students row for this applicant_id (if any) for the
     * duration of the enclosing transaction, so two concurrent promote()
     * calls for the same applicant serialize instead of racing.
     */
    private function lockByApplicantId(string $applicantId): ?array
    {
        $suffix = $this->db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';

        return $this->db->query(
            "SELECT * FROM students WHERE applicant_id = ?{$suffix}",
            [$applicantId]
        )->getRowArray();
    }

    /**
     * STU-{current year}-{00001}, sequential within the year. Locks the
     * matching rows for the duration of the transaction so two concurrent
     * promotions in the same year can't be handed the same number.
     */
    private function nextStudentNumber(): string
    {
        $year   = date('Y');
        $suffix = $this->db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';

        $count = $this->db->query(
            "SELECT COUNT(*) AS cnt FROM students WHERE student_number LIKE ?{$suffix}",
            ["STU-{$year}-%"]
        )->getRowArray();

        $next = ((int) ($count['cnt'] ?? 0)) + 1;

        return sprintf('STU-%s-%05d', $year, $next);
    }

    private function usernameFor(array $data): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]/i', '', $data['first_name'] . $data['last_name']));

        return $base !== '' ? $base . '.' . substr(bin2hex(random_bytes(3)), 0, 4) : 'student' . random_int(10000, 99999);
    }

    private function hydrate(array $student): array
    {
        return $student;
    }
}
