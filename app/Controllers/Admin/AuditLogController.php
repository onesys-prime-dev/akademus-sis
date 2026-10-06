<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditLogModel;

/**
 * Read-only viewer for the append-only audit_logs trail (AuditLogModel).
 * Every domain that calls AuditLogModel::record() — grades, offerings,
 * subject substitutions, chairperson actions, section balancing — lands
 * here; this controller adds no new writes, only a way to see what's
 * already being recorded.
 */
class AuditLogController extends BaseController
{
    public function index()
    {
        $filters = [
            'entity_type' => (string) ($this->request->getGet('entity_type') ?? ''),
            'action'      => (string) ($this->request->getGet('action') ?? ''),
            'actor_id'    => (string) ($this->request->getGet('actor_id') ?? ''),
        ];

        $model  = new AuditLogModel();
        $page   = (int) ($this->request->getGet('page') ?? 1);
        $result = $model->search($filters, max(1, $page));

        $actorIds   = array_values(array_filter(array_unique(array_column($result['rows'], 'actor_id'))));
        $actorsById = [];

        if ($actorIds !== []) {
            foreach (db_connect()->table('users')->whereIn('id', $actorIds)->get()->getResultArray() as $actor) {
                $actorsById[(int) $actor['id']] = $actor['username'];
            }
        }

        $rows = array_map(static function (array $row) use ($actorsById) {
            $row['actor_name'] = $row['actor_id'] !== null ? ($actorsById[(int) $row['actor_id']] ?? ('#' . $row['actor_id'])) : 'System';

            return $row;
        }, $result['rows']);

        return view('admin/audit_logs/index', [
            'pageTitle'   => 'Audit Logs',
            'activeAdmin' => 'audit-logs',
            'rows'        => $rows,
            'total'       => $result['total'],
            'page'        => $result['page'],
            'lastPage'    => $result['last_page'],
            'filters'     => $filters,
            'entityTypes' => $model->distinctEntityTypes(),
        ]);
    }
}
