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

        // Pin the student to whichever curriculum version is currently
        // published+current for their program. If the program has no
        // published curriculum yet, the student is left unassigned
        // (curriculum_id null) rather than blocking promotion — that's an
        // administrative gap to fill in later via Student Records, not a
        // reason to refuse creating the student record.
        $curriculumId = null;

        if ($programId !== null) {
            $curriculum = (new CurriculumModel())->currentPublishedFor($programId);
            $curriculumId = $curriculum['curriculum_id'] ?? null;
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
            'curriculum_id'   => $curriculumId,
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

    public const STATUSES = ['active', 'inactive', 'graduated', 'dropped'];

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
                      c.curriculum_id, c.curriculum_code, c.version_number as curriculum_version, c.status as curriculum_status,
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
            ->select('s.*, u.id as user_id, u.username, u.first_name, u.middle_name, u.last_name, u.contact_number, u.section_id,
                      i.secret as email, p.program_id, p.program_code, p.program_name,
                      c.curriculum_code, c.curriculum_name, c.version_number as curriculum_version,
                      c.status as curriculum_status, c.base_curriculum_id as curriculum_base_id')
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

        // Every published version of this student's curriculum lineage —
        // lets the registrar pick a specific version, or move the student
        // to a newer one when they change.
        $student['available_curricula'] = $student['program_id']
            ? (new CurriculumModel())
                ->where('program_id', $student['program_id'])
                ->where('status', 'published')
                ->orderBy('version_number', 'DESC')
                ->findAll()
            : [];

        $student['curriculum_history'] = $this->db->table('student_curriculum_logs l')
            ->select('l.reason, l.created_at, u2.username as changed_by_username,
                      cf.curriculum_code as from_code, cf.version_number as from_version,
                      ct.curriculum_code as to_code, ct.version_number as to_version')
            ->join('users u2', 'u2.id = l.changed_by', 'left')
            ->join('curriculum cf', 'cf.curriculum_id = l.from_curriculum_id', 'left')
            ->join('curriculum ct', 'ct.curriculum_id = l.to_curriculum_id', 'left')
            ->where('l.student_id', $studentId)
            ->orderBy('l.created_at', 'DESC')
            ->get()
            ->getResultArray();

        $student['enrollment_history'] = $this->db->table('enrollments e')
            ->select('e.enrollment_id, e.subject_code, e.method, e.status, e.created_at,
                      sec.academic_year, sec.program, sec.year_level, sec.section')
            ->join('sections sec', 'sec.section_id = e.section_id')
            ->where('e.user_id', $student['user_id'])
            ->orderBy('sec.academic_year', 'DESC')
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

        return ['ok' => $db->transStatus() !== false];
    }

    /**
     * Assigns or reassigns a student's curriculum version — the "adviser-
     * approved equivalency mapping, itself audited" the handbook calls for
     * when a student's curriculum changes. Only published curricula may be
     * assigned (a draft isn't a real, stable snapshot yet).
     */
    public function assignCurriculum(int $studentId, int $curriculumId, string $reason, ?int $changedBy): array
    {
        $student = $this->find($studentId);

        if ($student === null) {
            return ['ok' => false, 'error' => 'Student not found.'];
        }

        $curriculum = (new CurriculumModel())->find($curriculumId);

        if ($curriculum === null || $curriculum['status'] !== 'published') {
            return ['ok' => false, 'error' => 'Only a published curriculum version may be assigned.'];
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

        return ['ok' => $db->transStatus() !== false];
    }
}
