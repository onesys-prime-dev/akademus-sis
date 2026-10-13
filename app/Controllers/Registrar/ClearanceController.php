<?php

namespace App\Controllers\Registrar;

use App\Controllers\BaseController;
use App\Libraries\ClearanceAuthorization;
use App\Libraries\RegistrarPortal;
use App\Models\GraduationClearanceModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Registrar's graduation clearance queue — per-office sign-off on each
 * case's items, then finalize to mark the student graduated. Registrar
 * can see every item (including Finance's 'accounting' one, read-only —
 * see registrar/clearance_detail.php's button visibility) but can only
 * act on the offices ClearanceAuthorization grants them — every office
 * except 'accounting', which is exclusively Finance's since the Finance
 * module's ownership handoff.
 */
class ClearanceController extends BaseController
{
    public function index()
    {
        return view('registrar/clearance_queue', [
            'pageTitle' => 'Graduation Clearance',
            'cases'     => (new GraduationClearanceModel())->queue(),
        ] + RegistrarPortal::shell('clearance'));
    }

    public function show($id)
    {
        $data = (new GraduationClearanceModel())->withItems((int) $id);

        if ($data['case'] === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('registrar/clearance_detail', [
            'pageTitle'    => 'Clearance — ' . $data['case']['username'],
            'clearanceCase' => $data['case'],
            'items'        => $data['items'],
            'fullyCleared' => (new GraduationClearanceModel())->isFullyCleared((int) $id),
            'authorizedOffices' => (new ClearanceAuthorization())->authorizedOfficeRoles((int) auth()->id()),
        ] + RegistrarPortal::shell('clearance'));
    }

    public function clearItem($clearanceId, $itemId)
    {
        $remarks = (string) $this->request->getPost('remarks');
        $offices = (new ClearanceAuthorization())->authorizedOfficeRoles((int) auth()->id());
        $result  = (new GraduationClearanceModel())->clearItem((int) $clearanceId, (int) $itemId, (int) auth()->id(), $remarks ?: null, $offices);

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->to('/registrar/clearance/' . $clearanceId)->with('message', 'Item cleared.');
    }

    public function denyItem($clearanceId, $itemId)
    {
        $remarks = (string) $this->request->getPost('remarks');

        if (trim($remarks) === '') {
            return redirect()->back()->with('errors', ['A reason is required to deny a clearance item.']);
        }

        $offices = (new ClearanceAuthorization())->authorizedOfficeRoles((int) auth()->id());
        $result  = (new GraduationClearanceModel())->denyItem((int) $clearanceId, (int) $itemId, (int) auth()->id(), $remarks, $offices);

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->to('/registrar/clearance/' . $clearanceId)->with('message', 'Item denied.');
    }

    public function finalize($id)
    {
        $result = (new GraduationClearanceModel())->finalizeGraduation((int) $id, (int) auth()->id());

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->to('/registrar/clearance')->with('message', 'Student marked graduated.');
    }
}
