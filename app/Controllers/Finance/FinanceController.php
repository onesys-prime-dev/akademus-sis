<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Libraries\FinancePortal;
use App\Models\PaymentProofModel;
use CodeIgniter\Shield\Entities\User;

/**
 * Finance's dashboard + profile — the thin entry points, mirroring
 * DeanController/RegistrarController's equivalent methods.
 */
class FinanceController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        $todayCollections = (float) ($db->table('student_payments')
            ->selectSum('amount')
            ->where('DATE(payment_date)', date('Y-m-d'))
            ->get()
            ->getRowArray()['amount'] ?? 0);

        $pendingProofs = count((new PaymentProofModel())->pendingQueue());

        $highBalanceCount = (int) ($db->table('tuition_assessments')
            ->where('balance >', 0)
            ->countAllResults());

        return view('finance/dashboard', [
            'pageTitle'         => 'Finance Dashboard',
            'todayCollections'  => $todayCollections,
            'pendingProofs'     => $pendingProofs,
            'highBalanceCount'  => $highBalanceCount,
        ] + FinancePortal::shell('dashboard'));
    }

    public function profile()
    {
        $account = auth()->user();

        return view('finance/profile', [
            'pageTitle' => 'Profile',
            'account'   => [
                'username' => $account->username ?? '',
                'email'    => $account->getEmail() ?? '',
            ],
        ] + FinancePortal::shell('profile'));
    }

    public function updateProfile()
    {
        $username = trim((string) $this->request->getPost('username'));

        if ($username === '' || mb_strlen($username) > 100) {
            return redirect()->back()->with('profile_error', 'Name is required (max 100 characters).');
        }

        if (preg_match('/[<>"`\\]/', $username) === 1) {
            return redirect()->back()->with('profile_error', 'Name cannot contain < > " ` or \ characters.');
        }

        $users = auth()->getProvider();
        $user  = auth()->user();
        $user->username = $username;
        $users->save($user);

        return redirect()->to('/finance/profile')->with('profile_message', 'Profile updated.');
    }

    public function updatePassword()
    {
        $current = (string) $this->request->getPost('current_password');
        $new     = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('confirm_password');

        if ($current === '' || $new === '' || $confirm === '') {
            return redirect()->back()->with('profile_error', 'All password fields are required.');
        }

        if ($new !== $confirm) {
            return redirect()->back()->with('profile_error', 'New password and confirmation do not match.');
        }

        /** @var User $account */
        $account = auth()->user();

        if (! auth()->check(['email' => $account->getEmail(), 'password' => $current])->isOK()) {
            return redirect()->back()->with('profile_error', 'Current password is incorrect.');
        }

        $account->password = $new;

        try {
            auth()->getProvider()->save($account);
        } catch (\Throwable $e) {
            return redirect()->back()->with('profile_error', 'That password was rejected. Use at least 8 characters that are not easy to guess.');
        }

        return redirect()->to('/finance/profile')->with('profile_message', 'Password changed.');
    }
}
