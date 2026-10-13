<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\FinancePortal;
use App\Libraries\RegistrarPortal;
use App\Models\FeeTypeModel;
use App\Models\ProgramFeeRateModel;
use App\Models\ProgramModel;
use App\Models\TuitionRateModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * The tuition price list (per program per academic year) registrar/admin
 * sets — without a rate sheet for a program+year, TuitionAssessmentService
 * leaves every student in that program "unassessed" rather than guessing.
 * Served under /admin/tuition-rates and /finance/tuition-rates — rate
 * management moved to Finance; Registrar keeps read-only fees.view context
 * elsewhere but no longer mounts this controller at all. Same
 * isRegistrarPortal()-style convention as Admin\StudentRecordController,
 * extended to a third portal.
 *
 * A "rate sheet" is the per-unit tuition rate + installment count
 * (tuition_rates) plus one amount per active fee_types entry
 * (program_fee_rates) — edit() always shows every active catalog fee so a
 * newly-added fee type immediately appears on every program's next edit,
 * with no code change (see FeeTypeController for adding the fee itself).
 */
class TuitionController extends BaseController
{
    private function portal(): string
    {
        $segment = service('request')->getUri()->getSegment(1);

        return in_array($segment, ['registrar', 'finance'], true) ? $segment : 'admin';
    }

    private function baseUrl(): string
    {
        return '/' . $this->portal() . '/tuition-rates';
    }

    private function shell(): array
    {
        return match ($this->portal()) {
            'registrar' => RegistrarPortal::shell('tuition-rates'),
            'finance'   => FinancePortal::shell('tuition-rates'),
            default     => ['activeAdmin' => 'tuition-rates', 'basePath' => 'admin'],
        };
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

    /**
     * Create-or-edit form for one program+year's full rate sheet. Works
     * whether or not a tuition_rates row already exists — a brand-new
     * program+year combo just shows every field blank/zero.
     */
    public function edit(int $programId, string $academicYear)
    {
        $program = (new ProgramModel())->find($programId);

        if ($program === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $rate       = (new TuitionRateModel())->forProgramYear($programId, $academicYear);
        $feeAmounts = (new ProgramFeeRateModel())->amountsByFeeType($programId, $academicYear);
        $feeTypes   = (new FeeTypeModel())->active();

        $feesByCategory = [];

        foreach ($feeTypes as $feeType) {
            $feesByCategory[$feeType['category']][] = $feeType;
        }

        return view('admin/tuition_rates/edit', [
            'pageTitle'      => 'Tuition Rate Sheet',
            'program'        => $program,
            'academicYear'   => $academicYear,
            'rate'           => $rate,
            'feeAmounts'     => $feeAmounts,
            'feesByCategory' => $feesByCategory,
        ] + $this->shell());
    }

    public function store()
    {
        $rules = [
            'program_id'        => 'required|integer',
            'academic_year'     => 'required|max_length[60]',
            'per_unit_rate'     => 'required|decimal|greater_than_equal_to[0]',
            'installment_count' => 'required|integer|greater_than[0]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $programId    = (int) $this->request->getPost('program_id');
        $academicYear = (string) $this->request->getPost('academic_year');

        $rates = new TuitionRateModel();

        $rateData = [
            'program_id'        => $programId,
            'academic_year'     => $academicYear,
            'per_unit_rate'     => (float) $this->request->getPost('per_unit_rate'),
            'installment_count' => (int) $this->request->getPost('installment_count'),
        ];

        $existing = $rates->forProgramYear($programId, $academicYear);

        if ($existing !== null) {
            $rates->update((int) $existing['rate_id'], $rateData);
        } else {
            $rates->insert($rateData);
        }

        $feeAmounts = (array) ($this->request->getPost('fee') ?? []);
        $feeRates   = new ProgramFeeRateModel();

        foreach ($feeAmounts as $feeTypeId => $amount) {
            $feeTypeId = (int) $feeTypeId;
            $amount    = $amount === '' ? null : (float) $amount;

            $existingFee = $feeRates->where('program_id', $programId)
                ->where('academic_year', $academicYear)
                ->where('fee_type_id', $feeTypeId)
                ->first();

            if ($amount === null) {
                if ($existingFee !== null) {
                    $feeRates->delete((int) $existingFee['program_fee_rate_id']);
                }

                continue;
            }

            $data = [
                'program_id'    => $programId,
                'academic_year' => $academicYear,
                'fee_type_id'   => $feeTypeId,
                'amount'        => $amount,
            ];

            if ($existingFee !== null) {
                $feeRates->update((int) $existingFee['program_fee_rate_id'], $data);
            } else {
                $feeRates->insert($data);
            }
        }

        return redirect()->to($this->baseUrl() . '/edit/' . $programId . '/' . urlencode($academicYear))
            ->with('message', 'Rate sheet saved.');
    }
}
