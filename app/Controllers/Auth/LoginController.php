<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use CodeIgniter\Shield\Controllers\LoginController as ShieldLoginController;

/**
 * Shield's login controller, plus one thing: after a session expires the
 * "session ended" dialog opens this page with ?return=<page they were on>, so
 * signing back in lands them there instead of on whatever background request
 * (autosave, polling) happened to trip the login redirect.
 */
class LoginController extends ShieldLoginController
{
    public function loginView()
    {
        $return = $this->request->getGet('return');

        // Only ever send people back into this site.
        if (is_string($return) && str_starts_with($return, base_url())) {
            session()->setTempdata('beforeLoginUrl', $return, 300);
        }

        if ($this->request->getGet('expired') !== null && ! auth()->loggedIn()) {
            // The login page shows this in a modal (see views/auth/login.php).
            session()->setFlashdata('sessionEnded', true);
        }

        return parent::loginView();
    }
}
