<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Models\OfficialReceiptModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Official Receipt voiding — downloads go through the shared
 * StudentController::viewReceipt() (ownership-or-staff, same shape as
 * payment-proof downloads); this controller only ever flags one voided.
 */
class ReceiptController extends BaseController
{
    public function void($id)
    {
        if (! $this->validate(['reason' => 'required|min_length[5]'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $result = (new OfficialReceiptModel())->void((int) $id, (string) $this->request->getPost('reason'), (int) auth()->id());

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->back()->with('message', 'Receipt voided.');
    }
}
