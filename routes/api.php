<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AssetTypeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\DashboardManagementController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\EmailTemplateWrapperController;

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessTierController;
use App\Http\Controllers\BusinessTimesController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\CustomWebhookController;
use App\Http\Controllers\DashboardManagementControllerV2;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\DropdownOptionsController;
use App\Http\Controllers\EmployeeRotaController;
use App\Http\Controllers\EmploymentStatusController;
use App\Http\Controllers\FileManagementController;
use App\Http\Controllers\HistoryDetailsController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\JobListingController;
use App\Http\Controllers\JobPlatformController;
use App\Http\Controllers\JobTypeController;

use App\Http\Controllers\LeaveController;

use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotificationController;


use App\Http\Controllers\NotificationTemplateController;
use App\Http\Controllers\PaymentTypeController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrunController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RecruitmentProcessController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\ServicePlanController;
use App\Http\Controllers\SettingAttendanceController;
use App\Http\Controllers\SettingLeaveController;
use App\Http\Controllers\SettingLeaveTypeController;
use App\Http\Controllers\SettingPaymentDateController;
use App\Http\Controllers\SettingPayrollController;
use App\Http\Controllers\SocialSiteController;
use App\Http\Controllers\SystemSettingController;

use App\Http\Controllers\UserAddressHistoryController;
use App\Http\Controllers\UserAssetController;
use App\Http\Controllers\UserDocumentController;
use App\Http\Controllers\UserEducationHistoryController;
use App\Http\Controllers\UserJobHistoryController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\UserNoteController;
use App\Http\Controllers\UserPassportHistoryController;
use App\Http\Controllers\UserPayslipController;
use App\Http\Controllers\UserPensionHistoryController;
use App\Http\Controllers\UserRecruitmentProcessController;
use App\Http\Controllers\UserRightToWorkHistoryController;
use App\Http\Controllers\UserSocialSiteController;
use App\Http\Controllers\UserSponsorshipHistoryController;
use App\Http\Controllers\UserVisaHistoryController;
use App\Http\Controllers\WorkLocationController;
use App\Http\Controllers\WorkShiftController;
use App\Http\Controllers\WorkShiftHistoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider jistoryin a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Define route for GET method
Route::get('/health', function () {
    return response()->json(['status' => 'Server is up and running'], 200);
});

// Define route for POST method
Route::post('/health', function () {
    return response()->json(['status' => 'Server is up and running'], 200);
});

// Define route for PUT method
Route::put('/health', function () {
    return response()->json(['status' => 'Server is up and running'], 200);
});

// Define route for DELETE method
Route::delete('/health', function () {
    return response()->json(['status' => 'Server is up and running'], 200);
});

// Define route for PATCH method
Route::patch('/health', function () {
    return response()->json(['status' => 'Server is up and running'], 200);
});









Route::post('/v1.0/files/single-file-upload', [FileManagementController::class, "createFileSingle"]);

Route::post('/v1.0/files/multiple-file-upload', [FileManagementController::class, "createFileMultiple"]);





Route::post('/v1.0/register', [AuthController::class, "register"]);
Route::post('/v1.0/login', [AuthController::class, "login"]);
Route::post('/v2.0/login', [AuthController::class, "loginV2"]);

Route::post('/v1.0/token-regenerate', [AuthController::class, "regenerateToken"]);

Route::post('/forgetpassword', [AuthController::class, "storeToken"]);
Route::post('/v2.0/forgetpassword', [AuthController::class, "storeTokenV2"]);

Route::post('/resend-email-verify-mail', [AuthController::class, "resendEmailVerifyToken"]);

Route::patch('/forgetpassword/reset/{token}', [AuthController::class, "changePasswordByToken"]);
Route::post('/auth/check/email', [AuthController::class, "checkEmail"]);

Route::post('/auth/check/business/email', [AuthController::class, "checkBusinessEmail"]);









// !!!!!!!@@@@@@@@@@@@$$$$$$$$$$$$%%%%%%%%%%%%%%%%^^^^^^^^^^
// Protected Routes
// !!!!!!!@@@@@@@@@@@@$$$$$$$$$$$$%%%%%%%%%%%%%%%%^^^^^^^^^^

Route::middleware(['auth:api'])->group(function () {

Route::post('/v2.0/files/single-file-upload', [FileManagementController::class, "createFileSingleV2"]);
Route::post('/v2.0/files/multiple-file-upload', [FileManagementController::class, "createFileMultipleV2"]);
Route::get('/v1.0/file/{filename}', [FileManagementController::class, "getFile"]);

    Route::post('/v1.0/logout', [AuthController::class, "logout"]);
    Route::get('/v1.0/user', [AuthController::class, "getUser"]);
    Route::get('/v2.0/user', [AuthController::class, "getUserV2"]);


    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// notification management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Route::get('/v1.0/notifications', [NotificationController::class, "getNotifications"]);

Route::get('/v1.0/notifications/{business_id}/{perPage}', [NotificationController::class, "getNotificationsByBusinessId"]);

Route::put('/v1.0/notifications/change-status', [NotificationController::class, "updateNotificationStatus"]);

Route::delete('/v1.0/notifications/{id}', [NotificationController::class, "deleteNotificationById"]);
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// notification management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@





// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// dashboard section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::get('/v1.0/superadmin-dashboard', [DashboardManagementController::class, "getSuperAdminDashboardData"]);
Route::post('/v1.0/dashboard-widgets', [DashboardManagementController::class, "createDashboardWidget"]);
Route::delete('/v1.0/dashboard-widgets/{ids}', [DashboardManagementController::class, "deleteDashboardWidgetsByIds"]);

Route::get('/v1.0/business-user-dashboard', [DashboardManagementController::class, "getBusinessUserDashboardData"]);



// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// end dashboard section
// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%



Route::get('/v2.0/business-manager-dashboard', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardData"]);









Route::get('/v1.0/business-manager-dashboard/sponsorship-expiries/{duration}', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataSponsorshipExpiries"]);


Route::get('/v1.0/business-manager-dashboard/right-to-work-expiries/{duration}', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataRightToWorkExpiries"]);


Route::get('/v1.0/business-manager-dashboard/visa-expiries/{duration}', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataVisaExpiries"]);

Route::get('/v1.0/business-manager-dashboard/passport-expiries/{duration}', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataPassportExpiries"]);


Route::get('/v1.0/business-manager-dashboard/pension-expiries/{duration}', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataPensionExpiries"]);


Route::get('/v1.0/business-manager-dashboard/combined-expiries', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataCombinedExpiries"]);





Route::get('/v1.0/business-manager-dashboard/holidays', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataHolidays"]);
Route::get('/v1.0/business-manager-dashboard/leaves', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataLeaves"]);



Route::get('/v1.0/business-manager-dashboard/leaves/{status}/{duration}', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataLeavesByStatus"]);

Route::get('/v1.0/business-manager-dashboard/holidays/{status}/{duration}', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataHolidaysByStatus"]);

Route::get('/v1.0/business-manager-dashboard/leaves-holidays', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataLeavesAndHolidays"]);








Route::get('/v1.0/business-manager-dashboard/present', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataPresent"]);

Route::get('/v1.0/business-manager-dashboard/absent', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataAbsent"]);






Route::get('/v1.0/business-manager-dashboard/open-roles/{duration}', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataOpenRoles"]);


Route::get('/v1.0/business-manager-dashboard/total-employee/{duration}', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataTotalEmployee"]);


Route::get('/v1.0/business-manager-dashboard/open-roles-and-total-employee', [DashboardManagementControllerV2::class, "getBusinessManagerDashboardDataOpenRolesAndTotalEmployee"]);

















// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// announcements  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/announcements', [AnnouncementController::class, "createAnnouncement"]);
Route::put('/v1.0/announcements', [AnnouncementController::class, "updateAnnouncement"]);
Route::get('/v1.0/announcements', [AnnouncementController::class, "getAnnouncements"]);
Route::get('/v1.0/announcements/{id}', [AnnouncementController::class, "getAnnouncementById"]);
Route::delete('/v1.0/announcements/{ids}', [AnnouncementController::class, "deleteAnnouncementsByIds"]);

Route::get('/v1.0/clients/announcements', [AnnouncementController::class, "getAnnouncementsClient"]);
Route::get('/v1.0/clients/announcements-count', [AnnouncementController::class, "getAnnouncementsCountClient"]);
Route::put('/v1.0/clients/announcements/change-status', [AnnouncementController::class, "updateAnnouncementStatus"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end announcements management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@


});




Route::middleware(['auth:api',"business.subscription.check"])->group(function () {





    Route::patch('/auth/changepassword', [AuthController::class, "changePassword"]);
    Route::put('/v1.0/update-user-info', [AuthController::class, "updateUserInfo"]);






// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// system  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::put('/v1.0/system-settings', [SystemSettingController::class, "updateSystemSetting"]);
Route::get('/v1.0/system-settings', [SystemSettingController::class, "getSystemSetting"]);



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end system management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@













// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// modules  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



Route::put('/v1.0/business-modules/enable', [ModuleController::class, "enableBusinessModule"]);

Route::get('/v1.0/business-modules/{business_id}', [ModuleController::class, "getBusinessModules"]);


Route::get('/v1.0/modules', [ModuleController::class, "getModules"]);


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end modules management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
//  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/business-tiers', [BusinessTierController::class, "createBusinessTier"]);
Route::put('/v1.0/business-tiers', [BusinessTierController::class, "updateBusinessTier"]);
Route::get('/v1.0/business-tiers', [BusinessTierController::class, "getBusinessTiers"]);
Route::get('/v1.0/business-tiers/{id}', [BusinessTierController::class, "getBusinessTierById"]);
Route::delete('/v1.0/business-tiers/{ids}', [BusinessTierController::class, "deleteBusinessTiersByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end job platform management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
//  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/service-plans', [ServicePlanController::class, "createServicePlan"]);
Route::put('/v1.0/service-plans', [ServicePlanController::class, "updateServicePlan"]);
Route::get('/v1.0/service-plans', [ServicePlanController::class, "getServicePlans"]);
Route::get('/v1.0/service-plans/{id}', [ServicePlanController::class, "getServicePlanById"]);
Route::delete('/v1.0/service-plans/{ids}', [ServicePlanController::class, "deleteServicePlansByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end job platform management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@






// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



// ********************************************
// user management section --user
// ********************************************



Route::post('/v1.0/users', [UserManagementController::class, "createUser"]);
Route::get('/v1.0/users/{id}', [UserManagementController::class, "getUserById"]);
Route::put('/v1.0/users', [UserManagementController::class, "updateUser"]);
Route::put('/v1.0/users/update-password', [UserManagementController::class, "updatePassword"]);
Route::put('/v1.0/users/assign-roles', [UserManagementController::class, "assignUserRole"]);
Route::put('/v1.0/users/assign-permissions', [UserManagementController::class, "assignUserPermission"]);

Route::put('/v1.0/users/profile', [UserManagementController::class, "updateUserProfile"]);
Route::put('/v1.0/users/toggle-active', [UserManagementController::class, "toggleActiveUser"]);

Route::get('/v1.0/users', [UserManagementController::class, "getUsers"]);
Route::get('/v2.0/users', [UserManagementController::class, "getUsersV2"]);
Route::get('/v3.0/users', [UserManagementController::class, "getUsersV3"]);
Route::get('/v4.0/users', [UserManagementController::class, "getUsersV4"]);
Route::get('/v5.0/users', [UserManagementController::class, "getUsersV5"]);
Route::get('/v7.0/users', [UserManagementController::class, "getUsersV7"]);
















Route::delete('/v1.0/users/{ids}', [UserManagementController::class, "deleteUsersByIds"]);
Route::get('/v1.0/users/get/user-activity', [UserManagementController::class, "getUserActivity"]);



Route::post('/v2.0/users', [UserManagementController::class, "createUserV2"]);

Route::put('/v2.0/users', [UserManagementController::class, "updateUserV2"]);
Route::put('/v3.0/users', [UserManagementController::class, "updateUserV3"]);
Route::put('/v4.0/users', [UserManagementController::class, "updateUserV4"]);



Route::put('/v1.0/users/update-address', [UserManagementController::class, "updateUserAddress"]);
Route::put('/v1.0/users/update-bank-details', [UserManagementController::class, "updateUserBankDetails"]);

Route::put('/v1.0/users/update-joining-date', [UserManagementController::class, "updateUserJoiningDate"]);


Route::put('/v1.0/users/update-emergency-contact', [UserManagementController::class, "updateEmergencyContact"]);



Route::get('/v2.0/users/{id}', [UserManagementController::class, "getUserByIdV2"]);

Route::get('/v3.0/users/{id}', [UserManagementController::class, "getUserByIdV3"]);
Route::get('/v4.0/users/{id}', [UserManagementController::class, "getUserByIdV4"]);






Route::get('/v1.0/users/generate/employee-id', [UserManagementController::class, "generateEmployeeId"]);
Route::get('/v1.0/users/validate/employee-id/{user_id}', [UserManagementController::class, "validateEmployeeId"]);

Route::get('/v1.0/users/get-leave-details/{id}', [UserManagementController::class, "getLeaveDetailsByUserId"]);

Route::get('/v1.0/users/load-data-for-leaves/{id}', [UserManagementController::class, "getLoadDataForLeaveByUserId"]);


Route::get('/v1.0/users/load-data-for-attendances/{id}', [UserManagementController::class, "getLoadDataForAttendanceByUserId"]);




Route::get('/v1.0/users/get-disable-days-for-attendances/{id}', [UserManagementController::class, "getDisableDaysForAttendanceByUserId"]);

Route::get('/v1.0/users/get-attendances/{id}', [UserManagementController::class, "getAttendancesByUserId"]);

Route::get('/v1.0/users/get-leaves/{id}', [UserManagementController::class, "getLeavesByUserId"]);

Route::get('/v1.0/users/get-holiday-details/{id}', [UserManagementController::class, "getholidayDetailsByUserId"]);

Route::get('/v1.0/users/get-schedule-information/by-user', [UserManagementController::class, "getScheduleInformation"]);



Route::get('/v1.0/users/get-recruitment-processes/{id}', [UserManagementController::class, "getRecruitmentProcessesByUserId"]);



// ********************************************
// user management section --role
// ********************************************
Route::get('/v1.0/initial-role-permissions', [RolesController::class, "getInitialRolePermissions"]);
Route::get('/v1.0/initial-permissions', [RolesController::class, "getInitialPermissions"]);
Route::post('/v1.0/roles', [RolesController::class, "createRole"]);
Route::put('/v1.0/roles', [RolesController::class, "updateRole"]);
Route::get('/v1.0/roles', [RolesController::class, "getRoles"]);

Route::get('/v1.0/roles/{id}', [RolesController::class, "getRoleById"]);
Route::delete('/v1.0/roles/{ids}', [RolesController::class, "deleteRolesByIds"]);
// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// end user management section
// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user document  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Route::post('/v1.0/user-documents', [UserDocumentController::class, "createUserDocument"]);
Route::put('/v1.0/user-documents', [UserDocumentController::class, "updateUserDocument"]);
Route::get('/v1.0/user-documents', [UserDocumentController::class, "getUserDocuments"]);
Route::get('/v1.0/user-documents/{id}', [UserDocumentController::class, "getUserDocumentById"]);
Route::delete('/v1.0/user-documents/{ids}', [UserDocumentController::class, "deleteUserDocumentsByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end user document management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@





// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user recruitment process  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/user-recruitment-processes', [UserRecruitmentProcessController::class, "createUserRecruitmentProcess"]);

Route::put('/v1.0/user-recruitment-processes', [UserRecruitmentProcessController::class, "updateUserRecruitmentProcess"]);

Route::get('/v1.0/user-recruitment-processes/{id}', [UserRecruitmentProcessController::class, "getUserRecruitmentProcessesById"]);

Route::delete('/v1.0/user-recruitment-processes/{ids}', [UserRecruitmentProcessController::class, "deleteUserRecruitmentProcess"]);
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user recruitment process  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@










// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user job history  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/user-job-histories', [UserJobHistoryController::class, "createUserJobHistory"]);
Route::put('/v1.0/user-job-histories', [UserJobHistoryController::class, "updateUserJobHistory"]);
Route::get('/v1.0/user-job-histories', [UserJobHistoryController::class, "getUserJobHistories"]);
Route::get('/v1.0/user-job-histories/{id}', [UserJobHistoryController::class, "getUserJobHistoryById"]);
Route::delete('/v1.0/user-job-histories/{ids}', [UserJobHistoryController::class, "deleteUserJobHistoriesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end user job history management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user education history  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/user-education-histories', [UserEducationHistoryController::class, "createUserEducationHistory"]);
Route::put('/v1.0/user-education-histories', [UserEducationHistoryController::class, "updateUserEducationHistory"]);
Route::get('/v1.0/user-education-histories', [UserEducationHistoryController::class, "getUserEducationHistories"]);
Route::get('/v1.0/user-education-histories/{id}', [UserEducationHistoryController::class, "getUserEducationHistoryById"]);
Route::delete('/v1.0/user-education-histories/{ids}', [UserEducationHistoryController::class, "deleteUserEducationHistoriesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end user education history management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user payslip   management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/user-payslips', [UserPayslipController::class, "createUserPayslip"]);
Route::put('/v1.0/user-payslips', [UserPayslipController::class, "updateUserPayslip"]);
Route::get('/v1.0/user-payslips', [UserPayslipController::class, "getUserPayslips"]);
Route::get('/v1.0/user-payslips/{id}', [UserPayslipController::class, "getUserPayslipById"]);
Route::delete('/v1.0/user-payslips/{ids}', [UserPayslipController::class, "deleteUserPayslipsByIds"]);
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end user payslip history management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user notes management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/user-notes', [UserNoteController::class, "createUserNote"]);
Route::put('/v1.0/user-notes', [UserNoteController::class, "updateUserNote"]);
Route::put('/v1.0/user-notes/by-business-owner', [UserNoteController::class, "updateUserNoteByBusinessOwner"]);
Route::get('/v1.0/user-notes', [UserNoteController::class, "getUserNotes"]);
Route::get('/v1.0/user-notes/{id}', [UserNoteController::class, "getUserNoteById"]);
Route::delete('/v1.0/user-notes/{ids}', [UserNoteController::class, "deleteUserNotesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end user notes management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user address history  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/user-address-histories', [UserAddressHistoryController::class, "createUserAddressHistory"]);
Route::put('/v1.0/user-address-histories', [UserAddressHistoryController::class, "updateUserAddressHistory"]);
Route::get('/v1.0/user-address-histories', [UserAddressHistoryController::class, "getUserAddressHistories"]);
Route::get('/v1.0/user-address-histories/{id}', [UserAddressHistoryController::class, "getUserAddressHistoryById"]);
Route::delete('/v1.0/user-address-histories/{ids}', [UserAddressHistoryController::class, "deleteUserAddressHistoriesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end user address history management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user passport history  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Route::post('/v1.0/user-passport-histories', [UserPassportHistoryController::class, "createUserPassportHistory"]);
Route::put('/v1.0/user-passport-histories', [UserPassportHistoryController::class, "updateUserPassportHistory"]);
Route::get('/v1.0/user-passport-histories', [UserPassportHistoryController::class, "getUserPassportHistories"]);
Route::get('/v1.0/user-passport-histories/{id}', [UserPassportHistoryController::class, "getUserPassportHistoryById"]);
Route::delete('/v1.0/user-passport-histories/{ids}', [UserPassportHistoryController::class, "deleteUserPassportHistoriesByIds"]);
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end user passport history management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user passport history  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Route::post('/v1.0/user-visa-histories', [UserVisaHistoryController::class, "createUserVisaHistory"]);
Route::put('/v1.0/user-visa-histories', [UserVisaHistoryController::class, "updateUserVisaHistory"]);
Route::get('/v1.0/user-visa-histories', [UserVisaHistoryController::class, "getUserVisaHistories"]);
Route::get('/v1.0/user-visa-histories/{id}', [UserVisaHistoryController::class, "getUserVisaHistoryById"]);
Route::delete('/v1.0/user-visa-histories/{ids}', [UserVisaHistoryController::class, "deleteUserVisaHistoriesByIds"]);
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end user passport history management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@






// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user passport history  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Route::post('/v1.0/user-right-to-work-histories', [UserRightToWorkHistoryController::class, "createUserRightToWorkHistory"]);
Route::put('/v1.0/user-right-to-work-histories', [UserRightToWorkHistoryController::class, "updateRightToWorkHistory"]);
Route::get('/v1.0/user-right-to-work-histories', [UserRightToWorkHistoryController::class, "getUserRightToWorkHistories"]);
Route::get('/v1.0/user-right-to-work-histories/{id}', [UserRightToWorkHistoryController::class, "getUserRightToWorkHistoryById"]);
Route::delete('/v1.0/user-right-to-work-histories/{ids}', [UserRightToWorkHistoryController::class, "deleteUserRightToWorkHistoriesByIds"]);
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end user passport history management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@




// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user sponsorship history history  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/user-sponsorship-histories', [UserSponsorshipHistoryController::class, "createUserSponsorshipHistory"]);
Route::put('/v1.0/user-sponsorship-histories', [UserSponsorshipHistoryController::class, "updateUserSponsorshipHistory"]);
Route::get('/v1.0/user-sponsorship-histories', [UserSponsorshipHistoryController::class, "getUserSponsorshipHistories"]);
Route::get('/v1.0/user-sponsorship-histories/{id}', [UserSponsorshipHistoryController::class, "getUserSponsorshipHistoryById"]);
Route::delete('/v1.0/user-sponsorship-histories/{ids}', [UserSponsorshipHistoryController::class, "deleteUserSponsorshipHistoriesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end user sponsorship  history management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@








// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user pension history management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/user-pension-histories', [UserPensionHistoryController::class, "createUserPensionHistory"]);
Route::put('/v1.0/user-pension-histories', [UserPensionHistoryController::class, "updateUserPensionHistory"]);
Route::get('/v1.0/user-pension-histories', [UserPensionHistoryController::class, "getUserPensionHistories"]);
Route::get('/v1.0/user-pension-histories/{id}', [UserPensionHistoryController::class, "getUserPensionHistoryById"]);
Route::delete('/v1.0/user-pension-histories/{ids}', [UserPensionHistoryController::class, "deleteUserPensionHistoriesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end user pension  history management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

















// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user asset  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/user-assets', [UserAssetController::class, "createUserAsset"]);
Route::put('/v1.0/user-assets/add-existing', [UserAssetController::class, "addExistingUserAsset"]);
Route::put('/v1.0/user-assets', [UserAssetController::class, "updateUserAsset"]);
Route::put('/v1.0/user-assets/return', [UserAssetController::class, "returnUserAsset"]);

Route::get('/v1.0/user-assets', [UserAssetController::class, "getUserAssets"]);
Route::get('/v1.0/user-assets/{id}', [UserAssetController::class, "getUserAssetById"]);
Route::delete('/v1.0/user-assets/{ids}', [UserAssetController::class, "deleteUserAssetsByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end user asset management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// user social site  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Route::post('/v1.0/user-social-sites', [UserSocialSiteController::class, "createUserSocialSite"]);
Route::put('/v1.0/user-social-sites', [UserSocialSiteController::class, "updateUserSocialSite"]);
Route::get('/v1.0/user-social-sites', [UserSocialSiteController::class, "getUserSocialSites"]);
Route::get('/v1.0/user-social-sites/{id}', [UserSocialSiteController::class, "getUserSocialSiteById"]);
Route::delete('/v1.0/user-social-sites/{ids}', [UserSocialSiteController::class, "deleteUserSocialSitesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end user social site management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// business management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Route::post('/v1.0/auth/check-schedule-conflict', [BusinessController::class, "checkScheduleConflict"]);
Route::post('/v1.0/auth/register-with-business', [BusinessController::class, "registerUserWithBusiness"]);

Route::post('/v1.0/businesses', [BusinessController::class, "createBusiness"]);


Route::put('/v1.0/businesses/toggle-active', [BusinessController::class, "toggleActiveBusiness"]);
Route::put('/v1.0/businesses', [BusinessController::class, "updateBusiness"]);

Route::put('/v1.0/businesses-part-1', [BusinessController::class, "updateBusinessPart1"]);
Route::put('/v1.0/businesses-part-2', [BusinessController::class, "updateBusinessPart2"]);
Route::put('/v1.0/businesses-part-3', [BusinessController::class, "updateBusinessPart3"]);
Route::put('/v1.0/business-pension-information', [BusinessController::class, "updateBusinessPensionInformation"]);


Route::get('/v1.0/business-subscriptions/{id}', [BusinessController::class, "getSubscriptionsByBusinessId"]);








Route::put('/v1.0/businesses/separate', [BusinessController::class, "updateBusinessSeparate"]);
Route::get('/v1.0/businesses', [BusinessController::class, "getBusinesses"]);
Route::get('/v1.0/businesses/{id}', [BusinessController::class, "getBusinessById"]);
Route::get('/v2.0/businesses/{id}', [BusinessController::class, "getBusinessByIdV2"]);

Route::get('/v1.0/businesses-id-by-email/{email}', [BusinessController::class, "getBusinessIdByEmail"]);





Route::delete('/v1.0/businesses/{ids}', [BusinessController::class, "deleteBusinessesByIds"]);
Route::get('/v1.0/businesses/by-business-owner/all', [BusinessController::class, "getAllBusinessesByBusinessOwner"]);
Route::get('/v1.0/businesses-pension-information/{id}', [BusinessController::class, "getBusinessPensionInformationById"]);




Route::get('/v1.0/businesses-pension-information-history/{id}', [BusinessController::class, "getBusinessPensionInformationHistoryByBusinessId"]);
Route::delete('/v1.0/businesses-pension-information-history/{ids}', [BusinessController::class, "deleteBusinessPensionInformationHistoryByIds"]);

// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// end business management section
// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// business Time Management
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::patch('/v1.0/business-times', [BusinessTimesController::class, "updateBusinessTimes"]);
Route::get('/v1.0/business-times', [BusinessTimesController::class, "getBusinessTimes"]);


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// Garage Background Image Management
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// end businesses Background Image Management
// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%




// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// template management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

// ********************************************
// template management section --wrapper
// ********************************************
Route::put('/v1.0/email-template-wrappers', [EmailTemplateWrapperController::class, "updateEmailTemplateWrapper"]);
Route::get('/v1.0/email-template-wrappers/{perPage}', [EmailTemplateWrapperController::class, "getEmailTemplateWrappers"]);
Route::get('/v1.0/email-template-wrappers/single/{id}', [EmailTemplateWrapperController::class, "getEmailTemplateWrapperById"]);

// ********************************************
// template management section
// ********************************************
Route::post('/v1.0/email-templates', [EmailTemplateController::class, "createEmailTemplate"]);
Route::put('/v1.0/email-templates', [EmailTemplateController::class, "updateEmailTemplate"]);
Route::get('/v1.0/email-templates/{perPage}', [EmailTemplateController::class, "getEmailTemplates"]);
Route::get('/v1.0/email-templates/single/{id}', [EmailTemplateController::class, "getEmailTemplateById"]);
Route::get('/v1.0/email-template-types', [EmailTemplateController::class, "getEmailTemplateTypes"]);
 Route::delete('/v1.0/email-templates/{id}', [EmailTemplateController::class, "deleteEmailTemplateById"]);

// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// template management section
// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%


// ********************************************
// notification template management section
// ********************************************

Route::put('/v1.0/notification-templates', [NotificationTemplateController::class, "updateNotificationTemplate"]);
Route::get('/v1.0/notification-templates/{perPage}', [NotificationTemplateController::class, "getNotificationTemplates"]);
Route::get('/v1.0/notification-templates/single/{id}', [NotificationTemplateController::class, "getEmailTemplateById"]);
Route::get('/v1.0/notification-template-types', [NotificationTemplateController::class, "getNotificationTemplateTypes"]);
// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// notification template management section
// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// payment type management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Route::post('/v1.0/payment-types', [PaymentTypeController::class, "createPaymentType"]);
Route::put('/v1.0/payment-types', [PaymentTypeController::class, "updatePaymentType"]);
Route::get('/v1.0/payment-types/{perPage}', [PaymentTypeController::class, "getPaymentTypes"]);
Route::delete('/v1.0/payment-types/{id}', [PaymentTypeController::class, "deletePaymentTypeById"]);
// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// payment type management section
// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// asset type  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/asset-types', [AssetTypeController::class, "createAssetType"]);
Route::put('/v1.0/asset-types', [AssetTypeController::class, "updateAssetType"]);
Route::get('/v1.0/asset-types', [AssetTypeController::class, "getAssetTypes"]);
Route::get('/v1.0/asset-types/{id}', [AssetTypeController::class, "getAssetTypeById"]);
Route::delete('/v1.0/asset-types/{ids}', [AssetTypeController::class, "deleteAssetTypesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end asset type  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@





// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// department  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/departments', [DepartmentController::class, "createDepartment"]);
Route::put('/v1.0/departments', [DepartmentController::class, "updateDepartment"]);
Route::put('/v1.0/departments/toggle-active', [DepartmentController::class, "toggleActiveDepartment"]);
Route::get('/v1.0/departments', [DepartmentController::class, "getDepartments"]);
Route::get('/v2.0/departments', [DepartmentController::class, "getDepartmentsV2"]);
Route::get('/v3.0/departments', [DepartmentController::class, "getDepartmentsV3"]);
Route::get('/v1.0/departments/{id}', [DepartmentController::class, "getDepartmentById"]);
Route::delete('/v1.0/departments/{ids}', [DepartmentController::class, "deleteDepartmentsByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end department  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@




// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// holiday  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/holidays/self', [HolidayController::class, "createSelfHoliday"]);
Route::post('/v1.0/holidays', [HolidayController::class, "createHoliday"]);

Route::put('/v1.0/holidays', [HolidayController::class, "updateHoliday"]);
Route::put('/v1.0/holidays/approve', [HolidayController::class, "approveHoliday"]);

Route::get('/v1.0/holidays', [HolidayController::class, "getHolidays"]);
Route::get('/v1.0/holidays/{id}', [HolidayController::class, "getHolidayById"]);
Route::delete('/v1.0/holidays/{ids}', [HolidayController::class, "deleteHolidaysByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end holiday  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@




// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// work shift  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/work-shifts', [WorkShiftController::class, "createWorkShift"]);
Route::put('/v1.0/work-shifts', [WorkShiftController::class, "updateWorkShift"]);
Route::put('/v1.0/work-shifts/toggle-active', [WorkShiftController::class, "toggleActiveWorkShift"]);

Route::get('/v1.0/work-shifts', [WorkShiftController::class, "getWorkShifts"]);
Route::get('/v2.0/work-shifts', [WorkShiftController::class, "getWorkShiftsV2"]);


Route::get('/v1.0/work-shifts/{id}', [WorkShiftController::class, "getWorkShiftById"]);

Route::get('/v1.0/work-shifts/get-by-user-id/{user_id}', [WorkShiftController::class, "getWorkShiftByUserId"]);
Route::get('/v2.0/work-shifts/get-by-user-id/{user_id}', [WorkShiftController::class, "getWorkShiftByUserIdV2"]);

Route::delete('/v1.0/work-shifts/{ids}', [WorkShiftController::class, "deleteWorkShiftsByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end work shift  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

   // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
    // work shift  management section
    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



          // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
    // work shift  management section
    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

    Route::put('/v1.0/work-shift-histories', [WorkShiftHistoryController::class, "updateWorkShiftHistory"]);

    Route::get('/v1.0/work-shift-histories/{id}', [WorkShiftHistoryController::class, "getWorkShiftHistoryById"]);
    Route::get('/v1.0/current-work-shift-history/{employee_id}', [WorkShiftHistoryController::class, "getCurrentWorkShiftHistory"]);

    Route::delete('/v1.0/work-shift-histories/{ids}', [WorkShiftHistoryController::class, "deleteWorkShiftHistoriesByIds"]);

    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
    // end work shift  management section
    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
    // end work shift  management section
    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// work shift  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/employee-rotas', [EmployeeRotaController::class, "createEmployeeRota"]);
Route::put('/v1.0/employee-rotas', [EmployeeRotaController::class, "updateEmployeeRota"]);
Route::put('/v1.0/employee-rotas/toggle-active', [EmployeeRotaController::class, "toggleActiveEmployeeRota"]);

Route::get('/v1.0/employee-rotas', [EmployeeRotaController::class, "getEmployeeRotas"]);
Route::get('/v1.0/employee-rotas/{id}', [EmployeeRotaController::class, "getEmployeeRotaById"]);

Route::get('/v1.0/employee-rotas/get-by-user-id/{user_id}', [EmployeeRotaController::class, "getEmployeeRotaByUserId"]);

Route::delete('/v1.0/employee-rotas/{ids}', [EmployeeRotaController::class, "deleteEmployeeRotasByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end work shift  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@





// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// job platform  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/job-platforms', [JobPlatformController::class, "createJobPlatform"]);
Route::put('/v1.0/job-platforms', [JobPlatformController::class, "updateJobPlatform"]);

Route::put('/v1.0/job-platforms/toggle-active', [JobPlatformController::class, "toggleActiveJobPlatform"]);
Route::get('/v1.0/job-platforms', [JobPlatformController::class, "getJobPlatforms"]);
Route::get('/v1.0/job-platforms/{id}', [JobPlatformController::class, "getJobPlatformById"]);
Route::delete('/v1.0/job-platforms/{ids}', [JobPlatformController::class, "deleteJobPlatformsByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end job platform management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@




// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
//  social media management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/social-sites', [SocialSiteController::class, "createSocialSite"]);
Route::put('/v1.0/social-sites', [SocialSiteController::class, "updateSocialSite"]);
Route::get('/v1.0/social-sites', [SocialSiteController::class, "getSocialSites"]);
Route::get('/v1.0/social-sites/{id}', [SocialSiteController::class, "getSocialSiteById"]);
Route::delete('/v1.0/social-sites/{ids}', [SocialSiteController::class, "deleteSocialSitesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end social media management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// designation  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/designations', [DesignationController::class, "createDesignation"]);
Route::put('/v1.0/designations', [DesignationController::class, "updateDesignation"]);
Route::put('/v1.0/designations/toggle-active', [DesignationController::class, "toggleActiveDesignation"]);
Route::get('/v1.0/designations', [DesignationController::class, "getDesignations"]);
Route::get('/v1.0/designations/{id}', [DesignationController::class, "getDesignationById"]);
Route::delete('/v1.0/designations/{ids}', [DesignationController::class, "deleteDesignationsByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end designation management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// bank  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/banks', [BankController::class, "createBank"]);
Route::put('/v1.0/banks', [BankController::class, "updateBank"]);
Route::put('/v1.0/banks/toggle-active', [BankController::class, "toggleActiveBank"]);
Route::get('/v1.0/banks', [BankController::class, "getBanks"]);
Route::get('/v1.0/banks/{id}', [BankController::class, "getBankById"]);
Route::delete('/v1.0/banks/{ids}', [BankController::class, "deleteBanksByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end bank management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// designation  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/job-types', [JobTypeController::class, "createJobType"]);
Route::put('/v1.0/job-types', [JobTypeController::class, "updateJobType"]);
Route::put('/v1.0/job-types/toggle-active', [JobTypeController::class, "toggleActiveJobType"]);
Route::get('/v1.0/job-types', [JobTypeController::class, "getJobTypes"]);
Route::get('/v1.0/job-types/{id}', [JobTypeController::class, "getJobTypeById"]);
Route::delete('/v1.0/job-types/{ids}', [JobTypeController::class, "deleteJobTypesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end designation management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// work locations  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/work-locations', [WorkLocationController::class, "createWorkLocation"]);
Route::put('/v1.0/work-locations', [WorkLocationController::class, "updateWorkLocation"]);
Route::put('/v1.0/work-locations/toggle-active', [WorkLocationController::class, "toggleActiveWorkLocation"]);
Route::get('/v1.0/work-locations', [WorkLocationController::class, "getWorkLocations"]);
Route::get('/v1.0/work-locations/{id}', [WorkLocationController::class, "getWorkLocationById"]);
Route::delete('/v1.0/work-locations/{ids}', [WorkLocationController::class, "deleteWorkLocationsByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end work locations management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// recruitment processes  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/recruitment-processes', [RecruitmentProcessController::class, "createRecruitmentProcess"]);
Route::put('/v1.0/recruitment-processes', [RecruitmentProcessController::class, "updateRecruitmentProcess"]);
Route::put('/v1.0/recruitment-processes/toggle-active', [RecruitmentProcessController::class, "toggleActiveRecruitmentProcess"]);
Route::get('/v1.0/recruitment-processes', [RecruitmentProcessController::class, "getRecruitmentProcesses"]);
Route::get('/v1.0/recruitment-processes/{id}', [RecruitmentProcessController::class, "getRecruitmentProcessById"]);
Route::delete('/v1.0/recruitment-processes/{ids}', [RecruitmentProcessController::class, "deleteRecruitmentProcessesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end recruitment processes management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@





// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// employment status management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/employment-statuses', [EmploymentStatusController::class, "createEmploymentStatus"]);
Route::put('/v1.0/employment-statuses', [EmploymentStatusController::class, "updateEmploymentStatus"]);
Route::put('/v1.0/employment-statuses/toggle-active', [EmploymentStatusController::class, "toggleActiveEmploymentStatus"]);
Route::get('/v1.0/employment-statuses', [EmploymentStatusController::class, "getEmploymentStatuses"]);
Route::get('/v1.0/employment-statuses/{id}', [EmploymentStatusController::class, "getEmploymentStatusById"]);
Route::delete('/v1.0/employment-statuses/{ids}', [EmploymentStatusController::class, "deleteEmploymentStatusesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end employment status  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@




// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// setting leave types  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/setting-leave-types', [SettingLeaveTypeController::class, "createSettingLeaveType"]);
Route::put('/v1.0/setting-leave-types', [SettingLeaveTypeController::class, "updateSettingLeaveType"]);
Route::put('/v1.0/setting-leave-types/toggle-active', [SettingLeaveTypeController::class, "toggleActiveSettingLeaveType"]);
Route::put('/v1.0/setting-leave-types/toggle-earning-enabled', [SettingLeaveTypeController::class, "toggleEarningEnabledSettingLeaveType"]);
Route::get('/v1.0/setting-leave-types', [SettingLeaveTypeController::class, "getSettingLeaveTypes"]);
Route::get('/v1.0/setting-leave-types/{id}', [SettingLeaveTypeController::class, "getSettingLeaveTypeById"]);
Route::delete('/v1.0/setting-leave-types/{ids}', [SettingLeaveTypeController::class, "deleteSettingLeaveTypesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end setting leave types management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// setting leave  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Route::post('/v1.0/setting-leave', [SettingLeaveController::class, "createSettingLeave"]);
// Route::put('/v1.0/setting-leave', [SettingLeaveTypeController::class, "updateSettingLeaveType"]);
 Route::get('/v1.0/setting-leave', [SettingLeaveController::class, "getSettingLeave"]);
// Route::get('/v1.0/setting-leave/{id}', [SettingLeaveTypeController::class, "getSettingLeaveTypeById"]);
// Route::delete('/v1.0/setting-leave/{ids}', [SettingLeaveTypeController::class, "deleteSettingLeaveTypesByIds"]);
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end setting leave management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@




// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// leaves  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@


Route::post('/v1.0/leaves/self', [LeaveController::class, "createSelfLeave"]);
Route::post('/v1.0/leaves', [LeaveController::class, "createLeave"]);
Route::put('/v1.0/leaves/approve', [LeaveController::class, "approveLeave"]);
Route::put('/v1.0/leaves/approve/arrears', [LeaveController::class, "approveLeaveRecordArrear"]);



Route::put('/v1.0/leaves/bypass', [LeaveController::class, "bypassLeave"]);
Route::put('/v1.0/leaves', [LeaveController::class, "updateLeave"]);
Route::get('/v1.0/leaves', [LeaveController::class, "getLeaves"]);
Route::get('/v1.0/leave-arrears', [LeaveController::class, "getLeaveArrears"]);
Route::get('/v2.0/leaves', [LeaveController::class, "getLeavesV2"]);
Route::get('/v3.0/leaves', [LeaveController::class, "getLeavesV3"]);
Route::get('/v4.0/leaves', [LeaveController::class, "getLeavesV4"]);
Route::get('/v1.0/leaves/{id}', [LeaveController::class, "getLeaveById"]);
Route::get('/v1.0/leaves-get-current-hourly-rate', [LeaveController::class, "getLeaveCurrentHourlyRate"]);
Route::delete('/v1.0/leaves/{ids}', [LeaveController::class, "deleteLeavesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end leaves management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@







// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// setting attendance  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Route::post('/v1.0/setting-attendance', [SettingAttendanceController::class, "createSettingAttendance"]);
// Route::put('/v1.0/setting-leave', [SettingLeaveTypeController::class, "updateSettingLeaveType"]);
 Route::get('/v1.0/setting-attendance', [SettingAttendanceController::class, "getSettingAttendance"]);
// Route::get('/v1.0/setting-leave/{id}', [SettingLeaveTypeController::class, "getSettingLeaveTypeById"]);
// Route::delete('/v1.0/setting-leave/{ids}', [SettingLeaveTypeController::class, "deleteSettingLeaveTypesByIds"]);
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end setting attendance management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// attendances  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@


Route::post('/v1.0/attendances/self/check-in', [AttendanceController::class, "createSelfAttendanceCheckIn"]);
Route::put('/v1.0/attendances/self/check-out', [AttendanceController::class, "createSelfAttendanceCheckOut"]);
Route::post('/v1.0/attendances', [AttendanceController::class, "createAttendance"]);
Route::post('/v1.0/attendances/multiple', [AttendanceController::class, "createMultipleAttendance"]);
Route::put('/v1.0/attendances', [AttendanceController::class, "updateAttendance"]);
Route::put('/v1.0/attendances/approve', [AttendanceController::class, "approveAttendance"]);
Route::put('/v1.0/attendances/approve/arrears', [AttendanceController::class, "approveAttendanceArrear"]);
Route::get('/v1.0/attendances', [AttendanceController::class, "getAttendances"]);
Route::get('/v2.0/attendances', [AttendanceController::class, "getAttendancesV2"]);
Route::get('/v3.0/attendances', [AttendanceController::class, "getAttendancesV3"]);






Route::get('/v1.0/attendance-arrears', [AttendanceController::class, "getAttendanceArrears"]);
Route::get('/v1.0/attendances/{id}', [AttendanceController::class, "getAttendanceById"]);
Route::get('/v1.0/attendances/show/check-in-status', [AttendanceController::class, "getCurrentAttendance"]);
Route::delete('/v1.0/attendances/{ids}', [AttendanceController::class, "deleteAttendancesByIds"]);
Route::post('/v2.0/attendances/bypass/multiple', [AttendanceController::class, "createMultipleBypassAttendanceV2"]);
Route::post('/v1.0/attendances/bypass/multiple', [AttendanceController::class, "createMultipleBypassAttendanceV1"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end attendances management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// history details  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Route::get('/v1.0/histories/user-assets', [HistoryDetailsController::class, "getUserAssetHistory"]);
Route::get('/v1.0/histories/user-passport-details', [HistoryDetailsController::class, "getUserPassportDetailsHistory"]);
Route::get('/v1.0/histories/user-visa-details', [HistoryDetailsController::class, "getUserVisaDetailsHistory"]);
Route::get('/v1.0/histories/user-right-to-works', [HistoryDetailsController::class, 'getRightToWorksHistory']);
Route::get('/v1.0/histories/user-sponsorship-details', [HistoryDetailsController::class, "getUserSponsorshipDetailsHistory"]);
Route::get('/v1.0/histories/user-pension-details', [HistoryDetailsController::class, "getUserPensionDetailsHistory"]);
Route::get('/v1.0/histories/user-address-details', [HistoryDetailsController::class, "getUserAddressDetailsHistory"]);
Route::get('/v1.0/histories/user-attendance-details', [HistoryDetailsController::class, "getUserAttendanceDetailsHistory"]);
Route::get('/v1.0/histories/user-leave-details', [HistoryDetailsController::class, "getUserLeaveDetailsHistory"]);
Route::get('/v1.0/histories/user-work-shift', [HistoryDetailsController::class, "getUserWorkShiftHistory"]);
Route::get('/v1.0/histories/employee-work-shift', [HistoryDetailsController::class, "getEmployeeWorkShiftHistory"]);
Route::get('/v1.0/histories/user-project', [HistoryDetailsController::class, "getUserProjectHistory"]);


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end history details management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@




// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// setting payrun  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Route::post('/v1.0/setting-payrun', [SettingPayrollController::class, "createSettingPayrun"]);
// Route::put('/v1.0/setting-leave', [SettingLeaveTypeController::class, "updateSettingLeaveType"]);
 Route::get('/v1.0/setting-payrun', [SettingPayrollController::class, "getSettingPayrun"]);
// Route::get('/v1.0/setting-leave/{id}', [SettingLeaveTypeController::class, "getSettingLeaveTypeById"]);
// Route::delete('/v1.0/setting-leave/{ids}', [SettingLeaveTypeController::class, "deleteSettingLeaveTypesByIds"]);
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end setting payrun management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// payruns  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/payruns', [PayrunController::class, "createPayrun"]);
Route::put('/v1.0/payruns', [PayrunController::class, "updatePayrun"]);
Route::put('/v1.0/payruns/toggle-active', [PayrunController::class, "toggleActivePayrun"]);
Route::get('/v1.0/payruns', [PayrunController::class, "getPayruns"]);
Route::get('/v1.0/payruns/{id}', [PayrunController::class, "getPayrunById"]);
Route::delete('/v1.0/payruns/{ids}', [PayrunController::class, "deletePayrunsByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end payruns  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/payrolls', [PayrollController::class, "createPayroll"]);
Route::get('/v1.0/payrolls', [PayrollController::class, "getPayrolls"]);
Route::get('/v1.0/payrolls/report', [PayrollController::class, "getPayrollsReport"]);
Route::get('/v1.0/pending-payroll-users', [PayrollController::class, "getPendingPayrollUsers"]);
Route::get('/v1.0/payroll-list', [PayrollController::class, "getPayrollList"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// payruns  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// setting payment date  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@


 Route::post('/v1.0/setting-payment-dates', [SettingPaymentDateController::class, "createSettingPaymentDate"]);
 Route::get('/v1.0/setting-payment-dates', [SettingPaymentDateController::class, "getSettingPaymentDate"]);


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// setting payment date  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// setting payslip  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/setting-payslip', [SettingPayrollController::class, "createSettingPayslip"]);
// Route::put('/v1.0/setting-leave', [SettingLeaveTypeController::class, "updateSettingLeaveType"]);
 Route::get('/v1.0/setting-payslip', [SettingPayrollController::class, "getSettingPayslip"]);
// Route::get('/v1.0/setting-leave/{id}', [SettingLeaveTypeController::class, "getSettingLeaveTypeById"]);
// Route::delete('/v1.0/setting-leave/{ids}', [SettingLeaveTypeController::class, "deleteSettingLeaveTypesByIds"]);
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end setting payslip management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@




// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// job listings  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/job-listings', [JobListingController::class, "createJobListing"]);
Route::put('/v1.0/job-listings', [JobListingController::class, "updateJobListing"]);
Route::get('/v1.0/job-listings', [JobListingController::class, "getJobListings"]);
Route::get('/v1.0/job-listings/{id}', [JobListingController::class, "getJobListingById"]);
Route::delete('/v1.0/job-listings/{ids}', [JobListingController::class, "deleteJobListingsByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end job listings  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@






// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// candidates  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/candidates', [CandidateController::class, "createCandidate"]);
Route::put('/v1.0/candidates', [CandidateController::class, "updateCandidate"]);
Route::get('/v1.0/candidates', [CandidateController::class, "getCandidates"]);
Route::get('/v1.0/candidates/{id}', [CandidateController::class, "getCandidateById"]);
Route::delete('/v1.0/candidates/{ids}', [CandidateController::class, "deleteCandidatesByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end candidates management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// project  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/projects', [ProjectController::class, "createProject"]);
Route::put('/v1.0/projects/assign-user', [ProjectController::class, "assignUser"]);
Route::put('/v1.0/projects/discharge-user', [ProjectController::class, "dischargeUser"]);
Route::put('/v1.0/projects/assign-project', [ProjectController::class, "assignProject"]);
Route::put('/v1.0/projects/discharge-project', [ProjectController::class, "dischargeProject"]);
Route::put('/v1.0/projects', [ProjectController::class, "updateProject"]);
Route::get('/v1.0/projects', [ProjectController::class, "getProjects"]);
Route::get('/v1.0/projects/{id}', [ProjectController::class, "getProjectById"]);
Route::delete('/v1.0/projects/{ids}', [ProjectController::class, "deleteProjectsByIds"]);



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end project  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@























// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// product category management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/product-categories', [ProductCategoryController::class, "createProductCategory"]);
Route::put('/v1.0/product-categories', [ProductCategoryController::class, "updateProductCategory"]);
Route::get('/v1.0/product-categories/{perPage}', [ProductCategoryController::class, "getProductCategories"]);
Route::delete('/v1.0/product-categories/{id}', [ProductCategoryController::class, "deleteProductCategoryById"]);
Route::get('/v1.0/product-categories/single/get/{id}', [ProductCategoryController::class, "getProductCategoryById"]);

Route::get('/v1.0/product-categories/get/all', [ProductCategoryController::class, "getAllProductCategory"]);


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end product category management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@



// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// product management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

Route::post('/v1.0/products', [ProductController::class, "createProduct"]);
Route::put('/v1.0/products', [ProductController::class, "updateProduct"]);
Route::patch('/v1.0/products/link-product-to-shop', [ProductController::class, "linkProductToShop"]);

Route::get('/v1.0/products/{perPage}', [ProductController::class, "getProducts"]);
Route::get('/v1.0/products/single/get/{id}', [ProductController::class, "getProductById"]);
Route::delete('/v1.0/products/{id}', [ProductController::class, "deleteProductById"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end product  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@




// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// reminders  management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@


Route::post('/v1.0/reminders', [ReminderController::class, "createReminder"]);
Route::put('/v1.0/reminders', [ReminderController::class, "updateReminder"]);
Route::get('/v1.0/reminders-entity-names', [ReminderController::class, "getReminderEntityNames"]);
Route::get('/v1.0/reminders', [ReminderController::class, "getReminders"]);
Route::get('/v1.0/reminders/{id}', [ReminderController::class, "getReminderById"]);
Route::delete('/v1.0/reminders/{ids}', [ReminderController::class, "deleteRemindersByIds"]);

// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end reminders management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@


Route::get('/v1.0/dropdown-options/employee-form', [DropdownOptionsController::class, "getEmployeeFormDropdownData"]);
Route::get('/v2.0/dropdown-options/employee-form', [DropdownOptionsController::class, "getEmployeeFormDropdownDataV2"]);


// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
// end dropdown management section
// @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@






});

// !!!!!!!@@@@@@@@@@@@$$$$$$$$$$$$%%%%%%%%%%%%%%%%^^^^^^^^^^
// end admin routes
// !!!!!!!@@@@@@@@@@@@$$$$$$$$$$$$%%%%%%%%%%%%%%%%^^^^^^^^^^


Route::get('/v1.0/client/job-listings', [JobListingController::class, "getJobListingsClient"]);
Route::get('/v1.0/client/job-listings/{id}', [JobListingController::class, "getJobListingByIdClient"]);

Route::post('/v1.0/client/candidates', [CandidateController::class, "createCandidateClient"]);

Route::post('/v1.0/client/auth/register-with-business', [BusinessController::class, "registerUserWithBusinessClient"]);

Route::get('/v1.0/client/service-plans', [ServicePlanController::class, "getServicePlanClient"]);

Route::post('/v1.0/client/check-discount', [ServicePlanController::class, "checkDiscountClient"]);

Route::get('/v1.0/client/system-settings', [SystemSettingController::class, "getSystemSettingSettingClient"]);


Route::post('webhooks/stripe', [CustomWebhookController::class, "handleStripeWebhook"])->name("stripe.webhook");














































































