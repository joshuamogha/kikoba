<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\LoanManagement\LoanApplicationController;
use App\Http\Controllers\LoanManagement\LoanController;
use App\Http\Controllers\LoanManagement\LoanGuarantorController;
use App\Http\Controllers\LoanManagement\LoanInstallmentController;
use App\Http\Controllers\LoanManagement\LoanProductController;
use App\Http\Controllers\LoanManagement\LoanProductRangeController;
use App\Http\Controllers\LoanManagement\LoanRepaymentController;
use App\Http\Controllers\PaymentManagement\ContributionCalendarController;
use App\Http\Controllers\PaymentManagement\ContributionController;
use App\Http\Controllers\PaymentManagement\FinancialCycleController;
use App\Http\Controllers\PaymentManagement\GroupExpenseController;
use App\Http\Controllers\PaymentManagement\GroupFundController;
use App\Http\Controllers\PaymentManagement\GroupIncomeController;
use App\Http\Controllers\PaymentManagement\LookupController;
use App\Http\Controllers\PaymentManagement\PaymentPageController;
use App\Http\Controllers\PaymentManagement\PaymentTransactionController;
use App\Http\Controllers\SettingManagement\FinePaymentController;
use App\Http\Controllers\SettingManagement\FineTypeController;
use App\Http\Controllers\SettingManagement\MemberFineController;
use App\Http\Controllers\SettingManagement\SettingPageController;
use App\Http\Controllers\ShareManagement\GroupShareController;
use App\Http\Controllers\ShareManagement\MemberShareOutListController;
use App\Http\Controllers\ShareManagement\MemberShareTransactionListController;
use App\Http\Controllers\ShareManagement\ShareOutController;
use App\Http\Controllers\ShareManagement\ShareOutListController;
use App\Http\Controllers\ShareManagement\ShareOutPaymentController;
use App\Http\Controllers\ShareManagement\ShareOutPaymentListController;
use App\Http\Controllers\UserManagement\GroupController;
use App\Http\Controllers\UserManagement\GroupMemberController;
use App\Http\Controllers\UserManagement\GroupMemberListController;
use App\Http\Controllers\UserManagement\GroupMemberProfileController;
use App\Http\Controllers\UserManagement\GroupMemberRoleController;
use App\Http\Controllers\UserManagement\GroupRoleController;
use App\Http\Controllers\UserManagement\GroupSettingController;
use App\Http\Controllers\UserManagement\GroupTypeController;
use App\Http\Controllers\UserManagement\RelationController;
use App\Http\Controllers\UserManagement\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);

    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:6,1');

    Route::get('forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', LogoutController::class)->name('logout');

    Route::get('email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', function () {
        return view('backend.pages.home');
    })->name('dashboard');

    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');

    Route::get('share-outs', [ShareOutListController::class, 'index'])->name('share-outs.index');
    Route::get('member-share-outs', [MemberShareOutListController::class, 'index'])->name('member-share-outs.index');
    Route::get('share-out-payments', [ShareOutPaymentListController::class, 'index'])->name('share-out-payments.index');
    Route::get('member-share-transactions', [MemberShareTransactionListController::class, 'index'])->name('member-share-transactions.index');

    Route::get('payments/{page}', [PaymentPageController::class, 'show'])->whereIn('page', array_keys(PaymentPageController::PAGES))->name('payment-pages.show');

    Route::resource('financial-cycles', FinancialCycleController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('financial-cycles/{financial_cycle}/open', [FinancialCycleController::class, 'open'])->name('financial-cycles.open');
    Route::patch('financial-cycles/{financial_cycle}/close', [FinancialCycleController::class, 'close'])->name('financial-cycles.close');

    Route::resource('group-funds', GroupFundController::class)->only(['index', 'store', 'update']);

    foreach (['payment-methods', 'income-categories', 'group-expense-categories', 'loan-types', 'fund-types', 'contribution-frequencies', 'loan-interest-methods', 'membership-types', 'penalty-types', 'repayment-frequencies'] as $lookup) {
        Route::get($lookup, [LookupController::class, 'index'])->defaults('lookup', $lookup)->name("$lookup.index");
        Route::post($lookup, [LookupController::class, 'store'])->defaults('lookup', $lookup)->name("$lookup.store");
        Route::put("$lookup/{record}", [LookupController::class, 'update'])->defaults('lookup', $lookup)->name("$lookup.update");
    }

    Route::get('contribution-calendar', [ContributionCalendarController::class, 'index'])->name('contribution-calendar.index');
    Route::resource('contributions', ContributionController::class)->only(['index', 'store']);
    Route::patch('contributions/{contribution}/approve', [ContributionController::class, 'approve'])->name('contributions.approve');

    Route::resource('group-incomes', GroupIncomeController::class)->only(['index', 'store']);
    Route::patch('group-incomes/{group_income}/approve', [GroupIncomeController::class, 'approve'])->name('group-incomes.approve');

    Route::resource('group-expenses', GroupExpenseController::class)->only(['index', 'store']);
    Route::patch('group-expenses/{group_expense}/approve', [GroupExpenseController::class, 'approve'])->name('group-expenses.approve');
    Route::patch('group-expenses/{group_expense}/pay', [GroupExpenseController::class, 'pay'])->name('group-expenses.pay');

    Route::resource('loan-products', LoanProductController::class)->only(['index', 'store', 'update']);
    Route::resource('loan-ranges', LoanProductRangeController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('loan-applications', LoanApplicationController::class)->only(['index', 'store']);
    Route::get('loan-applications/preview', [LoanApplicationController::class, 'preview'])->name('loan-applications.preview');
    Route::post('loan-applications/record', [LoanApplicationController::class, 'record'])->name('loan-applications.record');
    Route::patch('loan-applications/{loan_application}/approve', [LoanApplicationController::class, 'approve'])->name('loan-applications.approve');
    Route::patch('loan-applications/{loan_application}/reject', [LoanApplicationController::class, 'reject'])->name('loan-applications.reject');
    Route::patch('loan-applications/{loan_application}/cancel', [LoanApplicationController::class, 'cancel'])->name('loan-applications.cancel');

    Route::resource('loan-guarantors', LoanGuarantorController::class)->only(['index', 'store']);
    Route::patch('loan-guarantors/{loan_guarantor}/respond', [LoanGuarantorController::class, 'respond'])->name('loan-guarantors.respond');

    Route::resource('loans', LoanController::class)->only(['index', 'store', 'show']);
    Route::get('loan-installments', [LoanInstallmentController::class, 'index'])->name('loan-installments.index');

    Route::resource('loan-repayments', LoanRepaymentController::class)->only(['index', 'store']);
    Route::patch('loan-repayments/{loan_repayment}/approve', [LoanRepaymentController::class, 'approve'])->name('loan-repayments.approve');

    Route::get('setting-management/{page}', [SettingPageController::class, 'show'])->whereIn('page', array_keys(SettingPageController::PAGES))->name('setting-pages.show');
    Route::resource('fine-types', FineTypeController::class)->only(['index', 'store', 'update']);
    Route::get('fine-payments', [FinePaymentController::class, 'index'])->name('fine-payments.index');
    Route::resource('member-fines', MemberFineController::class)->only(['index', 'store']);
    Route::patch('member-fines/{member_fine}/waive', [MemberFineController::class, 'waive'])->name('member-fines.waive');
    Route::post('member-fines/{member_fine}/payments', [MemberFineController::class, 'pay'])->name('member-fines.pay');

    Route::get('payment-transactions', [PaymentTransactionController::class, 'index'])->name('payment-transactions.index');

    Route::get('members', [GroupMemberListController::class, 'index'])->name('members.index');
    Route::get('members/import/template', [GroupMemberListController::class, 'template'])->name('members.template');
    Route::post('members/import', [GroupMemberListController::class, 'import'])->name('members.import');
    Route::get('members/{group_member}', [GroupMemberProfileController::class, 'show'])->whereNumber('group_member')->name('members.show');
    Route::get('members/{group_member}/records/{type}', [GroupMemberProfileController::class, 'records'])
        ->whereNumber('group_member')->whereIn('type', GroupMemberProfileController::TYPES)->name('members.records');

    Route::resource('groups', GroupController::class)->only(['index', 'store', 'show', 'update']);

    Route::prefix('groups/{group}')->scopeBindings()->name('groups.')->group(function () {
        Route::get('members', [GroupMemberController::class, 'index'])->name('members.index');
        Route::get('members/lookup', [GroupMemberController::class, 'lookup'])->middleware('throttle:20,1')->name('members.lookup');
        Route::post('members', [GroupMemberController::class, 'store'])->name('members.store');
        Route::put('members/{group_member}', [GroupMemberController::class, 'update'])->name('members.update');
        Route::delete('members/{group_member}', [GroupMemberController::class, 'destroy'])->name('members.destroy');
        Route::patch('members/{group_member}/status', [GroupMemberController::class, 'updateStatus'])->name('members.status');
        Route::patch('members/{group_member}/link', [GroupMemberController::class, 'link'])->name('members.link');
        Route::post('members/{group_member}/roles', [GroupMemberRoleController::class, 'store'])->name('members.roles.store');
        Route::delete('members/{group_member}/roles/{group_member_role}', [GroupMemberRoleController::class, 'destroy'])->name('members.roles.destroy');

        Route::get('roles', [GroupRoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [GroupRoleController::class, 'store'])->name('roles.store');
        Route::put('roles/{group_role}', [GroupRoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{group_role}', [GroupRoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('settings', [GroupSettingController::class, 'show'])->name('settings.show');
        Route::put('settings', [GroupSettingController::class, 'update'])->name('settings.update');

        Route::get('shares/import/template', [GroupShareController::class, 'template'])->name('shares.template');
        Route::post('shares/import', [GroupShareController::class, 'import'])->name('shares.import');
        Route::get('shares', [GroupShareController::class, 'index'])->name('shares.index');
        Route::post('shares', [GroupShareController::class, 'store'])->name('shares.store');

        Route::get('share-outs', [ShareOutController::class, 'index'])->name('share-outs.index');
        Route::post('share-outs', [ShareOutController::class, 'store'])->name('share-outs.store');
        Route::get('share-outs/{share_out}', [ShareOutController::class, 'show'])->name('share-outs.show');
        Route::put('share-outs/{share_out}', [ShareOutController::class, 'update'])->name('share-outs.update');
        Route::patch('share-outs/{share_out}/approve', [ShareOutController::class, 'approve'])->name('share-outs.approve');
        Route::delete('share-outs/{share_out}', [ShareOutController::class, 'destroy'])->name('share-outs.destroy');
        Route::post('share-outs/{share_out}/payments', [ShareOutPaymentController::class, 'store'])->name('share-outs.payments.store');
    });
    Route::get('users', [UserController::class, 'index'])->middleware('can:platform-admin')->name('users.index');
});

Route::middleware(['auth', 'verified', 'can:platform-admin'])->group(function () {
    Route::resource('group-types', GroupTypeController::class)->except(['create', 'show', 'edit']);
    Route::post('group-types/{group_type}/activate', [GroupTypeController::class, 'activate'])->name('group-types.activate');
    Route::resource('relations', RelationController::class)->except(['create', 'show', 'edit']);
    Route::post('relations/{relation}/activate', [RelationController::class, 'activate'])->name('relations.activate');
});
