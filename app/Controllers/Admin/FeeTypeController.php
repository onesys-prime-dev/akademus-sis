<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RegistrarPortal;
use App\Models\FeeTypeModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * The dynamic fee catalog — add a fee once (name + category), it becomes
 * available to price per program on every program's rate sheet
 * (Admin\TuitionController::edit) immediately, no code change. Deactivating
 * a fee type here stops it being charged on the NEXT recompute for every
 * program, without deleting the historical assessment_items that already
 * reference it (fee_type_id is nullable there specifically for this).
 */
class FeeTypeController extends BaseController
{
    private function isRegistrarPortal(): bool
    {
        return service('request')->getUri()->getSegment(1) === 'registrar';
    }

    private function baseUrl(): string
    {
        return '/' . ($this->isRegistrarPortal() ? 'registrar' : 'admin') . '/fee-types';
    }

    private function shell(): array
    {
        return $this->isRegistrarPortal()
            ? RegistrarPortal::shell('fee-types')
            : ['activeAdmin' => 'fee-types', 'basePath' => 'admin'];
    }

    public function index()
    {
        $feeTypes = (new FeeTypeModel())->orderBy('category')->orderBy('sort_order')->orderBy('name')->findAll();

        return view('admin/fee_types/index', [
            'pageTitle' => 'Fee Types',
            'feeTypes'  => $feeTypes,
            'categories' => FeeTypeModel::CATEGORIES,
        ] + $this->shell());
    }

    public function store()
    {
        $rules = [
            'category'   => 'required|in_list[' . implode(',', FeeTypeModel::CATEGORIES) . ']',
            'code'       => 'required|max_length[30]|alpha_dash',
            'name'       => 'required|max_length[100]',
            'sort_order' => 'permit_empty|integer',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to($this->baseUrl())->with('errors', $this->validator->getErrors());
        }

        (new FeeTypeModel())->insert([
            'category'   => $this->request->getPost('category'),
            'code'       => strtoupper((string) $this->request->getPost('code')),
            'name'       => $this->request->getPost('name'),
            'sort_order' => (int) ($this->request->getPost('sort_order') ?: 0),
        ]);

        return redirect()->to($this->baseUrl())->with('message', 'Fee type added.');
    }

    public function update(int $id)
    {
        $rules = [
            'category'   => 'required|in_list[' . implode(',', FeeTypeModel::CATEGORIES) . ']',
            'name'       => 'required|max_length[100]',
            'sort_order' => 'permit_empty|integer',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to($this->baseUrl())->with('errors', $this->validator->getErrors());
        }

        $feeTypes = new FeeTypeModel();

        if ($feeTypes->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $feeTypes->update($id, [
            'category'   => $this->request->getPost('category'),
            'name'       => $this->request->getPost('name'),
            'sort_order' => (int) ($this->request->getPost('sort_order') ?: 0),
        ]);

        return redirect()->to($this->baseUrl())->with('message', 'Fee type updated.');
    }

    public function toggleActive(int $id)
    {
        $feeTypes = new FeeTypeModel();
        $feeType  = $feeTypes->find($id);

        if ($feeType === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $feeTypes->update($id, ['is_active' => $feeType['is_active'] ? 0 : 1]);

        return redirect()->to($this->baseUrl())->with('message', $feeType['is_active'] ? 'Fee type deactivated.' : 'Fee type activated.');
    }
}
