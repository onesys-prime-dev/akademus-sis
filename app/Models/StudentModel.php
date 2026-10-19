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
        'curriculum_id',
        'admission_date',
        'student_status',
        'intended_school_year',
        'intended_semester',
        'date_of_birth',
        'gender',
        'home_address',
        'city',
        'emergency_contact_name',
        'emergency_contact_number',
    ];

    protected $useTimestamps = true;

    /**
     * The real Admission -> SIS handoff. Creates a Shield user (group =
     * student) plus a students row for a newly-admitted applicant, or
     * returns the existing student untouched if this applicant_id has
     * already been promoted — the webhook that calls this can be retried
     * safely by Admissions without risk of a duplicate account.
     *
     * Each rejection path pairs transRollback() with resetTransStatus() —
     * see EnrollmentModel::enrollOne()'s docblock for why: without it, an
     * expected rejection here (unknown program code, duplicate email)
     * leaves the connection's transStatus flag poisoned for the rest of the
     * request/process, silently failing any later, unrelated transaction on
     * the same connection even though its own queries succeeded.
     *
     * temp_password is only present on a brand-new promotion (never on a
     * retried/idempotent one) — the caller (StudentAPIController) uses it
     * once to send StudentWelcomeMailer, then it is gone; it is never
     * written to any column.
     *
     * @return array{ok: bool, error?: string, student?: array, created?: bool, temp_password?: string}
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

        $programId      = null;
        $programCreated = null;

        if (! empty($data['program_code'])) {
            $resolved = $this->resolveProgram((string) $data['program_code'], (string) ($data['program_name'] ?? ''));

            if (! $resolved['ok']) {
                $db->transRollback();
                $db->resetTransStatus();

                return ['ok' => false, 'error' => $resolved['error']];
            }

            $programId      = $resolved['program_id'];
            $programCreated = $resolved['created'] ? $resolved['program'] : null;
        }

        // Pin the student to their program's (single, always-live) curriculum.
        $curriculumId = null;

        if ($programId !== null) {
            $curriculum = (new CurriculumModel())->liveFor($programId);
            $curriculumId = $curriculum['curriculum_id'] ?? null;
        }

        $users = auth()->getProvider();

        // Reject rather than silently reuse an existing account under a
        // different applicant_id — a real email collision is a data problem
        // to surface, not paper over.
        if ($users->findByCredentials(['email' => $data['email']]) !== null) {
            $db->transRollback();
            $db->resetTransStatus();

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

        // Random, human-typeable temp password — mirrors Admissions'
        // AuthController::register() (bin2hex(random_bytes(6))). Emailed
        // once by StudentWelcomeMailer, never persisted in plaintext;
        // must_change_password forces it to be replaced on first login.
        $tempPassword = bin2hex(random_bytes(6));

        $db->table('users')->where('id', $userId)->update([
            'first_name'            => $data['first_name'],
            'middle_name'           => $data['middle_name'] ?? null,
            'last_name'             => $data['last_name'],
            'contact_number'        => $data['contact_number'] ?? null,
            'must_change_password'  => 1,
        ]);

        $user = $users->findById($userId);

        $user->createEmailIdentity([
            'email'    => $data['email'],
            'password' => $tempPassword,
        ]);

        $user->addGroup('student');

        $studentNumber = $this->nextStudentNumber();

        $this->insert([
            'user_id'               => $userId,
            'applicant_id'          => $data['applicant_id'],
            'student_number'        => $studentNumber,
            'program_id'            => $programId,
            'curriculum_id'         => $curriculumId,
            'admission_date'        => $data['admission_date'] ?? date('Y-m-d H:i:s'),
            'student_status'        => 'active',
            // Snapshot of what Admission recorded, if it sent one — never
            // overwritten afterward. The term the student is actually
            // SECTIONED into is a separate, later decision; this is only
            // provenance for spotting a mismatch (see Student Records'
            // Admission Info tab).
            'intended_school_year'  => $data['school_year'] ?? null,
            'intended_semester'     => $data['semester'] ?? null,
        ]);

        $student = $this->find($this->getInsertID());

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not create the student record. Please retry.'];
        }

        // Only after the commit: tell admins a program appeared that still
        // needs a college and a published curriculum.
        if ($programCreated !== null) {
            $this->notifyAdminsOfNewProgram($programCreated);
        }

        return [
            'ok'              => true,
            'student'         => $this->hydrate($student),
            'created'         => true,
            'temp_password'   => $tempPassword,
            'program_created' => $programCreated !== null,
        ];
    }

    /**
     * Admissions programs are dynamic (admins add them at will), so the SIS
     * cannot ship a fixed list. An unknown code is created on the spot from
     * the name Admissions sends — with no college and no curriculum, which an
     * admin must attach before the program can be scheduled. Without a name
     * we still refuse rather than invent one.
     *
     * @return array{ok: bool, error?: string, program_id?: int, created?: bool, program?: array}
     */
    private function resolveProgram(string $code, string $name): array
    {
        $programs = new ProgramModel();
        $code     = strtoupper(trim($code));
        $name     = trim($name);

        $program = $programs->where('program_code', $code)->first();

        if ($program !== null) {
            return ['ok' => true, 'program_id' => (int) $program['program_id'], 'created' => false];
        }

        if ($name === '') {
            return ['ok' => false, 'error' => "Unknown program code: {$code}"];
        }

        if (preg_match('/^[A-Z0-9][A-Z0-9 ._&-]{0,19}$/', $code) !== 1 || mb_strlen($name) > 225) {
            return ['ok' => false, 'error' => "Invalid program code or name: {$code}"];
        }

        // program_code is unique: if a concurrent promote created it a moment
        // ago, the insert is rejected and we simply use theirs.
        $inserted = $this->db->table('programs')->ignore(true)->insert([
            'program_code' => $code,
            'program_name' => $name,
            'is_active'    => 1,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        $program = $programs->where('program_code', $code)->first();

        if ($program === null) {
            return ['ok' => false, 'error' => "Could not create program {$code}."];
        }

        return [
            'ok'         => true,
            'program_id' => (int) $program['program_id'],
            'created'    => $inserted !== false && $this->db->affectedRows() > 0,
            'program'    => $program,
        ];
    }

    private function notifyAdminsOfNewProgram(array $program): void
    {
        $adminIds = $this->db->table('auth_groups_users')
            ->select('user_id')
            ->whereIn('group', ['admin', 'superadmin'])
            ->get()
            ->getResultArray();

        $notifications = new NotificationModel();

        foreach (array_unique(array_column($adminIds, 'user_id')) as $adminId) {
            $notifications->notify(
                (int) $adminId,
                'program.created',
                "New program {$program['program_code']} ({$program['program_name']}) was added automatically from Admissions. "
                . 'Assign it to a department and publish a curriculum before scheduling it.',
                'admin/programs/edit/' . $program['program_id']
            );
        }
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
    public function nextStudentNumber(): string
    {
        $year   = date('Y');
        $suffix = $this->db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';

        // MAX(suffix)+1, not COUNT(*)+1 — a count drifts out of sync with
        // reality the moment any row in the sequence is deleted (e.g. a
        // test account cleanup), handing out a number that collides with
        // one still in use further up the sequence.
        $row = $this->db->query(
            "SELECT MAX(CAST(SUBSTRING_INDEX(student_number, '-', -1) AS UNSIGNED)) AS max_suffix
             FROM students WHERE student_number LIKE ?{$suffix}",
            ["STU-{$year}-%"]
        )->getRowArray();

        $next = ((int) ($row['max_suffix'] ?? 0)) + 1;

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

    public const STATUSES = ['active', 'inactive', 'graduated', 'dropped', 'loa', 'dismissed', 'transferred'];

    /**
     * Student list for the registrar/admin Student Records page, joined to
     * everything a list row needs in one query. Filters mirror the
     * dashboard's Data Readiness expectations: program, year level (via the
     * student's current section), status, and free-text search.
     */
    public function listFiltered(array $filters): array
    {
        $builder = $this->db->table('students s')
            ->select('s.student_id, s.student_number, s.applicant_id, s.student_status, s.admission_date,
                      u.id as user_id, u.first_name, u.last_name, u.username, u.section_id,
                      i.secret as email,
                      p.program_id, p.program_code, p.program_name,
                      c.curriculum_id, c.curriculum_code,
                      sec.section, sec.year_level, sec.academic_year')
            ->join('users u', 'u.id = s.user_id')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->join('programs p', 'p.program_id = s.program_id', 'left')
            ->join('curriculum c', 'c.curriculum_id = s.curriculum_id', 'left')
            ->join('sections sec', 'sec.section_id = u.section_id', 'left')
            ->where('u.deleted_at', null);

        if (! empty($filters['program_id'])) {
            $builder->where('s.program_id', (int) $filters['program_id']);
        }

        if (! empty($filters['year_level'])) {
            $builder->where('sec.year_level', $filters['year_level']);
        }

        if (! empty($filters['section_id'])) {
            $builder->where('u.section_id', (int) $filters['section_id']);
        }

        if (! empty($filters['status'])) {
            $builder->where('s.student_status', $filters['status']);
        }

        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $builder->groupStart()
                ->like('u.first_name', $q)
                ->orLike('u.last_name', $q)
                ->orLike('s.student_number', $q)
                ->orLike('i.secret', $q)
            ->groupEnd();
        }

        return $builder->orderBy('u.last_name')->orderBy('u.first_name')->get()->getResultArray();
    }

    /**
     * Everything the Student Records detail page needs: the student row,
     * their section, and their full enrollment history — each sourced from
     * whichever table actually owns it (see the roadmap's field-by-field
     * ownership table).
     */
    public function findDetailed(int $studentId): ?array
    {
        $student = $this->db->table('students s')
            ->select('s.*, u.id as user_id, u.username, u.first_name, u.middle_name, u.last_name, u.contact_number, u.section_id, u.photo_mime,
                      i.secret as email, p.program_id, p.program_code, p.program_name,
                      c.curriculum_code, c.curriculum_name')
            ->join('users u', 'u.id = s.user_id')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->join('programs p', 'p.program_id = s.program_id', 'left')
            ->join('curriculum c', 'c.curriculum_id = s.curriculum_id', 'left')
            ->where('s.student_id', $studentId)
            ->get()
            ->getRowArray();

        if ($student === null) {
            return null;
        }

        $student['section'] = $student['section_id']
            ? (new SectionModel())->find((int) $student['section_id'])
            : null;

        // Every curriculum for the student's program — lets the registrar
        // move the student between programs/curricula when they transfer.
        $student['available_curricula'] = $student['program_id']
            ? (new CurriculumModel())
                ->where('program_id', $student['program_id'])
                ->orderBy('curriculum_id', 'DESC')
                ->findAll()
            : [];

        $student['curriculum_history'] = $this->db->table('student_curriculum_logs l')
            ->select('l.reason, l.created_at, u2.username as changed_by_username,
                      cf.curriculum_code as from_code,
                      ct.curriculum_code as to_code')
            ->join('users u2', 'u2.id = l.changed_by', 'left')
            ->join('curriculum cf', 'cf.curriculum_id = l.from_curriculum_id', 'left')
            ->join('curriculum ct', 'ct.curriculum_id = l.to_curriculum_id', 'left')
            ->where('l.student_id', $studentId)
            ->orderBy('l.created_at', 'DESC')
            ->get()
            ->getResultArray();

        $student['enrollment_history'] = $this->db->table('enrollments e')
            ->select('e.enrollment_id, e.subject_code, e.method, e.status, e.created_at,
                      e.academic_year, sec.program, sec.year_level, sec.section')
            ->join('sections sec', 'sec.section_id = e.section_id')
            ->where('e.user_id', $student['user_id'])
            ->orderBy('e.academic_year', 'DESC')
            ->orderBy('e.subject_code')
            ->get()
            ->getResultArray();

        $student['status_history'] = $this->db->table('student_status_logs l')
            ->select('l.from_status, l.to_status, l.reason, l.created_at, u2.username as changed_by_username')
            ->join('users u2', 'u2.id = l.changed_by', 'left')
            ->where('l.student_id', $studentId)
            ->orderBy('l.created_at', 'DESC')
            ->get()
            ->getResultArray();

        $student['tuition_assessments'] = (new TuitionAssessmentModel())->historyFor($studentId);
        $student['payment_history']     = (new StudentPaymentModel())->historyFor($studentId);
        $student['pending_proofs']      = (new PaymentProofModel())->where('student_id', $studentId)->where('status', 'pending')->findAll();

        $currentAssessment = $student['tuition_assessments'][0] ?? null;

        $student['assessment_items']  = $currentAssessment !== null
            ? (new AssessmentItemModel())->forAssessment((int) $currentAssessment['assessment_id'])
            : [];
        $student['payment_schedule']  = $currentAssessment !== null
            ? (new PaymentScheduleModel())->withBalances((int) $currentAssessment['assessment_id'], (float) $currentAssessment['amount_paid'])
            : [];

        return $student;
    }

    /**
     * Records an academic-status change with a required reason and the
     * acting admin, so a status flip is never silent (roadmap completion
     * criterion for Student Records: "Status change is audited").
     */
    public function changeStatus(int $studentId, string $newStatus, string $reason, ?int $changedBy): array
    {
        if (! in_array($newStatus, self::STATUSES, true)) {
            return ['ok' => false, 'error' => 'Invalid status.'];
        }

        $student = $this->find($studentId);

        if ($student === null) {
            return ['ok' => false, 'error' => 'Student not found.'];
        }

        if ($student['student_status'] === $newStatus) {
            return ['ok' => false, 'error' => 'Student is already in that status.'];
        }

        $db = $this->db;
        $db->transStart();

        $this->update($studentId, ['student_status' => $newStatus]);

        $db->table('student_status_logs')->insert([
            'student_id'  => $studentId,
            'from_status' => $student['student_status'],
            'to_status'   => $newStatus,
            'reason'      => $reason,
            'changed_by'  => $changedBy,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if ($db->transStatus() !== false) {
            // student_status_logs is append-only too, but it's a narrow,
            // student-status-specific trail — audit_logs is the one
            // cross-domain view (admin/audit-logs) staff actually search,
            // and a status flip (especially 'graduated'/'dismissed') is
            // exactly the high-stakes change that belongs there.
            (new AuditLogModel())->record(
                'student.status_changed',
                'student',
                $studentId,
                ['student_status' => $student['student_status']],
                ['student_status' => $newStatus],
                $reason,
                $changedBy,
                null
            );

            helper('url');
            (new NotificationModel())->notify(
                (int) $student['user_id'],
                'status_changed',
                "Your academic status has been changed to " . ucfirst($newStatus) . '.',
                site_url('student/profile')
            );
        }

        return ['ok' => $db->transStatus() !== false];
    }

    /**
     * Assigns or reassigns a student's curriculum — the "adviser-approved
     * equivalency mapping, itself audited" the handbook calls for when a
     * student's curriculum changes.
     */
    public function assignCurriculum(int $studentId, int $curriculumId, string $reason, ?int $changedBy): array
    {
        $student = $this->find($studentId);

        if ($student === null) {
            return ['ok' => false, 'error' => 'Student not found.'];
        }

        $curriculum = (new CurriculumModel())->find($curriculumId);

        if ($curriculum === null) {
            return ['ok' => false, 'error' => 'Curriculum not found.'];
        }

        if ((int) ($student['curriculum_id'] ?? 0) === $curriculumId) {
            return ['ok' => false, 'error' => 'Student is already on that curriculum version.'];
        }

        $db = $this->db;
        $db->transStart();

        $this->update($studentId, ['curriculum_id' => $curriculumId]);

        $db->table('student_curriculum_logs')->insert([
            'student_id'         => $studentId,
            'from_curriculum_id' => $student['curriculum_id'] ?? null,
            'to_curriculum_id'   => $curriculumId,
            'reason'             => $reason,
            'changed_by'         => $changedBy,
            'created_at'         => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if ($db->transStatus() !== false) {
            helper('url');
            (new NotificationModel())->notify(
                (int) $student['user_id'],
                'curriculum_changed',
                "Your curriculum has been updated to {$curriculum['curriculum_name']}. This may change which subjects you can enroll in.",
                site_url('student/profile')
            );
        }

        return ['ok' => $db->transStatus() !== false];
    }
}
