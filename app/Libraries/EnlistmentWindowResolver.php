<?php

namespace App\Libraries;

use App\Models\AcademicYearModel;
use App\Models\ProgramEnlistmentWindowModel;

/**
 * The single place that decides "is enlistment open for this term/program,"
 * so the mode setting (see docs/superpowers/specs/
 * 2026-11-19-enlistment-window-scoping-design.md) and the "the term window
 * is the outer bound" rule are never duplicated per call site.
 */
final class EnlistmentWindowResolver
{
    public function mode(): string
    {
        return setting('Enlistment.windowMode') ?? 'registrar_only';
    }

    public function isOpenFor(?int $ayId = null, ?int $programId = null): bool
    {
        $ayModel = new AcademicYearModel();

        if ($ayId === null) {
            $ayId = $ayModel->upcomingTerm()['ay_id'] ?? null;

            if ($ayId === null) {
                return false;
            }
        }

        if ($this->mode() !== 'program_scoped' || $programId === null) {
            return $ayModel->isEnlistmentOpen($ayId);
        }

        return $ayModel->isEnlistmentOpen($ayId) && (new ProgramEnlistmentWindowModel())->isOpen($ayId, $programId);
    }
}
