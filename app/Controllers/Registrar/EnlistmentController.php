<?php

namespace App\Controllers\Registrar;

use App\Controllers\BaseController;
use App\Libraries\RegistrarPortal;
use App\Models\StudentEnlistmentModel;

/**
 * Registrar's final step: dean_noted -> enrolled/conversion_failed. The
 * approve action is where StudentEnlistmentModel::registrarApprove()
 * actually converts cart items into real enrollments (see that method's
 * docblock) — institution-wide queue, no program scoping, same as
 * GradeChangeRequestModel::pendingRegistrarFinal().
 */
class EnlistmentController extends BaseController
{
    public function index()
    {
        return view('registrar/enlistments', [
            'pageTitle' => 'Enlistment Approvals',
            'requests'  => (new StudentEnlistmentModel())->pendingRegistrarApproval(),
        ] + RegistrarPortal::shell('enlistments'));
    }

    public function approve()
    {
        $registrarId = (int) auth()->id();
        $id          = (int) $this->request->getPost('id');
        $notes       = (string) $this->request->getPost('notes');

        $result = (new StudentEnlistmentModel())->registrarApprove($id, $registrarId, $notes ?: null);

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        $message = $result['failed'] === []
            ? 'Enlistment approved and converted to enrollments.'
            : 'Enlistment approved with some items failing conversion: ' . implode('; ', array_column($result['failed'], 'error'));

        return redirect()->to('/registrar/enlistments')->with('message', $message);
    }

    public function returnToStudent()
    {
        $registrarId = (int) auth()->id();
        $id          = (int) $this->request->getPost('id');
        $notes       = (string) $this->request->getPost('notes');

        $result = (new StudentEnlistmentModel())->returnToStudent($id, 'dean_noted', $registrarId, 'registrar', $notes);

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->to('/registrar/enlistments')->with('message', 'Cart returned to student.');
    }
}
