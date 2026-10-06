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

    /**
     * Newest-first, optionally narrowed by entity type / action prefix /
     * actor — the read side of the append-only trail, for an admin-facing
     * viewer. Action is matched as a prefix (e.g. 'grade' matches
     * 'grade.locked' and 'grade_change_request.approved') so a filter can
     * stay coarse without knowing every exact action string. Plain
     * limit/offset rather than CodeIgniter's Pager service — this is the
     * first paginated list in the app and doesn't need that machinery.
     */
    public function search(array $filters, int $page, int $perPage = 50): array
    {
        $apply = function () use ($filters) {
            $query = $this->orderBy('log_id', 'DESC');

            if (! empty($filters['entity_type'])) {
                $query = $query->where('entity_type', $filters['entity_type']);
            }

            if (! empty($filters['action'])) {
                $query = $query->like('action', $filters['action'], 'after');
            }

            if (! empty($filters['actor_id'])) {
                $query = $query->where('actor_id', (int) $filters['actor_id']);
            }

            return $query;
        };

        $total   = $apply()->countAllResults();
        $page    = max(1, $page);
        $rows    = $apply()->findAll($perPage, ($page - 1) * $perPage);

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    public function distinctEntityTypes(): array
    {
        return array_column(
            $this->select('entity_type')->distinct()->orderBy('entity_type')->findAll(),
            'entity_type'
        );
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
