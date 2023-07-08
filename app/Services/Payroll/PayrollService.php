<?php

namespace App\Services\Payroll;

use App\Repositories\Payslip\PayslipRepositoryInterface;
use App\Services\Payroll\PayrollServiceInterface;

use App\Models\User;
use App\Models\DesignationUser;
use App\Models\Payslip;
use App\Models\PayslipDeduction;
use App\Models\PayrollPeriod;
use App\Models\TaxContribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\Earning;
use App\Models\Deduction;
use Carbon\Carbon;
use App\Helpers\Helper;

class PayrollService implements PayrollServiceInterface
{
    private PayslipRepositoryInterface $modelRepository;
    private $helper;

    public function __construct(
        PayslipRepositoryInterface $modelRepository,
        Helper $helper
    ) {
        $this->modelRepository = $modelRepository;
        $this->helper = $helper;
    }

    public function previewPayrollByUser($user, $period_start, $period_end)
    {
        $collection = [
            'user_id' => $user->id,
            'name' => $user->formal_name,
            'code' => $user->code,
            'total_hours' => [
                'regular' => [
                    'name' => 'Regular',
                    'acronym' => 'rg',
                    'value' => 0,
                    'visible' => FALSE,
                    'is_editable' => false,
                ],
                'late' => [
                    'name' => 'Late',
                    'acronym' => 'lt',
                    'value' => 0,
                    'visible' => FALSE,
                    'is_editable' => false,
                ],
                'undertime' => [
                    'name' => 'Undertime',
                    'acronym' => 'ut',
                    'value' => 0,
                    'visible' => FALSE,
                    'is_editable' => false,
                ],
                'overtime' => [
                    'name' => 'Overtime',
                    'acronym' => 'ot',
                    'value' => 0,
                    'visible' => FALSE,
                    'is_editable' => false,
                ],
                'night_differential' => [
                    'name' => 'Night Diff',
                    'acronym' => 'nd',
                    'value' => 0,
                    'visible' => FALSE,
                    'is_editable' => false,
                ],
                'restday' => [
                    'name' => 'Rest Day',
                    'acronym' => 'rd',
                    'value' => 0,
                    'visible' => FALSE,
                    'is_editable' => false,
                ],
                'restday_ot' => [
                    'name' => 'Rest Day OT',
                    'acronym' => 'rdot',
                    'value' => 0,
                    'visible' => FALSE,
                    'is_editable' => false,
                ],
            ],
            'deductions' => [],
            'additional_earnings' => [],
            'include_in_payroll' => true,
            'is_visible' => true
        ];

        // additional earnings initial
        $earning_types = Earning::where('active', true)->get();
            foreach($earning_types as $earning_type)
            {
                $collection['additional_earnings'][$earning_type->id] = [
                    'name' => $earning_type->name,
                    'acronym' => $earning_type->acronym,
                    'amount' => null,
                    'visible' => false,
                ];
            }
        // 

        // additional deductions initial
        $deduction_types = Deduction::where('active', true)->get();
            foreach($deduction_types as $deduction_type)
            {
                $collection['deductions'][$deduction_type->id] = [
                    'name' => $deduction_type->name,
                    'acronym' => $deduction_type->acronym,
                    'amount' => null,
                    'visible' => false,
                ];
            }
        // 
    
        $date_range = $this->helper->getRangeBetweenDatesStr($period_start, $period_end);
        $tardiness = 0;
        $basic_pay = 0;
        $earnings = 0;
        foreach ($date_range as $date) {
            
            $is_date_working_day = $this->helper->isDateWorkingDay(Carbon::parse($date));
            $collection['by_date'][$date]['is_working_day'] = $is_date_working_day;

            $collection['by_date'][$date]['hours'] = [
                'regular' => 0,
                'late' => 0,
                'undertime' => 0,
                'overtime' => 0,
                'night_differential' => 0,
                'restday' => 0,
                'restday_ot' => 0,
            ];
            $collection['by_date'][$date]['holiday']['is_holiday'] = FALSE;
            $collection['by_date'][$date]['holiday']['is_double_holiday'] = FALSE;
            $collection['by_date'][$date]['leave']['has_filed'] = FALSE;
            $collection['by_date'][$date]['leave']['record'] = [];

            // Get the daily rate for the user on the specific date
                $designationUser = DesignationUser::where('user_id', $user->id)
                    ->where('created_at', '<=', $date)
                    ->orderBy('created_at', 'desc')
                    ->first();
                $daily_rate_user = $designationUser ? $designationUser->designation->daily_rate : null;
                $collection['by_date'][$date]['daily_rates'] = $daily_rate_user;
                $hourly_rate = $collection['by_date'][$date]['daily_rates'] / 8;
            // 

            // basic pay 
            if($is_date_working_day === true) {
                $basic_pay += $daily_rate_user;
            }

            // GET ATTENDANCE 
                $attendance = Attendance::where('user_id', $user->id)
                    ->where('date', $date)
                    ->whereNotIn('status', [4, 5])
                    ->first();
        
                $collection['by_date'][$date]['attendance'] = $attendance ?? null;
            // 

            // GET HOLIDAY
                $holiday = Holiday::where('date', $date)->get();
                if($holiday->count() > 0) {
                    $collection['by_date'][$date]['holiday']['is_holiday'] = TRUE;
                    if($holiday->count() > 1) {
                        $collection['by_date'][$date]['holiday']['is_double_holiday'] = TRUE;
                    }
                    $collection['by_date'][$date]['holiday']['records'] = $holiday;
                }
            // 

            // GET LEAVE
                $leave = Leave::where('user_id', $user->id)
                    ->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date)
                    ->where('status', 2) // Assuming status 2 indicates approved leave
                    ->first();

                if ($leave) {
                    $collection['by_date'][$date]['leave']['has_filed'] = TRUE;
                    $collection['by_date'][$date]['leave']['record'] = $leave;
                }
            //

            // GET HOURS
            // get regular, late, undertime, overtime, night_differential, restday, restday_ot in attendance
                if ($attendance) {
                    $hours = [
                        'regular' => $attendance->status === 1 || $attendance->status === 2 ? $attendance->regular : 0,
                        'late' => $attendance->status === 1 || $attendance->status === 2 ? $attendance->late : 0,
                        'undertime' => $attendance->status === 1 || $attendance->status === 2 ? $attendance->undertime : 0,
                        'overtime' => $attendance->status === 1 || $attendance->status === 2 ? $attendance->overtime : 0,
                        'night_differential' => $attendance->night_differential,
                        'restday' => $attendance->status === 3 ? $attendance->regular : 0,
                        'restday_ot' => $attendance->status === 3 ? $attendance->overtime : 0,
                    ];
                    $collection['by_date'][$date]['hours'] = $hours;

                    $collection['by_date'][$date]['earnings'] = [
                        'regular' => $hours['regular'] * $hourly_rate,
                        'overtime' => $hours['overtime'] * $hourly_rate * 1.25,
                        'restday' => $hours['restday'] * $hourly_rate * 1.30,
                        'restday_ot' => $hours['restday_ot'] * $hourly_rate * 1.69,
                        'night_differential' => $hours['night_differential'] * $hourly_rate * .10,
                    ];

                    foreach($collection['by_date'][$date]['earnings'] as $earning_amount) {
                        $earnings += $earning_amount;
                    }
                } 
            // 

            // GET TARDINESS
            // get deductions late / undertime
                if($collection['by_date'][$date]['hours']['late'] > 0 || $collection['by_date'][$date]['hours']['undertime'] > 0) {
                    $late_hour = $collection['by_date'][$date]['hours']['late'];
                    $undertime_hour = $collection['by_date'][$date]['hours']['undertime'];
                    $late_amount = $late_hour * $hourly_rate;
                    $undertime_amount = $undertime_hour * $hourly_rate;
                    $tardiness_date = ($late_amount + $undertime_amount);
                    $tardiness += $tardiness_date;
                    $collection['by_date'][$date]['tardiness'] = $tardiness_date;
                }
            // 

            // Calculate total hours
                if(isset($collection['by_date'][$date]['hours'])) {
                    foreach ($collection['by_date'][$date]['hours'] as $type => $hoursValue) {
                        if($hoursValue !== 0) {
                            $collection['total_hours'][$type]['visible'] = TRUE;
                        } 
                        $collection['total_hours'][$type]['value'] += $hoursValue;
                    }
                }
            //
        }
    
        // Generate the rates_range array
            $designationUser = DesignationUser::where('user_id', $user->id)
                ->where('created_at', '<=', $period_end)
                ->orderBy('created_at')
                ->get();

            $ratesRange = [];
            $previousTo = null;

            foreach ($designationUser as $key => $du) {
                $from = ($key === 0) ? $period_start : $previousTo;
                $to = ($key + 1 < count($designationUser)) ? $designationUser[$key + 1]->created_at->subDay()->format('Y-m-d') : $period_end;

                $rate = $du->designation->daily_rate;

                $ratesRange[] = [
                    'from' => $from,
                    'to' => $to,
                    'rate' => strval(number_format($rate, 2, '.', ',')) . "\u{00A0}",
                ];

                $previousTo = Carbon::parse($to)->addDay()->format('Y-m-d');
            }

            $collection['rates_range'] = $ratesRange;
            if(count($ratesRange) === 0) {
                $collection['is_visible'] = false;
                $collection['include_in_payroll'] = false;
            }
            
        //

        // cash advance
            $loan = Helper::getCashAdvanceAmountToPay($user->id);
            if($loan > 0) {
                $collection['deductions']['loan'] = [
                    'name' => 'Loan',
                    'acronym' => 'lo',
                    'amount' => $loan,
                    'visible' => true,
                    'is_editable' => false,
                ];
            }
        // 

        // tardiness
            if($tardiness > 0) {
                $collection['deductions']['tardiness'] = [
                    'name' => 'Tardiness',
                    'acronym' => 'td',
                    'amount' => $tardiness,
                    'visible' => true,
                    'is_editable' => false,
                ];
            }
        // 

        // is total hours valid
            $total_hours = 0;
            foreach($collection['total_hours'] as $type => $data_total_hours) {
                $type = $type;
                if($type === 'regular' || $type === 'overtime' || $type === 'night_differential'  || $type  === 'restday' || $type === 'restday_ot') {
                    $total_hours += $data_total_hours['value'];
                }
            }
            if($total_hours === 0) {
                $collection['include_in_payroll'] = false;
                $collection['is_visible'] = false;
            }
            
        // 

        $collection['basic_pay'] = $basic_pay;
        $collection['earnings'] = $earnings;
        return $collection;
    }
    
    public function payroll($data)
    {
        $raw_collection = (json_decode($data->data,true));
        $collection = [];

        foreach($raw_collection as $user_id => $user_collection)
        {
            $payroll_period = PayrollPeriod::where('period_start', $data->period_start)
                ->where('period_end', $data->period_end)
                ->first();

            $new_collection =  [
                'user_id' => $user_collection['user_id'], //
                'full_name' => $user_collection['name'],
                'code' => $user_collection['code'],
                'rates_range' => $user_collection['rates_range'],
                'basic_pay' => $user_collection['basic_pay'],
                'payroll_period_id' => $payroll_period->id,
                'earnings' => $user_collection['earnings'],
                'preview_data' => $user_collection,
            ];
            foreach($user_collection['total_hours'] as $hour_type => $hours) {
                $new_collection[$hour_type] = $hours['value'];
            }

            
            
            dd($new_collection);
            // $user_array =  [
                // 'user_id' => $user_id, //
            //     'cutoff_order' => $cutoff_order,
                // 'payroll_period_id' => $payroll_period->id,
                // 'full_name' => $user->formal_name(), // 
                // 'daily_rate' => $daily_rate, //

                // 'regular' => $regular_hours, //
                // 'overtime' => $overtime_hours, //
                // 'restday' => $restday_hours, //
                // 'restday_ot' => $restday_ot_hours, //
                // 'night_differential' => $night_diff_hours, //
                // 'late' => $late_hours, //
                // 'undertime' => $undertime_hours, //
                
                // 'basic_pay' => $basic_pay,
            //     'gross_pay' => $gross_pay,
            //     'net_pay' => $net_pay,

            //     'is_tax_exempted' => $user->is_tax_exempted,
            //     'tax_contributions' => $tax_contributions,
            //     'loan_deductions'=> $loan_deductions,
            //     'tardiness_amount' => $tardiness_amount,
            //     'total_deductions' => $total_deductions,

            //     'taxable' => $taxable,
            //     'non_taxable' => $non_taxable,

            //     'loan_change' => $loan_change,
                
            //     'additional_earnings' => $additional_earnings,
            //     'earnings_collection' => $earnings_collection,
            //     'deductions_collection' => $deductions_collection,
            //     'holidays_collection' => $holidays_collection,
            // ];
            // $collection[$user_id] = array_merge($user_collection['total_hours'], $secondaryArray);
            // COLLECTION
        }

    }

    public function getSSSContributionAmount($salary)
    {
        $sss_ee = 0;
        $sss_er = 0;
        $sss_ec = 0;
        $sss_rate = SssContributionRate::latest('year')->first();
        if($sss_rate)
        {
            $msc_min = $sss_rate->msc_min;
            $msc_max = $sss_rate->msc_max;
            $ee_share_rate = $sss_rate->ee_share;
            $er_share_rate = $sss_rate->er_share;

            if($salary <= $msc_min)
            {
                $sss_model = SssContributionModel::where('sss_contribution_rate_id', $sss_rate->id)->where('compensation_minimum', 0)->first();
            } 
            elseif($salary >= $msc_max)
            {
                $sss_model = SssContributionModel::where('sss_contribution_rate_id', $sss_rate->id)->where('compensation_maximum', 0)->first();
            } 
            else 
            {
                $sss_model = SssContributionModel::where('sss_contribution_rate_id', $sss_rate->id)
                ->where('compensation_minimum', '<=', $salary)
                ->where('compensation_maximum', '>=', $salary)
                ->first();
            }

            if($sss_model)
            {
                $msc = $sss_model->monthly_salary_credit;
                $sss_ee = $ee_share_rate / 100 * $msc;
                $sss_er = $er_share_rate / 100 * $msc;
                $sss_ec = $sss_model->ec_contribution;
            }
        }
        $data = [
            'ec' => $sss_ec,
            'ee' => $sss_ee,
            'er' => $sss_er,
        ];
        return $data;
    }

    public function getHDMFContributionAmount($rates, $payroll_period_start, $payroll_period_end)
    {
        $total_earnings = 0;
        $hdmf_er = 0;
        $hdmf_ee = 0;
    
        foreach ($rates as $rate) {
            $rateFrom = $rate['from'];
            $rateTo = $rate['to'];
            $rateAmount = $rate['rate'];
    
            // Check if the rate falls within the payroll period
            if ($rateFrom <= $payroll_period_end && $rateTo >= $payroll_period_start) {
                // Calculate the number of days the rate applies
                $daysInRange = min($rateTo, $payroll_period_end) - max($rateFrom, $payroll_period_start) + 1;
    
                // Add the earnings for the specific rate to the total
                $rateEarnings = $rateAmount * $daysInRange;
                $total_earnings += $rateEarnings;
            }
        }
    
        // Use the total earnings as the basis for HDMF contribution calculation
        $hdmf_contribution_rate = HdmfContributionRate::latest('year')
            ->where('msc_min', '<=', $total_earnings)
            ->where('msc_max', '>=', $total_earnings)
            ->first();
    
        if (!$hdmf_contribution_rate) {
            $hdmf_contribution_rate = HdmfContributionRate::latest('year')
                ->where('msc_min', '<', $total_earnings)
                ->where('msc_max', 0)
                ->first();
        }
    
        if ($hdmf_contribution_rate) {
            $ee_share_rate = $hdmf_contribution_rate->ee_share;
            $er_share_rate = $hdmf_contribution_rate->er_share;
    
            $hdmf_er = $er_share_rate / 100 * $total_earnings;
            $hdmf_ee = $ee_share_rate / 100 * $total_earnings;
        }
    
        $data = [
            'er' => $hdmf_er,
            'ee' => $hdmf_ee,
        ];
        return $data;
    }

    public function getPHICContributionAmount($rates, $payroll_period_start, $payroll_period_end)
    {
        $total_earnings = 0;
        $phic_er = 0;
        $phic_ee = 0;
    
        foreach ($rates as $rate) {
            $rateFrom = $rate['from'];
            $rateTo = $rate['to'];
            $rateAmount = $rate['rate'];
    
            // Check if the rate falls within the payroll period
            if ($rateFrom <= $payroll_period_end && $rateTo >= $payroll_period_start) {
                // Calculate the number of days the rate applies
                $daysInRange = min($rateTo, $payroll_period_end) - max($rateFrom, $payroll_period_start) + 1;
    
                // Add the earnings for the specific rate to the total
                $rateEarnings = $rateAmount * $daysInRange;
                $total_earnings += $rateEarnings;
            }
        }
    
        // Use the total earnings as the basis for PHIC contribution calculation
        $phic_contribution_rate = PhicContributionRate::where('year', Carbon::now()->year)
            ->where('mbs_min', '<=', $total_earnings)
            ->where('mbs_max', '>=', $total_earnings)
            ->first();
    
        if (!$phic_contribution_rate) {
            $phic_contribution_rate = PhicContributionRate::where('year', Carbon::now()->year)
                ->where('mbs_min', '<', $total_earnings)
                ->where('mbs_max', 0)
                ->first();
        }
    
        if ($phic_contribution_rate) {
            $share_rate = $phic_contribution_rate->premium_rate / 100;
    
            $monthly_premium = $share_rate * $total_earnings;
            $phic_er = $monthly_premium / 2;
            $phic_ee = $monthly_premium / 2;
        }
    
        $data = [
            'er' => $phic_er,
            'ee' => $phic_ee,
        ];
        return $data;
    }

    public function getTotalPaidLeaveHours($user_id, $between_dates)
    {
        $period_start = $between_dates['period_start'];
        $period_end = $between_dates['period_end'];

        $period_range = $this->helper->getRangeBetweenDatesStr($period_start, $period_end);

        $duration_hours = 0;

        // only 1 day
        $leave = Leave::where('user_id', $user_id)
        ->where('status', 2)
        ->where('is_paid', true)
        ->whereIn('type_id', [1,2])
        ->whereNull('end_date')
        ->whereBetween('start_date', [$period_start, $period_end])
        ->get();

        if($leave->count() !=0)
        {
            foreach($leave as $val)
            {
                $duration_hours += $val->hours_duration;
            }
        }

        // leave falls some day in payroll period dates (start date)
        
        $leave_1 = Leave::where('user_id', $user_id)
        ->where('status', 2)
        ->where('is_paid', true)
        ->where('type_id', 3)
        ->where('start_date', '>=', $period_start)
        ->where('end_date', '<=', $period_end)
        ->get();

        $leave_2 = Leave::where('user_id', $user_id)
        ->where('status', 2)
        ->where('is_paid', true)
        ->where('type_id', 3)
        ->where('start_date', '<=', $period_start)
        ->where('end_date', '>=', $period_start)
        ->get();

        $leave = $leave_1->merge($leave_2);

        $leave_3 = Leave::where('user_id', $user_id)
        ->where('status', 2)
        ->where('is_paid', true)
        ->where('type_id', 3)
        ->where('start_date', '<=', $period_end)
        ->where('end_date', '>=', $period_end)
        ->get();

        $leave = $leave->merge($leave_3);

        // count date intersect
        $date_intersect_count = 0;
        $range_valid = [];
        foreach($leave as $val)
        {
            $leave_start_date = $val->start_date;
            $leave_end_date = $val->end_date;

            $range = $this->helper->getRangeBetweenDatesStr($leave_start_date, $leave_end_date);
            $range_valid = array_unique(array_merge($range,$range_valid), SORT_REGULAR);
            $date_intersect_count +=  count(array_intersect($range, $period_range ));
        }

        foreach($range_valid as $date)
        {
            if (($key_in_period_range = array_search($date, $period_range)) === false) {
                $key = array_search($date, $range_valid);
                unset($range_valid[$key]);
            }
        }
        
        // remove rest days
        foreach($range_valid as $date)
        {
            $is_date_working_day = $this->helper->isDateWorkingDay(Carbon::parse($date));
            if(!$is_date_working_day)
            {
                $key = array_search($date, $range_valid);
                unset($range_valid[$key]);
            }
        }
        

        $days_leave = count($range_valid);
        $hours_leave = $days_leave * 8;

        $duration_hours += $hours_leave;
        return $duration_hours;
    }

}