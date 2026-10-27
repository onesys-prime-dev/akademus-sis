<?php

namespace App\Controllers;

use App\Models\NotificationModel;

/**
 * Shared across every logged-in role (routed from both the chairperson and
 * faculty groups) — scoped entirely by auth()->id(), so it needs no
 * role-specific logic of its own.
 */
class NotificationController extends BaseController
{
    public function index()
    {
        // This is the console notification panel's JSON feed — the panel
        // always fetches it with X-Requested-With: XMLHttpRequest (see
        // layouts/admin_console.php's loadList()). A plain browser navigation
        // must never dump raw JSON: the common way to land here is Shield
        // sending a freshly logged-in user back to the URL that triggered the
        // login redirect (Auth::$redirects / beforeLoginUrl), which stranded
        // them on a JSON page right after signing in. Send those to the
        // role-aware dashboard instead (DashboardController dispatches by
        // group).
        if (! $this->request->isAJAX()) {
            return redirect()->to(site_url('dashboard'));
        }

        $userId = (int) auth()->id();
        $model  = new NotificationModel();

        $notifications = array_map(static function (array $n): array {
            // notify() call sites are inconsistent: some pass a bare
            // relative route ('student/tuition'), others already pass
            // site_url('...'). A bare one resolved client-side via
            // window.location.href = n.link is relative to the CURRENT
            // page's path, not the site root, so 'student/tuition' from
            // within /student/tuition itself becomes .../student/student/
            // tuition. Normalize to absolute here, the one place every
            // consumer reads 'link' from, instead of at each call site.
            if (! empty($n['link']) && ! preg_match('#^https?://#i', $n['link'])) {
                $n['link'] = site_url($n['link']);
            }

            return $n;
        }, $model->recentFor($userId));

        return $this->response->setJSON([
            'notifications' => $notifications,
            'unread'        => $model->unreadCount($userId),
        ]);
    }

    public function markRead()
    {
        $userId  = (int) auth()->id();
        $payload = $this->request->getJSON(true);
        $id      = (int) ($payload['id'] ?? 0);

        (new NotificationModel())->markRead($userId, $id);

        return $this->response->setJSON(['ok' => true]);
    }

    public function markAllRead()
    {
        $userId = (int) auth()->id();

        (new NotificationModel())->markAllRead($userId);

        return $this->response->setJSON(['ok' => true]);
    }
}
