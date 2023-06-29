<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware(['auth:sanctum', 'verified', 'system_access'])->group(function () {
    Route::group(['namespace' => 'App\Http\Livewire'], function() {
        Route::get('/', function () {
            if(Auth::user()->hasRole('administrator')) {
                return redirect()->route('dashboard');
            } else {
                return redirect()->route('employee_attendance');
            }
            
        });
        

        Route::get('employee-attendance', Attendance\EmployeeAttendanceComponent::class)->name('employee_attendance');

        Route::get('loan', Loan\LoanComponent::class)->name('loan');

        Route::get('leave',Leave\LeaveComponent::class)->name('leave');


        Route::get('payslip',Payroll\PayslipComponent::class)->name('payslip');
        Route::get('profile',Profile\ProfileComponent::class)->name('profile');


        // Administrator
        Route::group(['middleware' => ['auth', 'role:administrator']], function() {
            Route::get('attendance', Attendance\AttendanceComponent::class)->name('attendance');
            Route::get('dashboard', Dashboard\DashboardComponent::class)->name('dashboard');
            
            // PROJECT
            Route::group(['namespace' => 'Project'], function() {
                Route::get('project', ProjectComponent::class)->name('project');
                Route::get('project/details', ProjectDetailsComponent::class)->name('project.details');
                // Route::get('employee/hire-new-employee', NewEmployeeFormComponent::class)->name('employee.hire');
            });

            // EMPLOYEE
            Route::group(['namespace' => 'Employee'], function() {
                Route::get('employee', EmployeeComponent::class)->name('employee');
                Route::get('employee/profile', ProfileComponent::class)->name('employee.profile');
                Route::get('employee/archive-profile', ProfileComponent::class)->name('employee_archive.profile');
                Route::get('employee/hire-new-employee', NewEmployeeFormComponent::class)->name('employee.hire');
            });

            // LOAN
            Route::group(['namespace' => 'Loan'], function() {
                Route::get('loan/grand', GrandLoanComponent::class)->name('loan.grand');
                Route::get('loan/installment', LoanInstallmentComponent::class)->name('loan.installment');
            });

            // Payroll
            Route::group(['namespace' => 'Payroll'], function() {
                Route::get('payroll', PayrollComponent::class)->name('payroll');
                // Route::get('payroll/settings', Settings\PayrollSettingsComponent::class)->name('payroll.settings');

                Route::get('payroll/run', RunPayrollComponent::class)->name('payroll.run');
                Route::get('payroll/review', ReviewProcessedPayrollComponent::class)->name('payroll.review');
            });

            // REPORTS
            Route::group(['namespace' => 'Reports'], function() {
                Route::get('reports', ReportComponent::class)->name('reports');
            });

            // SETTINGS
            Route::group(['namespace' => 'Settings'], function() {
                Route::get('settings', SettingsComponent::class)->name('settings');
            });

        });
    });
    
});

require __DIR__.'/auth.php';
