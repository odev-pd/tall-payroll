<?php

namespace App\Http\Livewire\Payroll;

use Livewire\Component;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Attendance;

use Livewire\WithPagination;


class PayrollComponent extends Component
{
    use WithPagination;
    
    public $perPage = 10;
    public $selected_payroll_period;
    public $search_payslip_using_paydate;
    public $search;

    public $total_pending_attendance;

    public function mount()
    {
        $attendances = Attendance::where('status', 4)->get();
        $this->total_pending_attendance = $attendances->count();
    }

    public function render()
    {
        return view('livewire.payroll.payroll-component', [
            'latest_payroll_period' => $this->latestPayrollPeriod,
            'previous_payrolls' => $this->previousPayrolls,
            'payslips' => $this->payslips,
        ])
        ->layout('layouts.app',  ['menu' => 'payroll']);
    }

    public function getLatestPayrollPeriodProperty()
    {
        return PayrollPeriod::latest('period_end')
        ->where('is_payroll_generated', false)
        ->first();
    }

    public function getPreviousPayrollsProperty()
    {
        return PayrollPeriod::latest('period_end')
        ->get();
    }

    public function getPayslipsProperty()
    {
        $search = $this->search;
        $payroll_period_id = $this->search_payslip_using_paydate;

        return Payslip::latest('payslips.updated_at')
        ->where(function ($query) use ($payroll_period_id){
            if($payroll_period_id != "")
            {
                return $query->where('payroll_period_id', $payroll_period_id);
            }
        })
        ->leftJoin('users', 'users.id', '=', 'payslips.user_id')
        ->where(function ($query) use ($search) {
            return $query->where('users.last_name', 'like', '%' . $search . '%')
            ->orWhere('users.first_name', 'like', '%' . $search . '%')
            ->orWhere('users.code', 'like', '%' . $search . '%');
        })
        
        ->paginate(15);
    }

    

    public function submit()
    {
        return redirect()->route('payroll.run', [
            'payroll_period'  => $this->latestPayrollPeriod->id
        ]);
    }

    public function submitPreviousPayroll()
    {
        return redirect()->route('payroll.run', [
            'payroll_period'  => $this->selected_payroll_period
        ]);
    }
}
