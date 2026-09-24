<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Append-only: rows can be written but never updated or deleted through the
 * application (handbook: audit logs are delete-restricted).
 */
class AuditLogModel extends Model
{
    protected $table      = 'audit_logs';
    protected $primaryKey = 'log_id';

    protected $allowedFields = [
        'actor_id', 'actor_role', 'action', 'entity_type', 'entity_id',
        'before_json', 'after_json', 'reason', 'ip_address', 'created_at',
    ];

    protected $useTimestamps = false;

    /**
     * @param array|null $before
     * @param array|null $after
     */
    public function record(string $action, string $entityType, $entityId, ?array $before, ?array $after, ?string $reason = null, ?int $actorId = null, ?string $actorRole = null): void
    {
        $ip = null;

        try {
            $ip = service('request')->getIPAddress();
        } catch (\Throwable $e) {
            // CLI / tests: no request.
        }

        $this->insert([
            'actor_id'    => $actorId,
            'actor_role'  => $actorRole,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId === null ? null : (string) $entityId,
            'before_json' => $before === null ? null : json_encode($before),
            'after_json'  => $after === null ? null : json_encode($after),
            'reason'      => $reason,
            'ip_address'  => $ip,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function update($id = null, $row = null): bool
    {
        throw new \LogicException('audit_logs is append-only.');
    }

    public function delete($id = null, bool $purge = false)
    {
        throw new \LogicException('audit_logs is append-only.');
    }
}
