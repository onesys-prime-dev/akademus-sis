<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RegistrarPortal;
use App\Models\ProgramModel;
use App\Models\TuitionRateModel;

/**
 * The tuition price list (per program per academic year) registrar/admin
 * sets — without a row here for a program+year, TuitionAssessmentService
 * leaves every student in that program "unassessed" rather than guessing.
 * Served under both /admin/tuition-rates and /registrar/tuition-rates, same
 * convention as Admin\StudentRecordController.
 */
class TuitionController extends BaseController
{
    private function isRegistrarPortal(): bool
    {
        return service('request')->getUri()->getSegment(1) === 'registrar';
    }

    private function baseUrl(): string
    {
        return '/' . ($this->isRegistrarPortal() ? 'registrar' : 'admin') . '/tuition-rates';
    }

    private function shell(): array
    {
        return $this->isRegistrarPortal()
            ? RegistrarPortal::shell('tuition-rates')
            : ['activeAdmin' => 'tuition-rates', 'basePath' => 'admin'];
    }

    public function index()
    {
        $rates = (new TuitionRateModel())->orderBy('academic_year', 'DESC')->findAll();

        $programs = (new ProgramModel())->where('is_active', 1)->orderBy('program_name')->findAll();
        $programsById = [];

        foreach ($programs as $program) {
            $programsById[(int) $program['program_id']] = $program;
        }

        return view('admin/tuition_rates/index', [
            'pageTitle'    => 'Tuition Rates',
            'rates'        => $rates,
            'programs'     => $programs,
            'programsById' => $programsById,
        ] + $this->shell());
    }

    public function store()
    {
        $rules = [
            'program_id'    => 'required|integer',
            'academic_year' => 'required|max_length[20]',
            'per_unit_rate' => 'required|decimal|greater_than_equal_to[0]',
            'misc_fee'      => 'permit_empty|decimal|greater_than_equal_to[0]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to($this->baseUrl())->with('errors', $this->validator->getErrors());
        }

        $rates = new TuitionRateModel();

        $data = [
            'program_id'    => (int) $this->request->getPost('program_id'),
            'academic_year' => (string) $this->request->getPost('academic_year'),
            'per_unit_rate' => (float) $this->request->getPost('per_unit_rate'),
            'misc_fee'      => (float) ($this->request->getPost('misc_fee') ?: 0),
        ];

        $existing = $rates->forProgramYear($data['program_id'], $data['academic_year']);

        if ($existing !== null) {
            $rates->update((int) $existing['rate_id'], $data);
        } else {
            $rates->insert($data);
        }

        return redirect()->to($this->baseUrl())->with('message', 'Tuition rate saved.');
    }
}
