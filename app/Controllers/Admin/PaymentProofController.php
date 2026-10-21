<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\FinancePortal;
use App\Libraries\RegistrarPortal;
use App\Models\PaymentProofModel;
use App\Services\TuitionAssessmentService;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Staff review queue for student-uploaded proof-of-payment. Approving calls
 * TuitionAssessmentService::approveProof(), which writes the real ledger
 * row via the same recordPayment() path a staff-recorded cash payment uses
 * — the queue itself never touches the balance directly.
 */
class PaymentProofController extends BaseController
{
    private function portal(): string
    {
        $segment = service('request')->getUri()->getSegment(1);

        return in_array($segment, ['registrar', 'finance'], true) ? $segment : 'admin';
    }

    private function shell(): array
    {
        return match ($this->portal()) {
            'registrar' => RegistrarPortal::shell('payment-proofs'),
            'finance'   => FinancePortal::shell('payment-proofs'),
            default     => ['activeAdmin' => 'payment-proofs', 'basePath' => 'admin'],
        };
    }

    public function index()
    {
        return view('admin/payment_proofs/index', [
            'pageTitle' => 'Payment Proofs',
            'proofs'    => (new PaymentProofModel())->pendingQueue(),
        ] + $this->shell());
    }

    public function approve(int $id)
    {
        $proofs = new PaymentProofModel();

        if ($proofs->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = (new TuitionAssessmentService())->approveProof($id, (int) auth()->id());

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->back()->with('message', 'Proof approved and payment recorded.');
    }

    public function reject(int $id)
    {
        if (! $this->validate(['cashier_remarks' => 'required|min_length[3]'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $proofs = new PaymentProofModel();

        if ($proofs->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = (new TuitionAssessmentService())->rejectProof($id, (int) auth()->id(), (string) $this->request->getPost('cashier_remarks'));

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->back()->with('message', 'Proof rejected.');
    }
}
