<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Libraries\ClearanceAuthorization;
use App\Libraries\FinancePortal;
use App\Models\GraduationClearanceModel;

/**
 * Finance's exclusive slice of graduation clearance — a cross-student
 * queue of only the 'accounting' office's pending items, distinct from
 * Registrar\ClearanceController's per-case detail view (which still shows
 * every item read-only, but can no longer act on this one — see
 * ClearanceAuthorization).
 */
class ClearanceController extends BaseController
{
    public function index()
    {
        $offices = (new ClearanceAuthorization())->authorizedOfficeRoles((int) auth()->id());

        return view('finance/clearance/index', [
            'pageTitle' => 'Graduation Clearance',
            'items'     => (new GraduationClearanceModel())->pendingForOffices($offices),
        ] + FinancePortal::shell('clearance'));
    }

    public function clearItem($clearanceId, $itemId)
    {
        $remarks = (string) $this->request->getPost('remarks');
        $offices = (new ClearanceAuthorization())->authorizedOfficeRoles((int) auth()->id());
        $result  = (new GraduationClearanceModel())->clearItem((int) $clearanceId, (int) $itemId, (int) auth()->id(), $remarks ?: null, $offices);

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->to('/finance/clearance')->with('message', 'Item cleared.');
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

        return redirect()->to('/finance/clearance')->with('message', 'Item denied.');
    }
}
