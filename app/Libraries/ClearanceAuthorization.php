<?php

namespace App\Libraries;

use App\Models\ClearanceRequirementModel;

/**
 * Resolves which clearance_requirements.office_role values a user may
 * clear/deny — mirrors SchedulingAuthorization's shape. 'accounting' is
 * exclusively Finance's; every other office_role (Library, Guidance,
 * Department Chair, Registrar, and anything added later with no dedicated
 * portal) falls to Registrar's catch-all, resolved dynamically from the
 * catalog rather than hardcoded, so a newly-added office defaults to
 * Registrar until it earns its own portal.
 */
class ClearanceAuthorization
{
    /**
     * @return list<string>
     */
    public function authorizedOfficeRoles(int $userId): array
    {
        $db = db_connect();

        $groups = array_column(
            $db->table('auth_groups_users')->select('group')->where('user_id', $userId)->get()->getResultArray(),
            'group'
        );

        if (in_array('superadmin', $groups, true) || in_array('admin', $groups, true)) {
            return array_column((new ClearanceRequirementModel())->active(), 'office_role');
        }

        if (in_array('finance', $groups, true)) {
            return ['accounting'];
        }

        if (in_array('chairperson', $groups, true)) {
            return ['chairperson'];
        }

        if (in_array('registrar', $groups, true)) {
            return array_values(array_filter(
                array_column((new ClearanceRequirementModel())->active(), 'office_role'),
                static fn (string $role) => $role !== 'accounting'
            ));
        }

        return [];
    }

    public function canActOn(int $userId, string $officeRole): bool
    {
        return in_array($officeRole, $this->authorizedOfficeRoles($userId), true);
    }
}
