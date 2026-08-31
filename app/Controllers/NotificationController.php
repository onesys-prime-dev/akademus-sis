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
        $userId = (int) auth()->id();
        $model  = new NotificationModel();

        return $this->response->setJSON([
            'notifications' => $model->recentFor($userId),
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
