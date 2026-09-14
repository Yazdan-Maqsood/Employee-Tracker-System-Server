<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/middleware/cors.php';

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string
$requestUri = strtok($requestUri, '?');

// Remove base path
$requestUri = str_replace('/backend', '', $requestUri);

// Route the request
switch (true) {
    // ========== AUTH ROUTES ==========
    case ($requestUri === '/api/auth/login' && $requestMethod === 'POST'):
        require __DIR__ . '/api/auth/login.php';
        break;
    
    case ($requestUri === '/api/auth/register' && $requestMethod === 'POST'):
        require __DIR__ . '/api/auth/register.php';
        break;

    case ($requestUri === '/api/auth/activate' && $requestMethod === 'POST'):
        require __DIR__ . '/api/auth/activate.php';
        break;

    case ($requestUri === '/api/pusher/auth' && $requestMethod === 'POST'):
        require __DIR__ . '/api/pusher/auth.php';
        break;   

    // ========== ATTENDANCE ROUTES ==========
    case ($requestUri === '/api/attendance/check_in' && $requestMethod === 'POST'):
        require __DIR__ . '/api/attendance/check_in.php';
        break;
    
    case ($requestUri === '/api/attendance/check_out' && $requestMethod === 'POST'):
        require __DIR__ . '/api/attendance/check_out.php';
        break;
    
    case ($requestUri === '/api/attendance/get_status' && $requestMethod === 'GET'):
        require __DIR__ . '/api/attendance/get_status.php';
        break;

    case ($requestUri === '/api/attendance/confirm_extra_time' && $requestMethod === 'POST'):
        require __DIR__ . '/api/attendance/confirm_extra_time.php';
        break;
    
    case ($requestUri === '/api/attendance/get_history' && $requestMethod === 'GET'):
        require __DIR__ . '/api/attendance/get_history.php';
        break;
    
    case ($requestUri === '/api/attendance/gps_status' && $requestMethod === 'POST'):
        require __DIR__ . '/api/attendance/gps_status.php';
        break;

    // ========== ADMIN ROUTES ==========
    case ($requestUri === '/api/admin/get_employees' && $requestMethod === 'GET'):
        require __DIR__ . '/api/admin/get_employees.php';
        break;
    
    case ($requestUri === '/api/admin/get_all_attendance' && $requestMethod === 'GET'):
        require __DIR__ . '/api/admin/get_all_attendance.php';
        break;
    
    case ($requestUri === '/api/admin/filter_attendance' && $requestMethod === 'GET'):
        require __DIR__ . '/api/admin/filter_attendance.php';
        break;
    
    case ($requestUri === '/api/admin/get_today_status' && $requestMethod === 'GET'):
        require __DIR__ . '/api/admin/get_today_status.php';
        break;
    
    case ($requestUri === '/api/admin/employee_check_in' && $requestMethod === 'POST'):
        require __DIR__ . '/api/admin/employee_check_in.php';
        break;
    
    case ($requestUri === '/api/admin/employee_check_out' && $requestMethod === 'POST'):
        require __DIR__ . '/api/admin/employee_check_out.php';
        break;

    // ✅ EMPLOYEE STATUS UPDATE ROUTE (0 = Deactive, 1 = Active)
    case (preg_match('/^\/api\/admin\/update_employee_status\/(\d+)$/', $requestUri, $matches) && $requestMethod === 'PUT'):
        $_GET['employee_id'] = $matches[1];
        require __DIR__ . '/api/admin/update_employee_status.php';
        break;

    // ========== USER TASKS ==========
    case ($requestUri === '/api/user/tasks' && $requestMethod === 'GET'):
        require __DIR__ . '/api/user/tasks_list.php';
        break;
        
    case (preg_match('/^\/api\/user\/tasks\/(\d+)\/submit$/', $requestUri, $matches) && $requestMethod === 'POST'):
        $_GET['task_id'] = $matches[1];
        require __DIR__ . '/api/user/tasks_submit.php';
        break;

    case ($requestUri === '/api/user/team_toggle_pin' && $requestMethod === 'PUT'):
        require __DIR__ . '/api/user/team_toggle_pin.php';
        break;    

    // ========== EMPLOYEE CRUD ROUTES ==========
    case ($requestUri === '/api/admin/add_employee' && $requestMethod === 'POST'):
        require __DIR__ . '/api/admin/add_employee.php';
        break;
    
    case (preg_match('/^\/api\/admin\/update_employee\/(\d+)$/', $requestUri, $matches) && $requestMethod === 'PUT'):
        $_GET['employee_id'] = $matches[1];
        require __DIR__ . '/api/admin/update_employee.php';
        break;
    
    case (preg_match('/^\/api\/admin\/delete_employee\/(\d+)$/', $requestUri, $matches) && $requestMethod === 'DELETE'):
        $_GET['employee_id'] = $matches[1];
        require __DIR__ . '/api/admin/delete_employee.php';
        break;

    // ========== TASK ROUTES ==========
    case ($requestUri === '/api/admin/tasks/create' && $requestMethod === 'POST'):
        require __DIR__ . '/api/admin/tasks_create.php'; 
        break;
        
    case ($requestUri === '/api/admin/tasks' && $requestMethod === 'GET'):
        require __DIR__ . '/api/admin/tasks_list.php'; 
        break;
    case ($requestUri === '/api/admin/tasks/submissions' && $requestMethod === 'GET'):
        require __DIR__ . '/api/admin/tasks_submissions.php';
        break;
    case ($requestUri === '/api/admin/tasks/submissions/review' && $requestMethod === 'PUT'):
        require __DIR__ . '/api/admin/tasks_review.php'; 
        break;    
    case (preg_match('/^\/api\/admin\/tasks\/update\/(\d+)$/', $requestUri, $matches) && $requestMethod === 'PUT'):
        $_GET['task_id'] = $matches[1];
        require __DIR__ . '/api/admin/tasks_update.php';
        break;
    case (preg_match('/^\/api\/admin\/tasks\/delete\/(\d+)$/', $requestUri, $matches) && $requestMethod === 'DELETE'):
        $_GET['task_id'] = $matches[1];
        require __DIR__ . '/api/admin/tasks_delete.php';
        break;

    // ========== CHAT ROUTES ==========
    case ($requestUri === '/api/chat/send' && $requestMethod === 'POST'):
        require __DIR__ . '/api/chat/send.php';
        break;
    case ($requestUri === '/api/chat/messages' && $requestMethod === 'GET'):
        require __DIR__ . '/api/chat/messages.php';
        break;
    case ($requestUri === '/api/chat/typing_start' && $requestMethod === 'POST'):
        require __DIR__ . '/api/chat/typing_start.php';
        break;
    case ($requestUri === '/api/chat/typing_stop' && $requestMethod === 'POST'):
        require __DIR__ . '/api/chat/typing_stop.php';
        break;
    case ($requestUri === '/api/chat/send_file' && $requestMethod === 'POST'):
        require __DIR__ . '/api/chat/send_file.php';
        break;

    case ($requestUri === '/api/chat/toggle_pin' && $requestMethod === 'PUT'):
        require __DIR__ . '/api/chat/toggle_pin.php';
        break;    

    // ========== TEAM MANAGEMENT ROUTES ==========
    case ($requestUri === '/api/admin/teams/create' && $requestMethod === 'POST'):
        require __DIR__ . '/api/admin/teams_create.php'; 
        break;
    case ($requestUri === '/api/admin/teams' && $requestMethod === 'GET'):
        require __DIR__ . '/api/admin/teams_list.php'; 
        break;
    case ($requestUri === '/api/admin/teams/members/add' && $requestMethod === 'POST'):
        require __DIR__ . '/api/admin/teams_members_add.php'; 
        break;
    case ($requestUri === '/api/admin/teams/members/remove' && $requestMethod === 'DELETE'):
        require __DIR__ . '/api/admin/teams_members_remove.php'; 
        break;
    case ($requestUri === '/api/admin/teams/members' && $requestMethod === 'GET'):
        require __DIR__ . '/api/admin/teams_members_list.php';
        break;
    case ($requestUri === '/api/admin/teams/update' && $requestMethod === 'PUT'):
        require __DIR__ . '/api/admin/teams_update.php'; 
        break;
    case ($requestUri === '/api/admin/teams/delete' && $requestMethod === 'DELETE'):
        require __DIR__ . '/api/admin/teams_delete.php'; 
        break;

    case ($requestUri === '/api/admin/employee_locations' && $requestMethod === 'GET'):
        require __DIR__ . '/api/admin/employee_locations.php';
        break;    

    case ($requestUri === '/api/admin/send_stats_email' && $requestMethod === 'POST'):
        require __DIR__ . '/api/admin/send_stats_email.php';
        break;    

    // User team chat
    case ($requestUri === '/api/user/team_messages' && $requestMethod === 'GET'):
        require __DIR__ . '/api/user/team_messages.php';
        break;
    case ($requestUri === '/api/user/team_send' && $requestMethod === 'POST'):
        require __DIR__ . '/api/user/team_send.php';
        break;
    case ($requestUri === '/api/user/team/messages' && $requestMethod === 'GET'):
        require __DIR__ . '/api/user/team_messages.php';
        break;
    case ($requestUri === '/api/user/team/send' && $requestMethod === 'POST'):
        require __DIR__ . '/api/user/team_send.php';
        break;

    case ($requestUri === '/api/user/teams' && $requestMethod === 'GET'):
        require __DIR__ . '/api/user/teams_list.php'; 
        break;

    case ($requestUri === '/api/user/team/members' && $requestMethod === 'GET'):
        require __DIR__ . '/api/user/team_members.php';
        break;

    case ($requestUri === '/api/user/chat_sidebar' && $requestMethod === 'GET'):
        require __DIR__ . '/api/user/chat_sidebar.php';
        break;

    case ($requestUri === '/api/user/get_user_info' && $requestMethod === 'GET'):
        require __DIR__ . '/api/user/get_user_info.php';
        break;

    // ========== HOLIDAY ROUTES ==========
    case ($requestUri === '/api/admin/holidays' && $requestMethod === 'GET'):
        require __DIR__ . '/api/admin/holidays_list.php';
        break;

    case ($requestUri === '/api/admin/holiday/add' && $requestMethod === 'POST'):
        require __DIR__ . '/api/admin/holiday_add.php';
        break;

    case ($requestUri === '/api/admin/holiday/delete' && $requestMethod === 'DELETE'):
        require __DIR__ . '/api/admin/holiday_delete.php';
        break;

    // ========== SCREEN CAPTURE ROUTES ==========
    case ($requestUri === '/api/admin/request_screen_capture' && $requestMethod === 'POST'):
        require __DIR__ . '/api/admin/request_screen_capture.php';
        break;

    case ($requestUri === '/api/admin/request_screen_record' && $requestMethod === 'POST'):
        require __DIR__ . '/api/admin/request_screen_record.php';
        break;

    case ($requestUri === '/api/admin/get_screen_captures' && $requestMethod === 'GET'):
        require __DIR__ . '/api/admin/get_screen_captures.php';
        break;

    case ($requestUri === '/api/user/upload_screen_capture' && $requestMethod === 'POST'):
        require __DIR__ . '/api/user/upload_screen_capture.php';
        break;
        
    case ($requestUri === '/api/attendance/track_location' && $requestMethod === 'POST'):
        require __DIR__ . '/api/attendance/track_location.php';
        break;

    case ($requestUri === '/api/attendance/get_break_history' && $requestMethod === 'GET'):
        require __DIR__ . '/api/attendance/get_break_history.php';
        break;

        // ========== LEAVE ROUTES ==========
    case ($requestUri === '/api/user/leave_request' && $requestMethod === 'POST'):
        require __DIR__ . '/api/user/leave_request.php';
        break;

    case ($requestUri === '/api/user/my_leaves' && $requestMethod === 'GET'):
        require __DIR__ . '/api/user/my_leaves.php';
        break;

    case ($requestUri === '/api/admin/leave_requests' && $requestMethod === 'GET'):
        require __DIR__ . '/api/admin/leave_requests.php';
        break;

    case ($requestUri === '/api/admin/leave_action' && $requestMethod === 'PUT'):
        require __DIR__ . '/api/admin/leave_action.php';
        break;

    // Notification routes
    case ($requestUri === '/api/user/get_notifications' && $requestMethod === 'GET'):
        require __DIR__ . '/api/user/get_notifications.php';
        break;

    case ($requestUri === '/api/user/mark_notification_read' && $requestMethod === 'PUT'):
        require __DIR__ . '/api/user/mark_notification_read.php';
        break;

    default:
        http_response_code(404);
        echo json_encode([
            "status" => 404,
            "message" => "Route not found: " . $requestUri
        ]);
        break;
}
?>