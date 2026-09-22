<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\CurriculumModel;
use App\Models\ProgramModel;

class CurriculumController extends BaseController
{
    protected CurriculumModel $curriculumModel;
    protected ProgramModel $programModel;

    public function __construct()
    {
        $this->curriculumModel = new CurriculumModel();
        $this->programModel = new ProgramModel();
    }

    public function index()
    {
        $curriculum = $this->curriculumModel
            ->select('curriculum.*, programs.program_name')
            ->join('programs', 'programs.program_id = curriculum.program_id')
            ->findAll();

        return view('admin/curriculum/index', [
            'curriculum'  => $curriculum,
            'activeAdmin' => 'curriculum',
        ]);
    }

    public function create()
    {
        $programs = $this->programModel
            ->where('is_active', 1)
            ->findAll();

        return view('admin/curriculum/create', [
            'programs'    => $programs,
            'activeAdmin' => 'curriculum',
        ]);
    }

    public function store()
    {
        $rules = [
            'program_id'      => 'required|integer',
            'curriculum_code' => 'required|max_length[50]|is_unique[curriculum.curriculum_code]',
            'curriculum_name' => 'required|max_length[255]',
            'academic_year'   => 'required|regex_match[/^\d{4}-\d{4}$/]'
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->curriculumModel->insert([
            'program_id'      => $this->request->getPost('program_id'),
            'curriculum_code' => $this->request->getPost('curriculum_code'),
            'curriculum_name' => $this->request->getPost('curriculum_name'),
            'academic_year'   => $this->request->getPost('academic_year'),
            'description'     => $this->request->getPost('description'),
            'version_number'  => 1,
            'status'          => 'draft',
        ]);

        $newId = $this->curriculumModel->getInsertID();

        // A brand-new curriculum is the root of its own version lineage.
        $this->curriculumModel->update($newId, ['base_curriculum_id' => $newId]);

        return redirect()
            ->to('/admin/curriculum')
            ->with('message', 'Curriculum created successfully as a draft. Publish it once its subjects are finalized.');
    }

    public function edit($id)
    {
        $curriculum = $this->curriculumModel->find($id);
        $programs = $this->programModel->where('is_active', 1)->findAll();

        if (! $curriculum) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('admin/curriculum/edit', [
            'curriculum'  => $curriculum,
            'programs'    => $programs,
            'editable'    => $this->curriculumModel->isEditable($curriculum),
            'versions'    => $this->curriculumModel->versionsOf((int) $id),
            'activeAdmin' => 'curriculum',
        ]);
    }

    public function update($id)
    {
        $rules = [
            'program_id'      => 'required|integer',
            'curriculum_code' => 'required|max_length[50]|is_unique[curriculum.curriculum_code,curriculum_id,{curriculum_id}]',
            'curriculum_name' => 'required|max_length[255]',
            'academic_year'   => 'required|regex_match[/^\d{4}-\d{4}$/]',
            'description'     => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $curriculum = $this->curriculumModel->find($id);

        if (! $curriculum) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if (! $this->curriculumModel->isEditable($curriculum)) {
            return redirect()
                ->back()
                ->with('errors', ['Published curricula are immutable — create a new version to make changes.']);
        }

        $duplicate = $this->curriculumModel
            ->where('curriculum_code', $this->request->getPost('curriculum_code'))
            ->where('curriculum_id !=', $id)
            ->first();

        if ($duplicate) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', [
                    'curriculum_code' => 'Curriculum code already exists.'
                ]);
        }

        $this->curriculumModel->update($id, [
            'program_id'      => $this->request->getPost('program_id'),
            'curriculum_code' => $this->request->getPost('curriculum_code'),
            'curriculum_name' => $this->request->getPost('curriculum_name'),
            'academic_year'   => $this->request->getPost('academic_year'),
            'description'     => $this->request->getPost('description'),
        ]);

        return redirect()
            ->to('/admin/curriculum')
            ->with('message', 'Curriculum updated successfully.');
    }

    public function toggleStatus($id)
    {
        $curriculum = $this->curriculumModel->find($id);

        if (! $curriculum) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $newStatus = $curriculum['is_active'] ? 0 : 1;

        $this->curriculumModel->update($id, [
            'is_active' => $newStatus
        ]);

        return redirect()
            ->to('/admin/curriculum')
            ->with(
                'message',
                $newStatus
                    ? 'curriculum is activated successfully'
                    : 'curriculum is deactivated successfully'
            );
    }

    /**
     * Locks a draft curriculum as an immutable, versioned snapshot and marks
     * it the current version for its program (new promotions/assignments
     * default to it). Older published versions of the same lineage remain
     * published and valid for whichever students are already on them.
     */
    public function publish($id)
    {
        $result = $this->curriculumModel->publish((int) $id);

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error'] ?? 'Could not publish curriculum.']);
        }

        return redirect()
            ->to('/admin/curriculum')
            ->with('message', 'Curriculum published. It is now immutable and set as the current version for its program.');
    }

    /**
     * Creates a new draft version of a curriculum — a full copy of its
     * subject list under a fresh curriculum_id — so it can be edited without
     * touching the (possibly already-published, already-assigned-to-students)
     * source version.
     */
    public function newVersion($id)
    {
        $result = $this->curriculumModel->createNewVersion((int) $id);

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error'] ?? 'Could not create a new version.']);
        }

        return redirect()
            ->to('/admin/curriculum/edit/' . $result['curriculum']['curriculum_id'])
            ->with('message', 'New draft version created from this curriculum. Edit it freely — the source version is untouched.');
    }
}
