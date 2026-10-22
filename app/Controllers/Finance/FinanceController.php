<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Libraries\FinanceBadgeCounts;
use App\Libraries\FinancePortal;
use App\Models\PaymentProofModel;
use App\Models\StudentPaymentModel;
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

        $monthCollections = (float) ($db->table('student_payments')
            ->selectSum('amount')
            ->where('YEAR(payment_date)', date('Y'))
            ->where('MONTH(payment_date)', date('n'))
            ->get()
            ->getRowArray()['amount'] ?? 0);

        $pendingProofs = count((new PaymentProofModel())->pendingQueue());

        $highBalanceCount = (int) ($db->table('tuition_assessments')
            ->where('balance >', 0)
            ->countAllResults());

        $badges = (new FinanceBadgeCounts())->forUser((int) auth()->id());

        return view('finance/dashboard', [
            'pageTitle'         => 'Finance Dashboard',
            'todayCollections'  => $todayCollections,
            'monthCollections'  => $monthCollections,
            'pendingProofs'     => $pendingProofs,
            'highBalanceCount'  => $highBalanceCount,
            'pendingClearance'  => $badges['clearance'] ?? 0,
            'recentPayments'    => (new StudentPaymentModel())->recentAcrossStudents(8),
        ] + FinancePortal::shell('dashboard'));
    }

    public function profile()
    {
        $account   = auth()->user();
        $photoMime = db_connect()->table('users')->select('photo_mime')->where('id', (int) auth()->id())->get()->getRowArray()['photo_mime'] ?? null;

        return view('finance/profile', [
            'pageTitle' => 'Profile',
            'account'   => [
                'id'         => (int) auth()->id(),
                'username'   => $account->username ?? '',
                'email'      => $account->getEmail() ?? '',
                'photo_mime' => $photoMime,
            ],
        ] + FinancePortal::shell('profile'));
    }

    /** Same contract as StudentController::uploadPhoto() — generic users.photo_blob/photo_mime storage. */
    public function uploadPhoto()
    {
        $userId = (int) auth()->id();

        $rules = [
            'photo' => 'uploaded[photo]|max_size[photo,2048]|ext_in[photo,jpg,jpeg,png,webp]|mime_in[photo,image/jpeg,image/png,image/webp]|is_image[photo]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/finance/profile')->with('profile_error', implode(' ', $this->validator->getErrors()));
        }

        $file = $this->request->getFile('photo');

        if ($file === null || ! $file->isValid()) {
            return redirect()->to('/finance/profile')->with('profile_error', 'Upload failed. Please try again.');
        }

        db_connect()->table('users')->where('id', $userId)->update([
            'photo_blob' => file_get_contents($file->getTempName()),
            'photo_mime' => $file->getClientMimeType(),
        ]);

        return redirect()->to('/finance/profile')->with('profile_message', 'Profile photo updated.');
    }

    /** Same contract as StudentController::removePhoto(). */
    public function removePhoto()
    {
        db_connect()->table('users')->where('id', (int) auth()->id())->update([
            'photo_blob' => null,
            'photo_mime' => null,
        ]);

        return redirect()->to('/finance/profile')->with('profile_message', 'Profile photo removed.');
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
