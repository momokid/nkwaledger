<?php

use App\Http\Controllers\KioskReportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiseaseReportQueueController;
use App\Http\Controllers\Admin\FarmTypeCategoryController;
use App\Http\Controllers\Admin\OfficerAssignmentController;
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
use App\Http\Controllers\Admin\FarmUnitController;
use App\Http\Controllers\Admin\FarmUnitStockController;
use App\Http\Controllers\Farm\DiseaseReportController;
use App\Http\Controllers\Farm\FarmerDashboardController;
use App\Http\Controllers\Officer\DiseaseReportController as OfficerDiseaseReportController;
use App\Http\Controllers\Farm\MyFarmController;
use App\Http\Controllers\Transactions\RecordTransactionController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Transactions\ReversalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Agent\AgentDashboardController;
use App\Http\Controllers\Farm\FarmerWeatherController;
use App\Http\Controllers\Agent\AgentReportsController;
use App\Http\Controllers\Supplier\SupplierDashboardController;
use App\Http\Controllers\Supplier\SupplierProfileController;
use App\Http\Controllers\Supplier\KioskController as SupplierKioskController;
use App\Http\Controllers\Supplier\KioskProductController;
use App\Http\Controllers\Admin\SupplierController as AdminSupplierController;
use App\Http\Controllers\Admin\KioskController as AdminKioskController;
use App\Http\Controllers\Admin\MarketplaceSettingController;
use App\Http\Controllers\Admin\CatalogProductController as AdminCatalogProductController;

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
    Route::get('/farmer/dashboard', [FarmerDashboardController::class, 'index'])
        ->name('farmer.dashboard');
    Route::get('/agent/dashboard', [AgentDashboardController::class, 'index'])
        ->name('agent.dashboard');
    Route::middleware(['role:vet', 'access:disease-reports.view'])->group(function () {
        Route::get('/vet/dashboard', [OfficerDiseaseReportController::class, 'dashboard'])
            ->name('vet.dashboard');
    });
    Route::middleware(['role:adviser', 'access:disease-reports.view'])->group(function () {
        Route::get('/adviser/dashboard', [OfficerDiseaseReportController::class, 'dashboard'])
            ->name('adviser.dashboard');
    });
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

// a vet's queue and the reports assigned to them
Route::middleware(['auth', 'role:vet', 'verified.phone'])->prefix('vet')->name('vet.')->group(function () {
    Route::middleware('access:disease-reports.view')->group(function () {
        Route::get('/reports/{report:uuid}', [OfficerDiseaseReportController::class, 'show'])
            ->name('reports.show');
    });
    Route::middleware('access:disease-reports.respond')->group(function () {
        Route::post('/reports/{report:uuid}/respond', [OfficerDiseaseReportController::class, 'respond'])
            ->name('reports.respond');
    });
});

// the same controller, an adviser's own address so the frame and the url match their role
Route::middleware(['auth', 'role:adviser', 'verified.phone'])->prefix('adviser')->name('adviser.')->group(function () {
    Route::middleware('access:disease-reports.view')->group(function () {
        Route::get('/reports/{report:uuid}', [OfficerDiseaseReportController::class, 'show'])
            ->name('reports.show');
    });
    Route::middleware('access:disease-reports.respond')->group(function () {
        Route::post('/reports/{report:uuid}/respond', [OfficerDiseaseReportController::class, 'respond'])
            ->name('reports.respond');
    });
});

// a farmer reporting a kiosk - marketplace browsing itself is not built yet, so this
// has no page of its own, just the action a future kiosk-detail page will call
Route::middleware(['auth', 'role:farmer', 'verified.phone'])->group(function () {
    Route::post('/kiosks/{kiosk:uuid}/reports', [KioskReportController::class, 'store'])->name('kiosks.reports.store');
});

// the farmer's own book, with nobody named in the address
Route::middleware(['auth', 'verified.phone'])->prefix('my-records')->name('my-records.')->group(function () {
    Route::middleware('access:transactions.view')->group(function () {
        Route::get('/', [RecordTransactionController::class, 'index'])->name('index');
    });

    Route::middleware('access:transactions.create')->group(function () {
        Route::get('/create', [RecordTransactionController::class, 'create'])->name('create');
        Route::post('/', [RecordTransactionController::class, 'store'])->name('store');
        Route::post('/{transaction}/settle', [RecordTransactionController::class, 'settle'])->name('settle');
    });

    Route::middleware('access:transactions.reverse-request')->group(function () {
        Route::post('/{transaction}/cancel', [ReversalController::class, 'store'])->name('cancel');
    });
});

// the farmer's own farm, with nobody named in the address
Route::middleware(['auth', 'verified.phone'])->prefix('my-farm')->name('my-farm.')->group(function () {
    Route::middleware('access:farm-units.view')->group(function () {
        Route::get('/', [MyFarmController::class, 'index'])->name('index');
    });

    Route::middleware('access:disease-reports.view')->group(function () {
        Route::get('/reports', [DiseaseReportController::class, 'index'])
            ->name('reports.index');
        Route::get('/reports/{report:uuid}', [DiseaseReportController::class, 'show'])
            ->name('reports.show');
    });

    Route::middleware('access:disease-reports.create')->group(function () {
        Route::get('/{farmUnit}/report-problem', [DiseaseReportController::class, 'create'])
            ->name('report-problem.create');
        Route::post('/{farmUnit}/report-problem', [DiseaseReportController::class, 'store'])
            ->name('report-problem.store');
    });
});

// the farmer's own weather, with nobody named in the address
Route::middleware(['auth', 'verified.phone'])->prefix('my-weather')->name('my-weather.')->group(function () {
    Route::middleware('access:farm-units.view')->group(function () {
        Route::get('/', [FarmerWeatherController::class, 'index'])->name('index');
    });
});

Route::middleware(['auth', 'verified.phone'])->prefix('my-reports')->name('my-reports.')->group(function () {
    Route::middleware('access:transactions.view')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/print', [ReportController::class, 'print'])->name('print');
        Route::get('/pdf', [ReportController::class, 'pdf'])->name('pdf');
        Route::get('/csv', [ReportController::class, 'csv'])->name('csv');
    });
});

Route::middleware(['auth', 'verified.phone'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

// the agent's own address for the same controller, so the frame and the url match their role
Route::middleware(['auth', 'verified.phone'])->prefix('agent')->name('agent.')->group(function () {
    Route::middleware('access:farmers.create')->group(function () {
        Route::post('/farmers', [FarmerController::class, 'store'])->name('farmers.store');

        // declared before the {farmer} routes so the word pending is never read as a profile id
        Route::get('/farmers/pending/{user}', [FarmerController::class, 'complete'])->name('farmers.complete');
        Route::post('/farmers/pending/{user}', [FarmerController::class, 'storeComplete'])->name('farmers.complete.store');

        // a farmer who never acted on their first code needs another one
        Route::post('/farmers/{farmer}/resend', [FarmerController::class, 'resendActivation'])
            ->middleware('throttle:otp-request')
            ->name('farmers.resend');
    });

    Route::middleware('access:farmers.view')->group(function () {
        Route::get('/farmers', [FarmerController::class, 'index'])->name('farmers.index');
        Route::get('/farmers/{farmer}', [FarmerController::class, 'show'])->name('farmers.show');
    });

    Route::middleware('access:farmers.update')->group(function () {
        Route::put('/farmers/{farmer}', [FarmerController::class, 'update'])->name('farmers.update');
        Route::post('/farmers/{farmer}/identity', [FarmerController::class, 'storeIdentity'])->name('farmers.identity.store');
    });

    // every unit across the farmers this person can reach
    Route::middleware('access:farm-units.view')->group(function () {
        Route::get('/farm-units', [FarmUnitController::class, 'all'])->name('farm-units.all');
    });

    Route::middleware('access:farm-units.create')->group(function () {
        Route::post('/farm-units', [FarmUnitController::class, 'storeFromList'])->name('farm-units.all.store');
    });

    // farm units under one farmer
    Route::middleware('access:farm-units.view')->group(function () {
        Route::get('/farmers/{farmer}/units', [FarmUnitController::class, 'index'])->name('farm-units.index');
    });

    Route::middleware('access:farm-units.create')->group(function () {
        Route::post('/farmers/{farmer}/units', [FarmUnitController::class, 'store'])->name('farm-units.store');
    });

    Route::middleware('access:farm-units.update')->group(function () {
        Route::put('/farmers/{farmer}/units/{farmUnit}', [FarmUnitController::class, 'update'])->name('farm-units.update');
    });

    Route::middleware('access:farm-units.approve')->group(function () {
        Route::patch('/farmers/{farmer}/units/{farmUnit}/approve', [FarmUnitController::class, 'approve'])->name('farm-units.approve');
    });

    // what is in each unit
    Route::middleware('access:farm-units.view')->group(function () {
        Route::get('/farmers/{farmer}/units/{farmUnit}/stocks', [FarmUnitStockController::class, 'index'])->name('farm-units.stocks.index');
    });

    Route::middleware('access:farm-units.create')->group(function () {
        Route::post('/farmers/{farmer}/units/{farmUnit}/stocks', [FarmUnitStockController::class, 'storeStock'])->name('farm-units.stocks.store');
        Route::post('/farmers/{farmer}/units/{farmUnit}/stocks/{stock}/movements', [FarmUnitStockController::class, 'storeMovement'])->name('farm-units.movements.store');
    });

    Route::middleware('access:farm-units.confirm')->group(function () {
        Route::patch('/farmers/{farmer}/units/{farmUnit}/stocks/{stock}/confirm', [FarmUnitStockController::class, 'confirmStock'])->name('farm-units.stocks.confirm');
        Route::patch('/farmers/{farmer}/units/{farmUnit}/stocks/{stock}/movements/{movement}/confirm', [FarmUnitStockController::class, 'confirmMovement'])->name('farm-units.movements.confirm');
        Route::patch('/farmers/{farmer}/units/{farmUnit}/stocks/{stock}/reject', [FarmUnitStockController::class, 'rejectStock'])->name('farm-units.stocks.reject');
        Route::patch('/farmers/{farmer}/units/{farmUnit}/stocks/{stock}/movements/{movement}/reject', [FarmUnitStockController::class, 'rejectMovement'])->name('farm-units.movements.reject');
    });

    // everything waiting on somebody, in one list
    Route::middleware('access:approvals.view')->group(function () {
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    });

    Route::middleware('access:transactions.reverse-approve')->group(function () {
        Route::patch('/reversals/{reversal}/approve', [ReversalController::class, 'approve'])->name('reversals.approve');
        Route::patch('/reversals/{reversal}/reject', [ReversalController::class, 'reject'])->name('reversals.reject');
    });

    // recording on a farmer's behalf
    Route::middleware('access:transactions.view')->group(function () {
        Route::get('/reports', [AgentReportsController::class, 'index'])->name('reports.menu');
        Route::get('/reports/activity', [AgentReportsController::class, 'activity'])->name('reports.activity');
        Route::get('/reports/activity/print', [AgentReportsController::class, 'printActivity'])->name('reports.activity.print');
        Route::get('/reports/dormant', [AgentReportsController::class, 'dormant'])->name('reports.dormant');
        Route::get('/reports/dormant/print', [AgentReportsController::class, 'printDormant'])->name('reports.dormant.print');
        Route::get('/reports/income-summary', [AgentReportsController::class, 'incomeSummary'])->name('reports.income-summary');
        Route::get('/reports/income-summary/print', [AgentReportsController::class, 'printIncomeSummary'])->name('reports.income-summary.print');
        Route::get('/reports/ranking', [AgentReportsController::class, 'ranking'])->name('reports.ranking');
        Route::get('/reports/ranking/print', [AgentReportsController::class, 'printRanking'])->name('reports.ranking.print');
        Route::get('/farmers/{farmer}/profile-report', [AgentReportsController::class, 'farmerProfile'])->name('reports.farmer-profile');
        Route::get('/farmers/{farmer}/profile-report/print', [AgentReportsController::class, 'printFarmerProfile'])->name('reports.farmer-profile.print');
        Route::get('/farmers/{farmer}/records', [RecordTransactionController::class, 'index'])->name('records.index');
        Route::get('/farmers/{farmer}/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/farmers/{farmer}/reports/print', [ReportController::class, 'print'])->name('reports.print');
        Route::get('/farmers/{farmer}/reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
        Route::get('/farmers/{farmer}/reports/csv', [ReportController::class, 'csv'])->name('reports.csv');
    });

    Route::middleware('access:transactions.create')->group(function () {
        Route::get('/farmers/{farmer}/records/create', [RecordTransactionController::class, 'create'])->name('records.create');
        Route::post('/farmers/{farmer}/records', [RecordTransactionController::class, 'store'])->name('records.store');
        Route::post('/farmers/{farmer}/records/{transaction}/settle', [RecordTransactionController::class, 'settle'])->name('records.settle');
    });

    Route::middleware('access:transactions.reverse-request')->group(function () {
        Route::post('/farmers/{farmer}/records/{transaction}/cancel', [ReversalController::class, 'store'])->name('records.cancel');
    });
});

// the supplier's own address, self-scoped like the farmer/agent groups above
Route::middleware(['auth', 'role:supplier', 'verified.phone'])->prefix('supplier')->name('supplier.')->group(function () {
    Route::get('/dashboard', [SupplierDashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile/create', [SupplierProfileController::class, 'create'])->name('profile.create');
    Route::post('/profile', [SupplierProfileController::class, 'store'])->name('profile.store');
    Route::post('/profile/verify-email', [SupplierProfileController::class, 'verifyEmail'])->name('profile.verify-email');

    Route::get('/kiosks', [SupplierKioskController::class, 'index'])->name('kiosks.index');
    Route::post('/kiosks', [SupplierKioskController::class, 'store'])->name('kiosks.store');
    Route::post('/kiosks/{kiosk:uuid}/confirm', [SupplierKioskController::class, 'confirm'])->name('kiosks.confirm');

    Route::get('/kiosks/{kiosk:uuid}/products', [KioskProductController::class, 'index'])->name('kiosks.products.index');
    Route::post('/kiosks/{kiosk:uuid}/products', [KioskProductController::class, 'store'])->name('kiosks.products.store');
    Route::patch('/kiosks/{kiosk:uuid}/products/{kioskProduct:uuid}/price', [KioskProductController::class, 'updatePrice'])->name('kiosks.products.price');
    Route::post('/kiosks/{kiosk:uuid}/products/{kioskProduct:uuid}/confirm-price', [KioskProductController::class, 'confirmPrice'])->name('kiosks.products.confirm-price');
    Route::patch('/kiosks/{kiosk:uuid}/products/{kioskProduct:uuid}/stock', [KioskProductController::class, 'updateStock'])->name('kiosks.products.stock');
    Route::delete('/kiosks/{kiosk:uuid}/products/{kioskProduct:uuid}/images/{image}', [KioskProductController::class, 'destroyImage'])->name('kiosks.products.images.destroy');

    Route::post('/kiosk-reports/{kioskReport:uuid}/answer', [\App\Http\Controllers\Supplier\KioskReportController::class, 'answer'])->name('kiosk-reports.answer');
});

// role-gated: only the admin role may reach these, regardless of any permission grant
Route::middleware(['auth', 'role:admin', 'verified.phone'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('/agents/{agent}/detail', [\App\Http\Controllers\Admin\AgentDetailController::class, 'show'])
        ->name('agents.detail');

    Route::get('/regions/{region}/detail', [\App\Http\Controllers\Admin\RegionDetailController::class, 'show'])
        ->name('regions.detail');

    Route::get('/regions/{region}/health-detail', [\App\Http\Controllers\Admin\RegionHealthDetailController::class, 'show'])
        ->name('regions.health-detail');

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

    // reports whose farmer's agent has no matching officer linked yet
    Route::middleware('access:disease-reports.manage')->group(function () {
        Route::get('/disease-reports', [DiseaseReportQueueController::class, 'index'])
            ->name('disease-reports.index');
    });

    // links an agent to the vets/advisers who handle their farmers' reports
    Route::middleware('access:officer-assignments.view')->group(function () {
        Route::get('/officer-assignments', [OfficerAssignmentController::class, 'index'])
            ->name('officer-assignments.index');
    });
    Route::middleware('access:officer-assignments.create')->group(function () {
        Route::post('/officer-assignments', [OfficerAssignmentController::class, 'store'])
            ->name('officer-assignments.store');
    });
    Route::middleware('access:officer-assignments.delete')->group(function () {
        Route::delete('/officer-assignments/{assignment}', [OfficerAssignmentController::class, 'destroy'])
            ->name('officer-assignments.destroy');
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
        Route::get('/communities/suggest-location', [CommunityController::class, 'suggestLocation'])->name('communities.suggest-location');
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

        // feeds the cascading group picker once a community is chosen
        Route::get('/farmer-groups/by-community/{community}', [FarmerGroupController::class, 'byCommunity'])
            ->name('farmer-groups.by-community');
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

    Route::middleware('access:marketplace-settings.view')->group(function () {
        Route::get('/marketplace/settings', [MarketplaceSettingController::class, 'index'])->name('marketplace.settings.index');
    });
    Route::middleware('access:marketplace-settings.update')->group(function () {
        Route::put('/marketplace/settings/{key}', [MarketplaceSettingController::class, 'update'])->name('marketplace.settings.update');
    });

    Route::middleware('access:marketplace-suppliers.view')->group(function () {
        Route::get('/marketplace/suppliers', [AdminSupplierController::class, 'index'])->name('marketplace.suppliers.index');
    });
    Route::middleware('access:marketplace-suppliers.suspend')->group(function () {
        Route::patch('/marketplace/suppliers/{supplier:uuid}/suspend', [AdminSupplierController::class, 'suspend'])->name('marketplace.suppliers.suspend');
        Route::patch('/marketplace/suppliers/{supplier:uuid}/restore', [AdminSupplierController::class, 'restore'])->name('marketplace.suppliers.restore');
    });

    Route::middleware('access:marketplace-kiosks.view')->group(function () {
        Route::get('/marketplace/kiosks', [AdminKioskController::class, 'index'])->name('marketplace.kiosks.index');
    });
    Route::middleware('access:marketplace-kiosks.approve')->group(function () {
        Route::patch('/marketplace/kiosks/{kiosk:uuid}/approve', [AdminKioskController::class, 'approve'])->name('marketplace.kiosks.approve');
    });
    Route::middleware('access:marketplace-kiosks.suspend')->group(function () {
        Route::patch('/marketplace/kiosks/{kiosk:uuid}/suspend', [AdminKioskController::class, 'suspend'])->name('marketplace.kiosks.suspend');
        Route::patch('/marketplace/kiosks/{kiosk:uuid}/restore', [AdminKioskController::class, 'restore'])->name('marketplace.kiosks.restore');
    });

    Route::middleware('access:marketplace-catalog.view')->group(function () {
        Route::get('/marketplace/catalog', [AdminCatalogProductController::class, 'index'])->name('marketplace.catalog.index');
        Route::get('/marketplace/suppliers/{supplier:uuid}/stock', [AdminSupplierController::class, 'stock'])->name('marketplace.suppliers.stock');
    });
    Route::middleware('access:marketplace-catalog.create')->group(function () {
        Route::post('/marketplace/catalog', [AdminCatalogProductController::class, 'store'])->name('marketplace.catalog.store');
    });
    Route::middleware('access:marketplace-catalog.merge')->group(function () {
        Route::patch('/marketplace/catalog/{catalogProduct:uuid}/merge', [AdminCatalogProductController::class, 'merge'])->name('marketplace.catalog.merge');
    });
    Route::middleware('access:marketplace-catalog.view')->group(function () {
        Route::delete('/marketplace/kiosk-products/{kioskProduct:uuid}/images/{image}', [AdminCatalogProductController::class, 'destroyImage'])->name('marketplace.kiosk-products.images.destroy');
    });

    // reuses the kiosk-suspend permission rather than a new "reports" permission group,
    // since resolving/extending a report is the same admin responsibility as suspending
    Route::middleware('access:marketplace-kiosks.view')->group(function () {
        Route::get('/marketplace/kiosk-reports', [\App\Http\Controllers\Admin\KioskReportController::class, 'index'])->name('marketplace.kiosk-reports.index');
    });
    Route::middleware('access:marketplace-kiosks.suspend')->group(function () {
        Route::post('/marketplace/kiosk-reports/{kioskReport:uuid}/resolve', [\App\Http\Controllers\Admin\KioskReportController::class, 'resolve'])->name('marketplace.kiosk-reports.resolve');
        Route::post('/marketplace/kiosk-reports/{kioskReport:uuid}/extend', [\App\Http\Controllers\Admin\KioskReportController::class, 'extend'])->name('marketplace.kiosk-reports.extend');
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
    Route::middleware('access:farmers.create')->group(function () {
        Route::post('/farmers', [FarmerController::class, 'store'])->name('farmers.store');

        // declared before the {farmer} routes so the word pending is never read as a profile id
        Route::get('/farmers/pending/{user}', [FarmerController::class, 'complete'])->name('farmers.complete');
        Route::post('/farmers/pending/{user}', [FarmerController::class, 'storeComplete'])->name('farmers.complete.store');

        // a farmer who never acted on their first code needs another one
        Route::post('/farmers/{farmer}/resend', [FarmerController::class, 'resendActivation'])
            ->middleware('throttle:otp-request')
            ->name('farmers.resend');
    });

    Route::middleware('access:farmers.view')->group(function () {
        Route::get('/farmers', [FarmerController::class, 'index'])->name('farmers.index');
        Route::get('/farmers/{farmer}', [FarmerController::class, 'show'])->name('farmers.show');
    });

    Route::middleware('access:farmers.update')->group(function () {
        Route::put('/farmers/{farmer}', [FarmerController::class, 'update'])->name('farmers.update');
        Route::post('/farmers/{farmer}/identity', [FarmerController::class, 'storeIdentity'])->name('farmers.identity.store');
    });

    // kept apart from editing, since verifying opens credit scoring and bank facing reports
    Route::middleware('access:farmers.verify')->group(function () {
        Route::patch('/farmers/{farmer}/identity/verify', [FarmerController::class, 'verifyIdentity'])->name('farmers.identity.verify');
    });

    // every unit across the farmers this person can reach
    Route::middleware('access:farm-units.view')->group(function () {
        Route::get('/farm-units', [FarmUnitController::class, 'all'])->name('farm-units.all');
    });

    Route::middleware('access:farm-units.create')->group(function () {
        Route::post('/farm-units', [FarmUnitController::class, 'storeFromList'])->name('farm-units.all.store');
    });

    // farm units under one farmer
    Route::middleware('access:farm-units.view')->group(function () {
        Route::get('/farmers/{farmer}/units', [FarmUnitController::class, 'index'])->name('farm-units.index');
    });

    Route::middleware('access:farm-units.create')->group(function () {
        Route::post('/farmers/{farmer}/units', [FarmUnitController::class, 'store'])->name('farm-units.store');
    });

    Route::middleware('access:farm-units.update')->group(function () {
        Route::put('/farmers/{farmer}/units/{farmUnit}', [FarmUnitController::class, 'update'])->name('farm-units.update');
    });

    Route::middleware('access:farm-units.approve')->group(function () {
        Route::patch('/farmers/{farmer}/units/{farmUnit}/approve', [FarmUnitController::class, 'approve'])->name('farm-units.approve');
    });

    // what is in each unit
    Route::middleware('access:farm-units.view')->group(function () {
        Route::get('/farmers/{farmer}/units/{farmUnit}/stocks', [FarmUnitStockController::class, 'index'])->name('farm-units.stocks.index');
    });

    Route::middleware('access:farm-units.create')->group(function () {
        Route::post('/farmers/{farmer}/units/{farmUnit}/stocks', [FarmUnitStockController::class, 'storeStock'])->name('farm-units.stocks.store');
        Route::post('/farmers/{farmer}/units/{farmUnit}/stocks/{stock}/movements', [FarmUnitStockController::class, 'storeMovement'])->name('farm-units.movements.store');
    });

    Route::middleware('access:farm-units.confirm')->group(function () {
        Route::patch('/farmers/{farmer}/units/{farmUnit}/stocks/{stock}/confirm', [FarmUnitStockController::class, 'confirmStock'])->name('farm-units.stocks.confirm');
        Route::patch('/farmers/{farmer}/units/{farmUnit}/stocks/{stock}/movements/{movement}/confirm', [FarmUnitStockController::class, 'confirmMovement'])->name('farm-units.movements.confirm');
        Route::patch('/farmers/{farmer}/units/{farmUnit}/stocks/{stock}/reject', [FarmUnitStockController::class, 'rejectStock'])->name('farm-units.stocks.reject');
        Route::patch('/farmers/{farmer}/units/{farmUnit}/stocks/{stock}/movements/{movement}/reject', [FarmUnitStockController::class, 'rejectMovement'])->name('farm-units.movements.reject');
    });

    // everything waiting on somebody, in one list
    Route::middleware('access:approvals.view')->group(function () {
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    });

    Route::middleware('access:transactions.reverse-approve')->group(function () {
        Route::patch('/reversals/{reversal}/approve', [ReversalController::class, 'approve'])->name('reversals.approve');
        Route::patch('/reversals/{reversal}/reject', [ReversalController::class, 'reject'])->name('reversals.reject');
    });

    // recording on a farmer's behalf
    Route::middleware('access:transactions.view')->group(function () {
        Route::get('/farmers/{farmer}/records', [RecordTransactionController::class, 'index'])->name('records.index');
        Route::get('/farmers/{farmer}/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/farmers/{farmer}/reports/print', [ReportController::class, 'print'])->name('reports.print');
        Route::get('/farmers/{farmer}/reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
        Route::get('/farmers/{farmer}/reports/csv', [ReportController::class, 'csv'])->name('reports.csv');
    });

    Route::middleware('access:transactions.create')->group(function () {
        Route::get('/farmers/{farmer}/records/create', [RecordTransactionController::class, 'create'])->name('records.create');
        Route::post('/farmers/{farmer}/records', [RecordTransactionController::class, 'store'])->name('records.store');
        Route::post('/farmers/{farmer}/records/{transaction}/settle', [RecordTransactionController::class, 'settle'])->name('records.settle');
    });

    Route::middleware('access:transactions.reverse-request')->group(function () {
        Route::post('/farmers/{farmer}/records/{transaction}/cancel', [ReversalController::class, 'store'])->name('records.cancel');
    });
});
