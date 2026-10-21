<?php

namespace App\Libraries;

use App\Models\GraduationClearanceModel;
use App\Models\PaymentProofModel;

/**
 * Live "needs your action" counts for every Finance sidebar item that has
 * one — mirrors ChairpersonBadgeCounts: computed fresh on every request,
 * read by FinancePortal::shell() so every Finance page's sidebar badge
 * stays in sync without each view recomputing it independently.
 */
class FinanceBadgeCounts
{
    /** @return array<string, int> keyed by the same key FinancePortal::shell() uses for that item */
    public function forUser(int $userId): array
    {
        return [
            'payment-proofs' => (new PaymentProofModel())->where('status', 'pending')->countAllResults(),
            'clearance'      => count((new GraduationClearanceModel())->pendingForOffices(
                (new ClearanceAuthorization())->authorizedOfficeRoles($userId)
            )),
        ];
    }
}
