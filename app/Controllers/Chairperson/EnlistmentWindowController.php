<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;

/**
 * A chairperson's narrow slice of academic-calendar control: only the
 * enlistment window on the currently-active term, not the full grading/
 * registration scope Admin\AcademicYearController::updateWindow() exposes
 * to admin/registrar. Satisfies the spec's "Department Chairs or
 * Registrars may open/close the enlistment window" without granting chairs
 * calendar.manage.
 */
class EnlistmentWindowController extends BaseController
{
    public function edit()
    {
        $ayModel = new AcademicYearModel();
        $ay      = $ayModel->activeRow();

        return view('chairperson/enlistment_window', [
            'pageTitle'     => 'Enlistment Window',
            'user'          => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => 'Chairperson'],
            'ay'            => $ay,
            'isOpen'        => $ay !== null ? $ayModel->isEnlistmentOpen((int) $ay['ay_id']) : true,
        ]);
    }

    public function update()
    {
        $ayModel = new AcademicYearModel();
        $ay      = $ayModel->activeRow();

        if ($ay === null) {
            return redirect()->to('/chairperson/enlistment-window')->with('errors', ['No active academic term is set.']);
        }

        $rules = [
            'enlistment_opens_at'  => 'permit_empty|valid_date',
            'enlistment_closes_at' => 'permit_empty|valid_date',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $ayModel->update((int) $ay['ay_id'], [
            'enlistment_opens_at'  => $this->request->getPost('enlistment_opens_at') ?: null,
            'enlistment_closes_at' => $this->request->getPost('enlistment_closes_at') ?: null,
        ]);

        return redirect()->to('/chairperson/enlistment-window')->with('message', "{$ay['label']} ({$ay['semester']}) enlistment window updated.");
    }
}
