<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FarmTypeCategoryController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\UserAccessController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\FarmTypeController;
use App\Http\Controllers\Admin\CommunityController;
use App\Http\Controllers\Admin\DistrictController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Admin\FarmerGroupTypeController;
use App\Http\Controllers\Admin\FarmerGroupController;
use App\Http\Controllers\Admin\LedgerAccountController;
use App\Http\Controllers\Admin\LedgerControlController;
use App\Http\Controllers\Admin\LedgerCategoryController;
use App\Http\Controllers\Admin\LedgerSubcategoryController;
use App\Http\Controllers\Admin\LedgerClassController;
use App\Http\Controllers\Admin\LedgerTypeController;
use App\Http\Controllers\Auth\PhoneVerificationController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AccountingPeriodController;
use App\Http\Controllers\Admin\TransactionTemplateController;
use App\Http\Controllers\Admin\FarmerController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'    => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

// open to guests and to signed-in users re-verifying, so the session is what guards them
Route::middleware('otp.pending')->group(function () {
    Route::get('verify-otp', [OtpController::class, 'create'])->name('otp.create');
    Route::post('verify-otp', [OtpController::class, 'store'])->name('otp.store');
    Route::post('resend-otp', [OtpController::class, 'resend'])
        ->middleware('throttle:otp-resend')
        ->name('otp.resend');
});

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:register');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::post('login/otp', [OtpController::class, 'requestLogin'])
        ->middleware('throttle:otp-request')
        ->name('login.otp');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    // an invited person lands here once their code checks out, holding a session pass rather than a login
    Route::middleware('activation.pending')->group(function () {
        Route::get('set-password', [SetPasswordController::class, 'create'])->name('activation.password.create');
        Route::post('set-password', [SetPasswordController::class, 'store'])->name('activation.password.store');
    });

    // an invited person starts here, since their browser has no session to bind a code to
    Route::get('activate', [ActivationController::class, 'create'])->name('activation.create');
    Route::post('activate', [ActivationController::class, 'store'])
        ->middleware('throttle:otp-request')
        ->name('activation.store');

    Route::get('auth/{provider}', [SocialAuthController::class, 'redirect'])
        ->name('social.redirect')
        ->where('provider', 'google|facebook');
    Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->name('social.callback')
        ->where('provider', 'google|facebook');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn() => redirect()->route('farmer.dashboard'));
    Route::get('/farmer/dashboard', fn() => Inertia::render('Dashboard'))
        ->name('farmer.dashboard');
    Route::get('/agent/dashboard', fn() => Inertia::render('Dashboard'))
        ->name('agent.dashboard');
    Route::get('/vet/dashboard', fn() => Inertia::render('Dashboard'))
        ->name('vet.dashboard');
    Route::get('/adviser/dashboard', fn() => Inertia::render('Dashboard'))
        ->name('adviser.dashboard');
    Route::get('/supplier/dashboard', fn() => Inertia::render('Dashboard'))
        ->name('supplier.dashboard');

    Route::get('/auth/check', fn() => response()->json(['authenticated' => true]));

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // lets a signed in user prove they hold their own phone number
    Route::post('verify-phone/send', [PhoneVerificationController::class, 'send'])
        ->name('otp.phone.send');
    Route::post('verify-phone/confirm', [PhoneVerificationController::class, 'confirm'])
        ->name('otp.phone.confirm');
});

// role-gated: only the admin role may reach these, regardless of any permission grant
Route::middleware(['auth', 'role:admin', 'verified.phone'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::middleware('access:access-control.manage')->prefix('permissions')->name('permissions.')->group(function () {
        Route::get('/roles', [RolePermissionController::class, 'index'])->name('roles.index');
        Route::get('/users', [UserAccessController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserAccessController::class, 'show'])->name('users.show');

        // sensitive mutations require a recent password confirmation, silently extended by real activity elsewhere in the app
        Route::middleware('password.confirm')->group(function () {
            Route::put('/roles/{role}', [RolePermissionController::class, 'update'])->name('roles.update');
            Route::put('/users/{user}/role', [UserAccessController::class, 'updateRole'])->name('users.role.update');
            Route::post('/users/{user}/grants', [UserAccessController::class, 'storeGrant'])->name('users.grants.store');
            Route::delete('/users/{user}/grants/{permission}', [UserAccessController::class, 'destroyGrant'])->name('users.grants.destroy');
            Route::post('/users/{user}/denials', [UserAccessController::class, 'storeDenial'])->name('users.denials.store');
            Route::delete('/users/{user}/denials/{permission}', [UserAccessController::class, 'destroyDenial'])->name('users.denials.destroy');
        });
    });
});

// permission-gated: any role can reach these if granted the specific permission, independent of role:admin
Route::middleware(['auth', 'verified.phone'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('access:farm-type-categories.view')->group(function () {
        Route::get('/farm-type-categories', [FarmTypeCategoryController::class, 'index'])
            ->name('farm-type-categories.index');
    });

    Route::middleware('access:farm-type-categories.create')->group(function () {
        Route::post('/farm-type-categories', [FarmTypeCategoryController::class, 'store'])
            ->name('farm-type-categories.store');
    });

    Route::middleware('access:farm-type-categories.update')->group(function () {
        Route::put('/farm-type-categories/{farmTypeCategory}', [FarmTypeCategoryController::class, 'update'])
            ->name('farm-type-categories.update');
    });

    Route::middleware('access:farm-type-categories.delete')->group(function () {
        Route::delete('/farm-type-categories/{farmTypeCategory}', [FarmTypeCategoryController::class, 'destroy'])
            ->name('farm-type-categories.destroy');
    });

    // farm types
    Route::middleware('access:farm-types.view')->group(function () {
        Route::get('/farm-types', [FarmTypeController::class, 'index'])
            ->name('farm-types.index');
    });

    Route::middleware('access:farm-types.create')->group(function () {
        Route::post('/farm-types', [FarmTypeController::class, 'store'])
            ->name('farm-types.store');
    });

    Route::middleware('access:farm-types.update')->group(function () {
        Route::put('/farm-types/{farmType}', [FarmTypeController::class, 'update'])
            ->name('farm-types.update');
    });

    Route::middleware('access:farm-types.delete')->group(function () {
        Route::delete('/farm-types/{farmType}', [FarmTypeController::class, 'destroy'])
            ->name('farm-types.destroy');
    });

    Route::middleware('access:farmer-groups.view')->group(function () {
        Route::get('/regions', [RegionController::class, 'index'])->name('regions.index');
        Route::get('/districts', [DistrictController::class, 'index'])->name('districts.index');
        Route::get('/communities', [CommunityController::class, 'index'])->name('communities.index');
    });

    Route::middleware('access:farmer-groups.create')->group(function () {
        Route::post('/regions', [RegionController::class, 'store'])->name('regions.store');
        Route::post('/districts', [DistrictController::class, 'store'])->name('districts.store');
        Route::post('/communities', [CommunityController::class, 'store'])->name('communities.store');
    });

    Route::middleware('access:farmer-groups.update')->group(function () {
        Route::put('/regions/{region}', [RegionController::class, 'update'])->name('regions.update');
        Route::put('/districts/{district}', [DistrictController::class, 'update'])->name('districts.update');
        Route::put('/communities/{community}', [CommunityController::class, 'update'])->name('communities.update');
    });

    Route::middleware('access:farmer-groups.delete')->group(function () {
        Route::delete('/regions/{region}', [RegionController::class, 'destroy'])->name('regions.destroy');
        Route::delete('/districts/{district}', [DistrictController::class, 'destroy'])->name('districts.destroy');
        Route::delete('/communities/{community}', [CommunityController::class, 'destroy'])->name('communities.destroy');
    });

    Route::middleware('access:farmer-groups.view')->group(function () {
        Route::get('/farmer-group-types', [FarmerGroupTypeController::class, 'index'])->name('farmer-group-types.index');
    });

    Route::middleware('access:farmer-groups.create')->group(function () {
        Route::post('/farmer-group-types', [FarmerGroupTypeController::class, 'store'])->name('farmer-group-types.store');
    });

    Route::middleware('access:farmer-groups.update')->group(function () {
        Route::put('/farmer-group-types/{farmerGroupType}', [FarmerGroupTypeController::class, 'update'])->name('farmer-group-types.update');
    });

    Route::middleware('access:farmer-groups.delete')->group(function () {
        Route::delete('/farmer-group-types/{farmerGroupType}', [FarmerGroupTypeController::class, 'destroy'])->name('farmer-group-types.destroy');
    });

    // farmer groups
    Route::middleware('access:farmer-groups.view')->group(function () {
        Route::get('/farmer-groups', [FarmerGroupController::class, 'index'])
            ->name('farmer-groups.index');
    });

    Route::middleware('access:farmer-groups.create')->group(function () {
        Route::post('/farmer-groups', [FarmerGroupController::class, 'store'])
            ->name('farmer-groups.store');
    });

    Route::middleware('access:farmer-groups.update')->group(function () {
        Route::put('/farmer-groups/{farmerGroup}', [FarmerGroupController::class, 'update'])
            ->name('farmer-groups.update');
    });

    Route::middleware('access:farmer-groups.delete')->group(function () {
        Route::delete('/farmer-groups/{farmerGroup}', [FarmerGroupController::class, 'destroy'])
            ->name('farmer-groups.destroy');
    });

    // ledger classes
    Route::middleware('access:ledger-accounts.view')->group(function () {
        Route::get('/ledger-classes', [LedgerClassController::class, 'index'])
            ->name('ledger-classes.index');
    });

    Route::middleware('access:ledger-accounts.create')->group(function () {
        Route::post('/ledger-classes', [LedgerClassController::class, 'store'])
            ->name('ledger-classes.store');
    });

    Route::middleware('access:ledger-accounts.update')->group(function () {
        Route::put('/ledger-classes/{ledgerClass}', [LedgerClassController::class, 'update'])
            ->name('ledger-classes.update');
    });

    Route::middleware('access:ledger-accounts.delete')->group(function () {
        Route::delete('/ledger-classes/{ledgerClass}', [LedgerClassController::class, 'destroy'])
            ->name('ledger-classes.destroy');
    });

    // ledger types
    Route::middleware('access:ledger-accounts.view')->group(function () {
        Route::get('/ledger-types', [LedgerTypeController::class, 'index'])
            ->name('ledger-types.index');
    });

    Route::middleware('access:ledger-accounts.create')->group(function () {
        Route::post('/ledger-types', [LedgerTypeController::class, 'store'])
            ->name('ledger-types.store');
    });

    Route::middleware('access:ledger-accounts.update')->group(function () {
        Route::put('/ledger-types/{ledgerType}', [LedgerTypeController::class, 'update'])
            ->name('ledger-types.update');
    });

    Route::middleware('access:ledger-accounts.delete')->group(function () {
        Route::delete('/ledger-types/{ledgerType}', [LedgerTypeController::class, 'destroy'])
            ->name('ledger-types.destroy');
    });

    // ledger controls
    Route::middleware('access:ledger-accounts.view')->group(function () {
        Route::get('/ledger-controls', [LedgerControlController::class, 'index'])
            ->name('ledger-controls.index');
    });

    Route::middleware('access:ledger-accounts.create')->group(function () {
        Route::post('/ledger-controls', [LedgerControlController::class, 'store'])
            ->name('ledger-controls.store');
    });

    Route::middleware('access:ledger-accounts.update')->group(function () {
        Route::put('/ledger-controls/{ledgerControl}', [LedgerControlController::class, 'update'])
            ->name('ledger-controls.update');
    });

    Route::middleware('access:ledger-accounts.delete')->group(function () {
        Route::delete('/ledger-controls/{ledgerControl}', [LedgerControlController::class, 'destroy'])
            ->name('ledger-controls.destroy');
    });

    // ledger categories
    Route::middleware('access:ledger-accounts.view')->group(function () {
        Route::get('/ledger-categories', [LedgerCategoryController::class, 'index'])
            ->name('ledger-categories.index');
    });

    Route::middleware('access:ledger-accounts.create')->group(function () {
        Route::post('/ledger-categories', [LedgerCategoryController::class, 'store'])
            ->name('ledger-categories.store');
    });

    Route::middleware('access:ledger-accounts.update')->group(function () {
        Route::put('/ledger-categories/{ledgerCategory}', [LedgerCategoryController::class, 'update'])
            ->name('ledger-categories.update');
    });

    Route::middleware('access:ledger-accounts.delete')->group(function () {
        Route::delete('/ledger-categories/{ledgerCategory}', [LedgerCategoryController::class, 'destroy'])
            ->name('ledger-categories.destroy');
    });

    // ledger subcategories
    Route::middleware('access:ledger-accounts.view')->group(function () {
        Route::get('/ledger-subcategories', [LedgerSubcategoryController::class, 'index'])
            ->name('ledger-subcategories.index');
    });

    Route::middleware('access:ledger-accounts.create')->group(function () {
        Route::post('/ledger-subcategories', [LedgerSubcategoryController::class, 'store'])
            ->name('ledger-subcategories.store');
    });

    Route::middleware('access:ledger-accounts.update')->group(function () {
        Route::put('/ledger-subcategories/{ledgerSubcategory}', [LedgerSubcategoryController::class, 'update'])
            ->name('ledger-subcategories.update');
    });

    Route::middleware('access:ledger-accounts.delete')->group(function () {
        Route::delete('/ledger-subcategories/{ledgerSubcategory}', [LedgerSubcategoryController::class, 'destroy'])
            ->name('ledger-subcategories.destroy');
    });

    // ledger accounts
    Route::middleware('access:ledger-accounts.view')->group(function () {
        Route::get('/ledger-accounts', [LedgerAccountController::class, 'index'])
            ->name('ledger-accounts.index');
    });

    Route::middleware('access:ledger-accounts.create')->group(function () {
        Route::post('/ledger-accounts', [LedgerAccountController::class, 'store'])
            ->name('ledger-accounts.store');
    });

    Route::middleware('access:ledger-accounts.update')->group(function () {
        Route::put('/ledger-accounts/{ledgerAccount}', [LedgerAccountController::class, 'update'])
            ->name('ledger-accounts.update');
    });

    Route::middleware('access:ledger-accounts.delete')->group(function () {
        Route::delete('/ledger-accounts/{ledgerAccount}', [LedgerAccountController::class, 'destroy'])
            ->name('ledger-accounts.destroy');
    });

    Route::middleware('access:staff.view')->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    });

    Route::middleware('access:staff.create')->group(function () {
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');

        // a resend costs an sms but grants nothing new, so it needs no password confirmation
        Route::post('/staff/{user}/resend', [StaffController::class, 'resend'])
            ->middleware('throttle:otp-request')
            ->name('staff.resend');
    });

    Route::middleware('access:staff.update')->group(function () {
        Route::patch('/staff/{user}/disable', [StaffController::class, 'disable'])->name('staff.disable');
        Route::patch('/staff/{user}/enable', [StaffController::class, 'enable'])->name('staff.enable');
    });

    Route::middleware('access:staff.delete')->group(function () {
        Route::delete('/staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');
    });

    Route::middleware('access:audit.view')->group(function () {
        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    });

    Route::middleware('access:accounting-periods.view')->group(function () {
        Route::get('/accounting-periods', [AccountingPeriodController::class, 'index'])->name('accounting-periods.index');
    });

    Route::middleware('access:accounting-periods.create')->group(function () {
        Route::post('/accounting-periods', [AccountingPeriodController::class, 'store'])->name('accounting-periods.store');
    });

    Route::middleware('access:accounting-periods.close')->group(function () {
        Route::patch('/accounting-periods/{accountingPeriod}/close', [AccountingPeriodController::class, 'close'])->name('accounting-periods.close');
    });

    // kept apart from closing, since reopening changes a period reports were built from
    Route::middleware('access:accounting-periods.reopen')->group(function () {
        Route::patch('/accounting-periods/{accountingPeriod}/reopen', [AccountingPeriodController::class, 'reopen'])->name('accounting-periods.reopen');
    });

    // transaction templates
    Route::middleware('access:transaction-templates.view')->group(function () {
        Route::get('/transaction-templates', [TransactionTemplateController::class, 'index'])
            ->name('transaction-templates.index');
    });

    Route::middleware('access:transaction-templates.create')->group(function () {
        Route::post('/transaction-templates', [TransactionTemplateController::class, 'store'])
            ->name('transaction-templates.store');
    });

    Route::middleware('access:transaction-templates.update')->group(function () {
        Route::put('/transaction-templates/{transactionTemplate}', [TransactionTemplateController::class, 'update'])
            ->name('transaction-templates.update');
    });

    Route::middleware('access:transaction-templates.delete')->group(function () {
        Route::delete('/transaction-templates/{transactionTemplate}', [TransactionTemplateController::class, 'destroy'])
            ->name('transaction-templates.destroy');
    });

    // farmers
    Route::middleware('access:farmers.view')->group(function () {
        Route::get('/farmers', [FarmerController::class, 'index'])->name('farmers.index');
        Route::get('/farmers/{farmer}', [FarmerController::class, 'show'])->name('farmers.show');
    });

    Route::middleware('access:farmers.create')->group(function () {
        Route::post('/farmers', [FarmerController::class, 'store'])->name('farmers.store');
    });

    Route::middleware('access:farmers.update')->group(function () {
        Route::put('/farmers/{farmer}', [FarmerController::class, 'update'])->name('farmers.update');
        Route::post('/farmers/{farmer}/identity', [FarmerController::class, 'storeIdentity'])->name('farmers.identity.store');
    });

    // kept apart from editing, since verifying opens credit scoring and bank facing reports
    Route::middleware('access:farmers.verify')->group(function () {
        Route::patch('/farmers/{farmer}/identity/verify', [FarmerController::class, 'verifyIdentity'])->name('farmers.identity.verify');
    });
});
