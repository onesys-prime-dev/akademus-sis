<?php

namespace App\Controllers\Dean;

use App\Controllers\BaseController;
use App\Libraries\DeanPortal;
use App\Libraries\SchedulingAuthorization;
use App\Models\DepartmentModel;
use App\Models\GradeModel;
use CodeIgniter\Shield\Entities\User;

/**
 * Dean's dashboard + profile — the thin entry points, mirroring
 * ChairpersonController's equivalent methods and Registrar\ProfileController.
 */
class DeanController extends BaseController
{
    public function index()
    {
        $collegeIds = (new SchedulingAuthorization())->authorizedDepartmentIds((int) auth()->id());

        $collegeNames = $collegeIds === []
            ? []
            : array_column(
                db_connect()->table('departments')->whereIn('dept_id', $collegeIds)->orderBy('name')->get()->getResultArray(),
                'name'
            );

        $programs   = self::programsOfColleges($collegeIds);
        $gradeModel = new GradeModel();

        return view('dean/dashboard', [
            'pageTitle'         => 'Dean Dashboard',
            'collegeName'       => count($collegeNames) === 1 ? $collegeNames[0] : (($collegeNames === []) ? null : implode(', ', $collegeNames)),
            'awaitingNoteCount' => $programs === [] ? 0 : count($gradeModel->recommendedForDean($programs)),
            'notedCount'        => $programs === [] ? 0 : count($gradeModel->notedClasses($programs)),
        ] + DeanPortal::shell('dashboard'));
    }

    public function profile()
    {
        $account = auth()->user();

        return view('dean/profile', [
            'pageTitle' => 'Profile',
            'account'   => [
                'username' => $account->username ?? '',
                'email'    => $account->getEmail() ?? '',
            ],
        ] + DeanPortal::shell('profile'));
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

        return redirect()->to('/dean/profile')->with('profile_message', 'Profile updated.');
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

        return redirect()->to('/dean/profile')->with('profile_message', 'Password changed.');
    }

    /**
     * Program display names across every college this dean is authorized
     * for — dean's counterpart to Chairperson\GradeApprovalController's
     * ownProgram() resolution, one level up the hierarchy (college ->
     * sub-departments -> programs, rather than sub-department -> programs
     * directly).
     *
     * @return list<string>
     */
    public static function programsOfColleges(array $collegeIds): array
    {
        if ($collegeIds === []) {
            return [];
        }

        $subDeptIds = array_column(
            db_connect()->table('sub_departments')->select('sub_dept_id')->whereIn('college_id', $collegeIds)->get()->getResultArray(),
            'sub_dept_id'
        );

        if ($subDeptIds === []) {
            return [];
        }

        $deptModel = new DepartmentModel();
        $programs  = [];

        foreach ($subDeptIds as $subDeptId) {
            $programs = array_merge($programs, $deptModel->programsOfSubDept((int) $subDeptId));
        }

        return array_values(array_unique($programs));
    }
}
