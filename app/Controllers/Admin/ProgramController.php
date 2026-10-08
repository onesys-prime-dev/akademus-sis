<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditLogModel;
use App\Models\DepartmentModel;
use App\Models\ProgramModel;
use App\Models\SubDepartmentModel;
use CodeIgniter\HTTP\ResponseInterface;

class ProgramController extends BaseController
{
    protected ProgramModel $programModel;
    public function __construct()
    {
        $this->programModel = new ProgramModel();
    }

    public function index()
    {
        $programs = $this->programModel->findAll();

        return view('admin/programs/index', [
            'programs'    => $programs,
            'activeAdmin' => 'programs',
        ]);
    }

    public function create()
    {
        return view('admin/programs/create', [
            'activeAdmin'   => 'programs',
            'departments'   => (new SubDepartmentModel())->orderBy('name')->findAll(),
        ]);
    }

    public function store()
    {
        $rules = [
            // Previously this path had no uniqueness check at all — a
            // program created here (as opposed to via a Department's own
            // Add Program action, which does check) could silently
            // duplicate an existing code. Also previously had no Department
            // field whatsoever, which is how a Program like BSHM ended up
            // permanently orphaned (sub_department_id NULL) the moment it
            // was created through this form instead of a Department page.
            'program_code'      => 'required|max_length[20]|is_unique[programs.program_code]',
            'program_name'      => 'required|max_length[255]',
            'description'       => 'permit_empty',
            'sub_department_id' => 'required|is_natural_no_zero',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $subDeptId = (int) $this->request->getPost('sub_department_id');

        if ((new SubDepartmentModel())->find($subDeptId) === null) {
            return redirect()->back()->withInput()->with('errors', ['sub_department_id' => 'Department not found.']);
        }

        $name = trim($this->request->getPost('program_name'));
        $nameTaken = $this->programModel->where('sub_department_id', $subDeptId)->where('program_name', $name)->countAllResults() > 0;

        if ($nameTaken) {
            return redirect()->back()->withInput()->with('errors', ['program_name' => "A Program named \"{$name}\" already exists in this Department."]);
        }

        $this->programModel->insert([
            'program_code'      => strtoupper($this->request->getPost('program_code')),
            'program_name'      => $name,
            'description'       => $this->request->getPost('description'),
            'sub_department_id' => $subDeptId,
        ]);

        return redirect()
            ->to('/admin/programs')
            ->with('message', 'Program created successfully.');
    }

    public function edit($id)
    {
        $program = $this->programModel->find($id);

        if (! $program) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('admin/programs/edit', [
            'program'     => $program,
            'activeAdmin' => 'programs',
            'return'      => (string) $this->request->getGet('return'),
        ]);
    }

    public function update($id)
    {
        $rules = [
            'program_code' => 'required|max_length[20]',
            'program_name' => 'required|max_length[100]',
            'description'  => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $program = $this->programModel->find($id);

        if (! $program) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $newValues = [
            'program_code' => $this->request->getPost('program_code'),
            'program_name' => $this->request->getPost('program_name'),
            'description'  => $this->request->getPost('description'),
        ];

        $this->programModel->update($id, $newValues);

        // A rename is safe by construction now that sections resolve by
        // program_id (see SchedulingAuthorization::matchSection(),
        // SectionModel::forSubDepartment()) rather than matching this text
        // — nothing downstream needs to change, only the audit trail.
        (new AuditLogModel())->record(
            'program.updated',
            'program',
            $id,
            ['program_code' => $program['program_code'], 'program_name' => $program['program_name']],
            $newValues,
            null,
            (int) auth()->id(),
            'admin'
        );

        return redirect()
            ->to($this->returnUrl())
            ->with('message', 'Programs updated successfully.');
    }

    /**
     * This program editor is reachable both from the standalone Programs
     * list and from a department's detail page (Admin\SubDepartmentController
     * — the "Programs" panel on a department shows the same edit/toggle
     * actions). A `return` field (carried through edit()'s view and
     * toggleStatus()'s form) sends the admin back to whichever one they came
     * from instead of always bouncing to the generic list.
     */
    private function returnUrl(): string
    {
        $return = (string) $this->request->getPost('return');

        // Only ever redirect back into this same app — a bare hidden field
        // reflected straight into redirect()->to() would otherwise be an
        // open-redirect if someone crafted the POST themselves.
        if ($return !== '' && str_starts_with($return, site_url())) {
            return $return;
        }

        return '/admin/programs';
    }

    // public function delete($id)
    // {
    //     $program = $this->programModel->find($id);

    //     if (! $program) {
    //         throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    //     }
    //     $this->programModel->update($id, [
    //         'is_active' => 0
    //     ]);

    //     return redirect()
    //         ->to('/admin/programs')
    //         ->with('message', 'Programs deactivated successfully.');
    // }

    public function toggleStatus($id)
    {
        $program = $this->programModel->find($id);

        if (! $program) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $newStatus = $program['is_active'] ? 0 : 1;

        $this->programModel->update($id, [
            'is_active' => $newStatus
        ]);

        (new AuditLogModel())->record(
            'program.status_changed',
            'program',
            $id,
            ['is_active' => (int) $program['is_active']],
            ['is_active' => $newStatus],
            null,
            (int) auth()->id(),
            'admin'
        );

        return redirect()
            ->to($this->returnUrl())
            ->with(
                'message',
                $newStatus
                    ? 'program activated.'
                    : 'program deactivated.'
            );
    }

    /**
     * JSON: what Transfer Program (to a different Department) would affect —
     * read-only preview for the confirmation modal.
     */
    public function transferPreview($id)
    {
        $program = $this->programModel->find($id);

        if (! $program) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'Program not found.']);
        }

        $targetSubDeptId = (int) $this->request->getPost('sub_department_id');
        $targetDept       = (new SubDepartmentModel())->find($targetSubDeptId);

        if (! $targetDept) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'Department not found.']);
        }

        $currentDept = $program['sub_department_id'] !== null ? (new SubDepartmentModel())->find((int) $program['sub_department_id']) : null;

        return $this->response->setJSON([
            'ok'              => true,
            'from'            => $currentDept['name'] ?? '— (unassigned)',
            'to'              => $targetDept['name'],
            'same_as_current' => $targetSubDeptId === (int) ($program['sub_department_id'] ?? 0),
            'affected'        => (new DepartmentModel())->programTransferImpact((int) $id),
        ]);
    }

    /**
     * Moves a Program to a different Department. Curriculum/students/
     * sections/enrollments are untouched by design — they key off program_id
     * (or the program's own display name for the legacy section-matching
     * path), never off the department, so nothing needs to be rewritten;
     * chairperson/scheduling authorization for this program re-evaluates
     * automatically the next time it's checked (SchedulingAuthorization
     * reads live from programs.sub_department_id, nothing to resync).
     */
    public function transfer($id)
    {
        $program = $this->programModel->find($id);

        if (! $program) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $targetSubDeptId = (int) $this->request->getPost('sub_department_id');
        $targetDept       = (new SubDepartmentModel())->find($targetSubDeptId);
        $reason           = trim((string) $this->request->getPost('reason'));

        if (! $targetDept) {
            return redirect()->back()->with('errors', ['Department not found.']);
        }

        if ($reason === '') {
            return redirect()->back()->withInput()->with('errors', ['reason' => 'A reason is required for a transfer.']);
        }

        $oldSubDeptId = $program['sub_department_id'] !== null ? (int) $program['sub_department_id'] : null;

        if ($targetSubDeptId === $oldSubDeptId) {
            return redirect()->to($this->returnUrl())->with('message', "{$program['program_name']} is already under {$targetDept['name']}.");
        }

        $this->programModel->update($id, ['sub_department_id' => $targetSubDeptId]);

        (new AuditLogModel())->record(
            'program.transferred',
            'program',
            $id,
            ['sub_department_id' => $oldSubDeptId],
            ['sub_department_id' => $targetSubDeptId],
            $reason,
            (int) auth()->id(),
            'admin'
        );

        return redirect()
            ->to($this->returnUrl())
            ->with('message', "{$program['program_name']} transferred to {$targetDept['name']}.");
    }
}
