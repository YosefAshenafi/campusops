<?php

use think\facade\Route;

// API v1 routes
Route::group('api/v1', function () {
    
    // Apply rate limiting to all routes
    Route::middleware('rate_limit');

    // Health check - no auth required
    Route::get('ping', 'Index/ping');

    // Auth routes - no auth required
    Route::post('auth/login', 'AuthController/login');

    // Auth routes - require authentication
    Route::group('', function () {
        Route::post('auth/logout', 'AuthController/logout');
        Route::post('auth/unlock', 'AuthController/unlock')
            ->middleware('rbac', 'users.password');

        // User management routes (with sensitive data masking)
        Route::group('users', function () {
            Route::get('', 'UserController/index')
                ->middleware('rbac', 'users.read')
                ->middleware('sensitive_data');
            Route::get('/:id', 'UserController/show')
                ->middleware('rbac', 'users.read')
                ->middleware('sensitive_data');
            Route::post('', 'UserController/create')
                ->middleware('rbac', 'users.create');
            Route::put('/:id', 'UserController/update')
                ->middleware('rbac', 'users.update');
            Route::delete('/:id', 'UserController/delete')
                ->middleware('rbac', 'users.delete');
            Route::put('/:id/role', 'UserController/changeRole')
                ->middleware('rbac', 'users.update');
            Route::put('/:id/password', 'UserController/resetPassword')
                ->middleware('rbac', 'users.password');
        });

        // Activity management routes
        Route::group('activities', function () {
            Route::get('', 'ActivityController/index')
                ->middleware('rbac', 'activities.read');
            Route::get('/:id', 'ActivityController/show')
                ->middleware('rbac', 'activities.read');
            Route::get('/:id/versions', 'ActivityController/versions')
                ->middleware('rbac', 'activities.read');
            Route::get('/:id/signups', 'ActivityController/signups')
                ->middleware('rbac', 'activities.read');
            Route::get('/:id/change-log', 'ActivityController/changeLog')
                ->middleware('rbac', 'activities.read');
            Route::post('', 'ActivityController/create')
                ->middleware('rbac', 'activities.create');
            Route::put('/:id', 'ActivityController/update')
                ->middleware('rbac', 'activities.update');
            Route::post('/:id/publish', 'ActivityController/publish')
                ->middleware('rbac', 'activities.publish');
            Route::post('/:id/start', 'ActivityController/start')
                ->middleware('rbac', 'activities.transition');
            Route::post('/:id/complete', 'ActivityController/complete')
                ->middleware('rbac', 'activities.transition');
            Route::post('/:id/archive', 'ActivityController/archive')
                ->middleware('rbac', 'activities.transition');
            Route::post('/:id/signups', 'ActivityController/signup')
                ->middleware('rbac', 'activities.signup');
            Route::delete('/:id/signups/:signup_id', 'ActivityController/cancelSignup')
                ->middleware('rbac', 'activities.signup');
            Route::post('/:id/signups/:signup_id/acknowledge', 'ActivityController/acknowledge')
                ->middleware('rbac', 'activities.signup');
        });

        // Order management routes (with sensitive data masking)
        Route::group('orders', function () {
            Route::get('', 'OrderController/index')
                ->middleware('rbac', 'orders.read')
                ->middleware('sensitive_data');
            Route::get('/:id', 'OrderController/show')
                ->middleware('rbac', 'orders.read')
                ->middleware('sensitive_data');
            Route::get('/:id/history', 'OrderController/history')
                ->middleware('rbac', 'orders.read');
            Route::post('', 'OrderController/create')
                ->middleware('rbac', 'orders.create');
            Route::put('/:id', 'OrderController/update')
                ->middleware('rbac', 'orders.update');
            Route::post('/:id/initiate-payment', 'OrderController/initiatePayment')
                ->middleware('rbac', 'orders.payment');
            Route::post('/:id/confirm-payment', 'OrderController/confirmPayment')
                ->middleware('rbac', 'orders.payment');
            Route::post('/:id/start-ticketing', 'OrderController/startTicketing')
                ->middleware('rbac', 'orders.ticketing');
            Route::post('/:id/ticket', 'OrderController/ticket')
                ->middleware('rbac', 'orders.ticketing');
            Route::post('/:id/refund', 'OrderController/refund')
                ->middleware('rbac', 'orders.refund');
            Route::post('/:id/cancel', 'OrderController/cancel')
                ->middleware('rbac', 'orders.cancel');
            Route::post('/:id/close', 'OrderController/close')
                ->middleware('rbac', 'orders.close');
            Route::put('/:id/address', 'OrderController/updateAddress')
                ->middleware('rbac', 'orders.update');
            Route::post('/:id/request-address-correction', 'OrderController/requestAddressCorrection')
                ->middleware('rbac', 'orders.request_correction');
            Route::post('/:id/approve-address-correction', 'OrderController/approveAddressCorrection')
                ->middleware('rbac', 'orders.approve');
        });

        // Shipment routes
        Route::group('orders/:order_id/shipments', function () {
            Route::get('', 'ShipmentController/index')
                ->middleware('rbac', 'shipments.read');
            Route::post('', 'ShipmentController/create')
                ->middleware('rbac', 'shipments.create');
        });

        Route::group('shipments', function () {
            Route::get('', 'ShipmentController/listAll')
                ->middleware('rbac', 'shipments.read');
            Route::get('/:id', 'ShipmentController/show')
                ->middleware('rbac', 'shipments.read');
            Route::post('/:id/scan', 'ShipmentController/scan')
                ->middleware('rbac', 'shipments.update');
            Route::get('/:id/scan-history', 'ShipmentController/scanHistory')
                ->middleware('rbac', 'shipments.read');
            Route::post('/:id/confirm-delivery', 'ShipmentController/confirmDelivery')
                ->middleware('rbac', 'shipments.deliver');
            Route::get('/:id/exceptions', 'ShipmentController/exceptions')
                ->middleware('rbac', 'shipments.read');
            Route::post('/:id/exceptions', 'ShipmentController/reportException')
                ->middleware('rbac', 'shipments.exception');
        });

        // Violation management routes
        Route::group('violations', function () {
            Route::get('rules', 'ViolationController/rules')
                ->middleware('rbac', 'violations.read');
            Route::get('rules/:id', 'ViolationController/ruleShow')
                ->middleware('rbac', 'violations.read');
            Route::post('rules', 'ViolationController/ruleCreate')
                ->middleware('rbac', 'violations.rules');
            Route::put('rules/:id', 'ViolationController/ruleUpdate')
                ->middleware('rbac', 'violations.rules');
            Route::delete('rules/:id', 'ViolationController/ruleDelete')
                ->middleware('rbac', 'violations.rules');
            Route::get('', 'ViolationController/index')
                ->middleware('rbac', 'violations.read');
            Route::get('/:id', 'ViolationController/show')
                ->middleware('rbac', 'violations.read');
            Route::post('', 'ViolationController/create')
                ->middleware('rbac', 'violations.create');
            Route::get('user/:user_id', 'ViolationController/userViolations')
                ->middleware('rbac', 'violations.read');
            Route::get('group/:group_id', 'ViolationController/groupViolations')
                ->middleware('rbac', 'violations.read');
            Route::post('/:id/appeal', 'ViolationController/appeal')
                ->middleware('rbac', 'violations.appeal');
            Route::post('/:id/review', 'ViolationController/review')
                ->middleware('rbac', 'violations.review');
            Route::post('/:id/final-decision', 'ViolationController/finalDecision')
                ->middleware('rbac', 'violations.review');
        });

        // File upload routes
        Route::group('upload', function () {
            Route::post('', 'UploadController/upload')
                ->middleware('rbac', 'uploads.create');
            Route::get('/:id', 'UploadController/show')
                ->middleware('rbac', 'files.read');
            Route::get('/:id/download', 'UploadController/download')
                ->middleware('rbac', 'files.read');
            Route::delete('/:id', 'UploadController/delete')
                ->middleware('rbac', 'uploads.delete');
        });

        // Task routes
        Route::group('activities/:activity_id/tasks', function () {
            Route::get('', 'TaskController/index')
                ->middleware('rbac', 'tasks.read');
            Route::post('', 'TaskController/create')
                ->middleware('rbac', 'tasks.create');
        });

        Route::group('tasks', function () {
            Route::put('/:id', 'TaskController/update')
                ->middleware('rbac', 'tasks.update');
            Route::put('/:id/status', 'TaskController/updateStatus')
                ->middleware('rbac', 'tasks.update');
            Route::delete('/:id', 'TaskController/delete')
                ->middleware('rbac', 'tasks.delete');
        });

        // Checklist routes
        Route::group('activities/:activity_id/checklists', function () {
            Route::get('', 'ChecklistController/index')
                ->middleware('rbac', 'checklists.read');
            Route::post('', 'ChecklistController/create')
                ->middleware('rbac', 'tasks.create');
        });

        Route::group('checklists', function () {
            Route::put('/:id', 'ChecklistController/update')
                ->middleware('rbac', 'tasks.update');
            Route::delete('/:id', 'ChecklistController/delete')
                ->middleware('rbac', 'tasks.delete');
            Route::post('/:id/items/:item_id/complete', 'ChecklistController/completeItem')
                ->middleware('rbac', 'checklists.update');
        });

        // Staffing routes
        Route::group('activities/:activity_id/staffing', function () {
            Route::get('', 'StaffingController/index')
                ->middleware('rbac', 'staffing.read');
            Route::post('', 'StaffingController/create')
                ->middleware('rbac', 'staffing.create');
        });

        Route::group('staffing', function () {
            Route::put('/:id', 'StaffingController/update')
                ->middleware('rbac', 'staffing.update');
            Route::delete('/:id', 'StaffingController/delete')
                ->middleware('rbac', 'staffing.delete');
        });

        // Search routes
        Route::group('search', function () {
            Route::get('', 'SearchController/index')
                ->middleware('rbac', 'search.read');
            Route::get('suggest', 'SearchController/suggest')
                ->middleware('rbac', 'search.read');
            Route::get('logistics', 'SearchController/logistics')
                ->middleware('rbac', 'search.read');
        });

        Route::group('index', function () {
            Route::get('status', 'SearchController/status')
                ->middleware('rbac', 'index.manage');
            Route::post('rebuild', 'SearchController/rebuild')
                ->middleware('rbac', 'index.manage');
            Route::post('cleanup', 'SearchController/cleanup')
                ->middleware('rbac', 'index.manage');
        });

        // Notification routes
        Route::group('notifications', function () {
            Route::get('', 'NotificationController/index')
                ->middleware('rbac', 'notifications.read');
            Route::put('/:id/read', 'NotificationController/markRead')
                ->middleware('rbac', 'notifications.read');
            Route::get('settings', 'NotificationController/settings')
                ->middleware('rbac', 'notifications.read');
            Route::put('settings', 'NotificationController/updateSettings')
                ->middleware('rbac', 'notifications.read');
        });

        // Preferences routes
        Route::group('preferences', function () {
            Route::get('', 'PreferenceController/index')
                ->middleware('rbac', 'preferences.read');
            Route::put('', 'PreferenceController/update')
                ->middleware('rbac', 'preferences.update');
        });

        // Recommendation routes
        Route::group('recommendations', function () {
            Route::get('', 'RecommendationController/index')
                ->middleware('rbac', 'activities.read');
            Route::get('popular', 'RecommendationController/popular')
                ->middleware('rbac', 'activities.read');
            Route::get('orders', 'RecommendationController/orders')
                ->middleware('rbac', 'orders.read');
        });

        // Dashboard routes
        Route::group('dashboard', function () {
            Route::get('', 'DashboardController/index')
                ->middleware('rbac', 'dashboard.read');
            Route::get('custom', 'DashboardController/custom')
                ->middleware('rbac', 'dashboard.read');
            Route::post('custom', 'DashboardController/createCustom')
                ->middleware('rbac', 'dashboard.create');
            Route::put('custom/:id', 'DashboardController/updateCustom')
                ->middleware('rbac', 'dashboard.update');
            Route::delete('custom', 'DashboardController/deleteCustom')
                ->middleware('rbac', 'dashboard.update');
            Route::get('favorites', 'DashboardController/favorites')
                ->middleware('rbac', 'dashboard.read');
            Route::post('favorites', 'DashboardController/addFavorite')
                ->middleware('rbac', 'dashboard.update');
            Route::delete('favorites/:widget_id', 'DashboardController/removeFavorite')
                ->middleware('rbac', 'dashboard.update');
            Route::get('drill/:widget_id', 'DashboardController/drill')
                ->middleware('rbac', 'dashboard.read');
            Route::get('snapshot', 'DashboardController/snapshot')
                ->middleware('rbac', 'dashboard.export');
        });

        // Export routes
        Route::group('export', function () {
            Route::get('orders', 'ExportController/orders')
                ->middleware('rbac', 'dashboard.export');
            Route::get('activities', 'ExportController/activities')
                ->middleware('rbac', 'dashboard.export');
            Route::get('violations', 'ExportController/violations')
                ->middleware('rbac', 'dashboard.export');
            Route::get('download', 'ExportController/download')
                ->middleware('rbac', 'dashboard.export');
        });

        // Audit trail routes
        Route::group('audit', function () {
            Route::get('', 'AuditController/index')
                ->middleware('rbac', 'audit.read');
        });
    })->middleware('auth');

})->allowCrossDomain();
