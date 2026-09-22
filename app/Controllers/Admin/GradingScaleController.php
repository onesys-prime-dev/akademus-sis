<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\GradingScaleModel;

/**
 * Admin-configurable grading scale — mirrors Admin\AcademicYearController's
 * "list, add, set one active" shape exactly (see AcademicYearModel::setActive()).
 * Every grade snapshots grading_scale_id at entry time, so switching the
 * active scale here never reinterprets a grade already on record.
 */
class GradingScaleController extends BaseController
{
    protected GradingScaleModel $scaleModel;

    public function __construct()
    {
        $this->scaleModel = new GradingScaleModel();
    }

    public function index()
    {
        $scales = $this->scaleModel->orderBy('is_active', 'desc')->orderBy('name', 'asc')->findAll();

        return view('admin/grading_scales/index', [
            'pageTitle'   => 'Grading Scales',
            'activeAdmin' => 'grading-scales',
            'scales'      => $scales,
        ]);
    }

    public function store()
    {
        $rules = [
            'name' => 'required|max_length[60]',
            'type' => 'required|in_list[numeric_gwa,percentage,letter]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $type = $this->request->getPost('type');
        $data = [
            'name' => $this->request->getPost('name'),
            'type' => $type,
        ];

        if (in_array($type, ['numeric_gwa', 'percentage'], true)) {
            $min     = $this->request->getPost('min_value');
            $max     = $this->request->getPost('max_value');
            $passing = $this->request->getPost('passing_value');

            if (! is_numeric($min) || ! is_numeric($max) || ! is_numeric($passing)) {
                return redirect()->back()->withInput()->with('errors', ['general' => 'Min, max, and passing value are all required for a numeric scale.']);
            }

            $data['min_value']       = $min;
            $data['max_value']       = $max;
            $data['passing_value']   = $passing;
            $data['is_lower_better'] = $type === 'numeric_gwa' ? 1 : 0;
        } else {
            $letterOptions  = trim((string) $this->request->getPost('letter_options'));
            $failingLetters = trim((string) $this->request->getPost('failing_letters'));

            if ($letterOptions === '' || $failingLetters === '') {
                return redirect()->back()->withInput()->with('errors', ['general' => 'Letter options and failing letters are both required for a letter scale.']);
            }

            $data['letter_options']  = $letterOptions;
            $data['failing_letters'] = $failingLetters;
        }

        $this->scaleModel->insert($data);

        return redirect()->to('/admin/grading-scales')->with('message', 'Grading scale added successfully.');
    }

    public function setActive($id)
    {
        $scale = $this->scaleModel->find($id);

        if (! $scale) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $this->scaleModel->setActive($id);

        return redirect()->to('/admin/grading-scales')->with('message', "{$scale['name']} set as the active grading scale.");
    }
}
