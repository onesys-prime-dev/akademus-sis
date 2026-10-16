<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Libraries\FinancePortal;

class SettingsController extends BaseController
{
    public function index()
    {
        return view('finance/settings/index', [
            'allowEnlistmentWithBalance' => (bool) (setting('Finance.allowEnlistmentWithBalance') ?? true),
        ] + FinancePortal::shell('settings'));
    }

    public function update()
    {
        $allow = $this->request->getPost('allow_enlistment_with_balance') === '1';

        setting('Finance.allowEnlistmentWithBalance', $allow);

        return redirect()
            ->to('/finance/settings')
            ->with('message', 'Finance settings updated.');
    }
}
