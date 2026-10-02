<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditLogModel;
use App\Models\SubjectModel;
use App\Models\SubjectSubstitutionModel;

/**
 * Admin-only configuration of explicit subject substitution/equivalency
 * rules (Step 8) — gated by the same `group:admin` route filter as
 * Admin\SubjectController/Admin\CurriculumController, no new permission or
 * role invented. Every mutation is audited; the catalog rows themselves are
 * never hard-deleted, only soft-deactivated (and reactivatable), matching
 * the existing is_active convention across subjects/curriculum.
 */
class SubjectSubstitutionController extends BaseController
{
    public function index()
    {
        return view('admin/subject_substitutions/index', [
            'relationships' => (new SubjectSubstitutionModel())->listWithSubjects(),
            'subjects'      => (new SubjectModel())->where('is_active', 1)->orderBy('subject_code')->findAll(),
        ]);
    }

    public function store()
    {
        if (! $this->validate([
            'required_subject_id'   => 'required|is_natural_no_zero',
            'substitute_subject_id' => 'required|is_natural_no_zero',
        ])) {
            return redirect()->to('/admin/subject-substitutions')->with('errors', ['Choose both a required subject and a substitute subject.']);
        }

        $requiredId   = (int) $this->request->getPost('required_subject_id');
        $substituteId = (int) $this->request->getPost('substitute_subject_id');
        $notes        = $this->request->getPost('notes') ?: null;

        $result = (new SubjectSubstitutionModel())->createOrReactivate($requiredId, $substituteId, $notes, (int) auth()->id());

        if (! $result['ok']) {
            return redirect()->to('/admin/subject-substitutions')->with('errors', [$result['error']]);
        }

        $after = ['required_subject_id' => $requiredId, 'substitute_subject_id' => $substituteId, 'notes' => $notes];

        if ($result['reactivated']) {
            $this->audit('subject_substitution.reactivated', $result['id'], null, $after);

            return redirect()->to('/admin/subject-substitutions')->with('message', 'Relationship reactivated.');
        }

        $this->audit('subject_substitution.created', $result['id'], null, $after);

        return redirect()->to('/admin/subject-substitutions')->with('message', 'Relationship created — the substitute subject will now satisfy the required subject.');
    }

    public function deactivate($id)
    {
        $model = new SubjectSubstitutionModel();
        $before = $model->find((int) $id);

        if ($before === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $result = $model->deactivate((int) $id);

        if (! $result['ok']) {
            return redirect()->to('/admin/subject-substitutions')->with('errors', [$result['error']]);
        }

        $this->audit('subject_substitution.deactivated', (int) $id, $before, null);

        return redirect()->to('/admin/subject-substitutions')->with('message', 'Relationship deactivated.');
    }

    private function audit(string $action, int $id, ?array $before, ?array $after): void
    {
        (new AuditLogModel())->record($action, 'subject_substitution', $id, $before, $after, null, (int) auth()->id(), 'admin');
    }
}
