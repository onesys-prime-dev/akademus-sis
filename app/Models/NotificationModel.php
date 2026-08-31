<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table      = 'notifications';
    protected $primaryKey = 'id';

    protected $allowedFields = ['user_id', 'type', 'message', 'link', 'is_read'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    public function notify(int $userId, string $type, string $message, ?string $link = null): void
    {
        $this->insert([
            'user_id' => $userId,
            'type'    => $type,
            'message' => $message,
            'link'    => $link,
            'is_read' => 0,
        ]);
    }

    public function unreadCount(int $userId): int
    {
        return $this->where('user_id', $userId)->where('is_read', 0)->countAllResults();
    }

    public function recentFor(int $userId, int $limit = 20): array
    {
        return $this->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function markRead(int $userId, int $id): void
    {
        $this->where('id', $id)->where('user_id', $userId)->set(['is_read' => 1])->update();
    }

    public function markAllRead(int $userId): void
    {
        $this->where('user_id', $userId)->where('is_read', 0)->set(['is_read' => 1])->update();
    }
}
