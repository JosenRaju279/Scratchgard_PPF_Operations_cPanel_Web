<?php

use App\Http\Controllers\ApiClientController;
use App\Http\Controllers\ApplicatorRegistrationController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PluginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/install',[InstallerController::class,'index'])->name('install.index');
Route::post('/install',[InstallerController::class,'install'])->name('install.run');

Route::middleware('installed')->group(function(){
    Route::get('/',[HomeController::class,'index'])->name('home');
    Route::get('/track/{token}',[PublicTrackingController::class,'show'])->middleware('throttle:120,1')->name('tracking.show');
    Route::middleware('guest')->group(function(){
        Route::get('/login',[AuthController::class,'showLogin'])->name('login');
        Route::post('/login',[AuthController::class,'login'])->name('login.attempt');

        Route::get('/super-admin/login',[AuthController::class,'showLogin'])->defaults('portal','super_admin')->name('login.super-admin');
        Route::post('/super-admin/login',[AuthController::class,'login'])->defaults('portal','super_admin')->name('login.super-admin.attempt');
        Route::get('/delegator/login',[AuthController::class,'showLogin'])->defaults('portal','work_delegator')->name('login.delegator');
        Route::post('/delegator/login',[AuthController::class,'login'])->defaults('portal','work_delegator')->name('login.delegator.attempt');
        Route::get('/zonal-manager/login',[AuthController::class,'showLogin'])->defaults('portal','zonal_manager')->name('login.zonal-manager');
        Route::post('/zonal-manager/login',[AuthController::class,'login'])->defaults('portal','zonal_manager')->name('login.zonal-manager.attempt');
        Route::get('/applicator/login',[AuthController::class,'showLogin'])->defaults('portal','applicator')->name('login.applicator');
        Route::post('/applicator/login',[AuthController::class,'login'])->defaults('portal','applicator')->name('login.applicator.attempt');
        Route::get('/showroom/login',[AuthController::class,'showLogin'])->defaults('portal','external_verifier')->name('login.showroom');
        Route::post('/showroom/login',[AuthController::class,'login'])->defaults('portal','external_verifier')->name('login.showroom.attempt');
        Route::get('/finance/login',[AuthController::class,'showLogin'])->defaults('portal','finance')->name('login.finance');
        Route::post('/finance/login',[AuthController::class,'login'])->defaults('portal','finance')->name('login.finance.attempt');
        Route::get('/applicator/register',[ApplicatorRegistrationController::class,'create'])->name('applicator.register');
        Route::get('/applicator/register/zone',[ApplicatorRegistrationController::class,'resolveZone'])->name('applicator.register.zone');
        Route::get('/applicator/register/username-availability',[ApplicatorRegistrationController::class,'usernameAvailability'])->name('applicator.register.username');
        Route::post('/applicator/register',[ApplicatorRegistrationController::class,'store'])->name('applicator.register.store');
        Route::get('/applicator/register/verify',[ApplicatorRegistrationController::class,'verifyForm'])->name('applicator.register.verify');
        Route::post('/applicator/register/verify',[ApplicatorRegistrationController::class,'verify'])->name('applicator.register.verify.store');
        Route::post('/applicator/register/resend',[ApplicatorRegistrationController::class,'resend'])->name('applicator.register.resend');
    });

    Route::middleware('auth')->group(function(){
        Route::post('/logout',[AuthController::class,'logout'])->name('logout');
        Route::get('/dashboard',[DashboardController::class,'index'])->name('dashboard');
        Route::get('/profile',[ProfileController::class,'edit'])->name('profile.edit');
        Route::get('/profile/zone',[ProfileController::class,'resolveZone'])->name('profile.zone');
        Route::get('/profile/username-availability',[ProfileController::class,'usernameAvailability'])->name('profile.username');
        Route::put('/profile',[ProfileController::class,'update'])->name('profile.update');
        Route::post('/profile/verify',[ProfileController::class,'verifyContact'])->name('profile.verify');

        Route::get('/work-orders',[WorkOrderController::class,'index'])->name('work-orders.index');
        Route::get('/work-orders/create',[WorkOrderController::class,'create'])->middleware('permission:work.create')->name('work-orders.create');
        Route::post('/work-orders',[WorkOrderController::class,'store'])->middleware('permission:work.create')->name('work-orders.store');
        Route::get('/work-orders/{workOrder}',[WorkOrderController::class,'show'])->name('work-orders.show');
        Route::post('/work-orders/{workOrder}/delegate',[WorkOrderController::class,'delegate'])->middleware('permission:work.delegate')->name('work-orders.delegate');
        Route::post('/work-orders/{workOrder}/assign',[WorkOrderController::class,'assign'])->middleware('permission:work.assign_applicator')->name('work-orders.assign');
        Route::post('/work-orders/{workOrder}/verifier',[WorkOrderController::class,'assignVerifier'])->middleware('permission:work.assign_verifier')->name('work-orders.verifier');
        Route::post('/work-orders/{workOrder}/start',[WorkOrderController::class,'start'])->name('work-orders.start');
        Route::post('/work-orders/{workOrder}/submit',[WorkOrderController::class,'submit'])->name('work-orders.submit');
        Route::post('/work-orders/{workOrder}/review-otp',[WorkOrderController::class,'reviewOtp'])->middleware('throttle:3,10')->name('work-orders.review-otp');
        Route::post('/work-orders/{workOrder}/review',[WorkOrderController::class,'review'])->name('work-orders.review');
        Route::post('/work-orders/{workOrder}/tracking/regenerate',[WorkOrderController::class,'regenerateTracking'])->name('work-orders.tracking.regenerate');
        Route::post('/work-orders/{workOrder}/tracking/toggle',[WorkOrderController::class,'toggleTracking'])->name('work-orders.tracking.toggle');
        Route::get('/evidence',[EvidenceController::class,'index'])->name('evidence.index');
        Route::post('/work-orders/{workOrder}/evidence',[EvidenceController::class,'store'])->name('evidence.store');
        Route::post('/work-orders/{workOrder}/check-in',[EvidenceController::class,'checkin'])->name('checkins.store');
        Route::get('/evidence/{evidence}',[EvidenceController::class,'view'])->name('evidence.view');
        Route::post('/work-orders/{workOrder}/messages',[MessageController::class,'store'])->name('messages.store');

        Route::get('/users',[UserManagementController::class,'index'])->middleware('permission:users.manage')->name('users.index');
        Route::post('/users',[UserManagementController::class,'store'])->middleware('permission:users.manage')->name('users.store');
        Route::get('/users/{user}/edit',[UserManagementController::class,'edit'])->middleware('permission:users.manage')->name('users.edit');
        Route::put('/users/{user}',[UserManagementController::class,'update'])->middleware('permission:users.manage')->name('users.update');
        Route::delete('/users/{user}',[UserManagementController::class,'destroy'])->middleware('permission:users.manage')->name('users.delete');

        Route::get('/masters',[MasterDataController::class,'index'])->middleware('permission:masters.manage')->name('masters.index');
        Route::post('/masters/zones',[MasterDataController::class,'zoneStore'])->middleware('permission:masters.manage')->name('masters.zones.store');
        Route::put('/masters/zones/{zone}',[MasterDataController::class,'zoneUpdate'])->middleware('permission:masters.manage')->name('masters.zones.update');
        Route::delete('/masters/zones/{zone}',[MasterDataController::class,'zoneDelete'])->middleware('permission:masters.manage')->name('masters.zones.delete');
        Route::get('/masters/locations',[MasterDataController::class,'locationOptions'])->middleware('permission:masters.manage')->name('masters.locations');
        Route::get('/masters/postal-lookup',[MasterDataController::class,'postalLookup'])->middleware('permission:masters.manage')->name('masters.postal.lookup');
        Route::post('/masters/postal-import',[MasterDataController::class,'postalImport'])->middleware('permission:masters.manage')->name('masters.postal.import');
        Route::get('/masters/coverage-preview',[MasterDataController::class,'coveragePreview'])->middleware('permission:masters.manage')->name('masters.coverage.preview');
        Route::post('/masters/coverage',[MasterDataController::class,'coverageStore'])->middleware('permission:masters.manage')->name('masters.coverage.store');
        Route::delete('/masters/coverage/{rule}',[MasterDataController::class,'coverageDelete'])->middleware('permission:masters.manage')->name('masters.coverage.delete');
        Route::delete('/masters/zone-mappings/{batch}',[MasterDataController::class,'zoneMappingDelete'])->middleware('permission:masters.manage')->name('masters.zone-mappings.delete');
        Route::post('/masters/pincodes',[MasterDataController::class,'pincodeStore'])->middleware('permission:masters.manage')->name('masters.pincodes.store');
        Route::post('/masters/pincodes/import',[MasterDataController::class,'pincodeImport'])->middleware('permission:masters.manage')->name('masters.pincodes.import');
        Route::post('/masters/vendors',[MasterDataController::class,'vendorStore'])->middleware('permission:masters.manage')->name('masters.vendors.store');
        Route::post('/masters/showrooms',[MasterDataController::class,'showroomStore'])->middleware('permission:masters.manage')->name('masters.showrooms.store');

        Route::get('/kyc',[KycController::class,'index'])->middleware('permission:kyc.review')->name('kyc.index');
        Route::post('/kyc/{kyc}/review',[KycController::class,'review'])->middleware('permission:kyc.review')->name('kyc.review');

        Route::get('/configuration',[ConfigurationController::class,'index'])->middleware('permission:settings.manage')->name('configuration.index');
        Route::post('/configuration/reasons',[ConfigurationController::class,'reasonStore'])->middleware('permission:reasons.manage')->name('configuration.reasons.store');
        Route::post('/configuration/pricing',[ConfigurationController::class,'pricingStore'])->middleware('permission:pricing.manage')->name('configuration.pricing.store');
        Route::post('/configuration/templates',[ConfigurationController::class,'templateStore'])->middleware('permission:evidence_templates.manage')->name('configuration.templates.store');
        Route::post('/configuration/templates/{template}/slots',[ConfigurationController::class,'slotStore'])->middleware('permission:evidence_templates.manage')->name('configuration.slots.store');

        Route::get('/complaints',[ComplaintController::class,'index'])->middleware('permission:complaints.manage')->name('complaints.index');
        Route::post('/work-orders/{workOrder}/complaints',[ComplaintController::class,'store'])->middleware('permission:complaints.manage')->name('complaints.store');
        Route::post('/complaints/{complaint}/status',[ComplaintController::class,'status'])->middleware('permission:complaints.manage')->name('complaints.status');
        Route::post('/complaints/{complaint}/reworks',[ComplaintController::class,'reworkStore'])->middleware('permission:complaints.manage')->name('reworks.store');
        Route::post('/reworks/{rework}',[ComplaintController::class,'reworkUpdate'])->middleware('permission:complaints.manage')->name('reworks.update');
        Route::post('/reworks/{rework}/scope',[ComplaintController::class,'changeScope'])->middleware('permission:work.change_rework_scope')->name('reworks.scope');

        Route::get('/tickets',[TicketController::class,'index'])->middleware('permission:tickets.view')->name('tickets.index');
        Route::post('/tickets',[TicketController::class,'store'])->middleware('permission:tickets.view')->name('tickets.store');
        Route::get('/tickets/{ticket}',[TicketController::class,'show'])->middleware('permission:tickets.view')->name('tickets.show');
        Route::post('/tickets/{ticket}/messages',[TicketController::class,'message'])->middleware('permission:tickets.view')->name('tickets.message');
        Route::get('/tickets/{ticket}/messages/{message}/attachments/{index}',[TicketController::class,'attachment'])->middleware('permission:tickets.view')->whereNumber('index')->name('tickets.attachment');
        Route::post('/tickets/{ticket}/status',[TicketController::class,'status'])->middleware('permission:tickets.view')->name('tickets.status');

        Route::get('/payments',[PaymentController::class,'index'])->middleware('permission:payment.view')->name('payments.index');
        Route::post('/payments/{payment}/paid',[PaymentController::class,'markPaid'])->middleware('permission:payment.manage')->name('payments.paid');
        Route::post('/payments/{payment}/revert',[PaymentController::class,'revert'])->middleware('permission:payment.revert')->name('payments.revert');
        Route::get('/payments-export.csv',[PaymentController::class,'export'])->middleware('permission:payment.view')->name('payments.export');

        Route::get('/audit',[AuditController::class,'index'])->middleware('permission:audit.view')->name('audit.index');
        Route::get('/settings',[SettingsController::class,'index'])->middleware('permission:settings.manage')->name('settings.index');
        Route::post('/settings',[SettingsController::class,'update'])->middleware('permission:settings.manage')->name('settings.update');
        Route::post('/settings/test-mail',[SettingsController::class,'testMail'])->middleware('permission:settings.manage')->name('settings.test-mail');

        Route::middleware('superadmin')->prefix('/plugins')->group(function(){
            Route::get('/',[PluginController::class,'index'])->name('plugins.index');
            Route::get('/builder',[PluginController::class,'builder'])->name('plugins.builder');
            Route::post('/builder/starter',[PluginController::class,'downloadStarter'])->name('plugins.builder.starter');
            Route::post('/',[PluginController::class,'install'])->name('plugins.install');
            Route::get('/{plugin}',[PluginController::class,'show'])->name('plugins.show');
            Route::post('/{plugin}/enable',[PluginController::class,'enable'])->name('plugins.enable');
            Route::post('/{plugin}/disable',[PluginController::class,'disable'])->name('plugins.disable');
            Route::get('/{plugin}/settings',[PluginController::class,'settings'])->name('plugins.settings');
            Route::post('/{plugin}/settings',[PluginController::class,'saveSettings'])->name('plugins.settings.save');
            Route::post('/{plugin}/migrate',[PluginController::class,'migrate'])->name('plugins.migrate');
            Route::delete('/{plugin}',[PluginController::class,'destroy'])->name('plugins.delete');
        });

        Route::get('/api-clients',[ApiClientController::class,'index'])->middleware('permission:api_clients.manage')->name('api-clients.index');
        Route::post('/api-clients',[ApiClientController::class,'store'])->middleware('permission:api_clients.manage')->name('api-clients.store');
        Route::post('/api-clients/{client}/toggle',[ApiClientController::class,'toggle'])->middleware('permission:api_clients.manage')->name('api-clients.toggle');
        Route::delete('/api-clients/{client}',[ApiClientController::class,'destroy'])->middleware('permission:api_clients.manage')->name('api-clients.delete');
    });
});
