<?php

namespace Tests\Support\Traits;

/**
 * enrollments.user_id (and grades.user_id) are real foreign keys to users
 * since 2026-09-23-040000_AddMissingForeignKeys, so tests that enrol
 * "student 100" must first have a users row with that id.
 */
trait SeedsUsers
{
    /**
     * Insert a minimal users row for each id (idempotent per test run).
     */
    protected function seedUsers(int ...$ids): void
    {
        $users = db_connect()->table('users');

        foreach ($ids as $id) {
            if ($users->where('id', $id)->countAllResults() > 0) {
                continue;
            }

            db_connect()->table('users')->insert([
                'id'       => $id,
                'username' => 'testuser' . $id,
                'active'   => 1,
            ]);
        }
    }
}
