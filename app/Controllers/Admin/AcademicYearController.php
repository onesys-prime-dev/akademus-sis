<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RegistrarPortal;
use App\Models\AcademicYearModel;

class AcademicYearController extends BaseController
{
    protected AcademicYearModel $ayModel;

    public function __construct()
    {
        $this->ayModel = new AcademicYearModel();
    }

    /**
     * Served under both /admin/academic-years (full CRUD) and
     * /registrar/academic-calendar (view + window editing only, via the
     * $academicCalendarRoutes closure in Routes.php) — mirrors
     * StudentRecordController's isRegistrarPortal()/shell() pattern exactly.
     */
    private function isRegistrarPortal(): bool
    {
        return service('request')->getUri()->getSegment(1) === 'registrar';
    }

    private function shell(string $active): array
    {
        return $this->isRegistrarPortal()
            ? RegistrarPortal::shell($active)
            : ['activeAdmin' => $active, 'basePath' => 'admin'];
    }

    public function index()
    {
        $years = $this->ayModel->orderBy('label', 'desc')->orderBy('semester', 'asc')->findAll();

        return view('admin/academic_years/index', [
            'pageTitle' => 'Academic Years',
            'years'     => $years,
        ] + $this->shell($this->isRegistrarPortal() ? 'academic-calendar' : 'academic-years'));
    }

    public function store()
    {
        $rules = [
            'label'      => 'required|max_length[20]',
            'semester'   => 'required|in_list[1st,2nd,Summer]',
            'start_date' => 'permit_empty|valid_date',
            'end_date'   => 'permit_empty|valid_date',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->ayModel->insert([
            'label'      => $this->request->getPost('label'),
            'semester'   => $this->request->getPost('semester'),
            'start_date' => $this->request->getPost('start_date') ?: null,
            'end_date'   => $this->request->getPost('end_date') ?: null,
        ]);

        return redirect()
            ->to('/admin/academic-years')
            ->with('message', 'Academic year added successfully.');
    }

    /**
     * Sets or clears this term's grading/registration sub-windows —
     * Registrar\AcademicCalendarController's thin wrapper reuses this same
     * action rather than duplicating it, since the underlying data and
     * validation are identical regardless of which console edits it.
     */
    public function updateWindow($id)
    {
        $ay = $this->ayModel->find($id);

        if (! $ay) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'grading_opens_at'       => 'permit_empty|valid_date',
            'grading_closes_at'      => 'permit_empty|valid_date',
            'registration_opens_at'  => 'permit_empty|valid_date',
            'registration_closes_at' => 'permit_empty|valid_date',
            'enlistment_opens_at'    => 'permit_empty|valid_date',
            'enlistment_closes_at'   => 'permit_empty|valid_date',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->ayModel->update($id, [
            'grading_opens_at'       => $this->request->getPost('grading_opens_at') ?: null,
            'grading_closes_at'      => $this->request->getPost('grading_closes_at') ?: null,
            'registration_opens_at'  => $this->request->getPost('registration_opens_at') ?: null,
            'registration_closes_at' => $this->request->getPost('registration_closes_at') ?: null,
            'enlistment_opens_at'    => $this->request->getPost('enlistment_opens_at') ?: null,
            'enlistment_closes_at'   => $this->request->getPost('enlistment_closes_at') ?: null,
        ]);

        return redirect()->back()->with('message', "{$ay['label']} ({$ay['semester']}) window updated.");
    }

    public function setActive($id)
    {
        $ay = $this->ayModel->find($id);

        if (! $ay) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $this->ayModel->setActive($id);

        return redirect()
            ->to('/admin/academic-years')
            ->with('message', "{$ay['label']} ({$ay['semester']}) set as active term.");
    }

    /**
     * Manual trigger for AcademicYearModel::syncActiveToDate() — flips the
     * active term to whichever row's date range covers today, if it
     * differs. The existing setActive() action above stays as an explicit
     * manual override; this is the date-driven alternative, not a
     * replacement.
     */
    public function syncActive()
    {
        $result = $this->ayModel->syncActiveToDate();

        if (! $result['changed']) {
            $message = $result['to'] === null
                ? ($result['reason'] ?? 'No term covers today.')
                : "Already correct — {$result['to']['label']} ({$result['to']['semester']}) already matches today.";

            return redirect()->to('/admin/academic-years')->with('message', $message);
        }

        $to = $result['to'];

        return redirect()
            ->to('/admin/academic-years')
            ->with('message', "Active term updated to {$to['label']} ({$to['semester']}) to match today's date.");
    }
}
