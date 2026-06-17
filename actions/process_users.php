<?php
/**
 * Process User Management CRUD Operations
 * Smart Agricultural Decision Support System
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Set response header for AJAX delete
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
}

// 1. Verify User Role (Must be System Admin)
if ($_SESSION['role'] !== 'System Admin') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        echo json_encode(['success' => false, 'message' => 'Access Denied: Insufficient permissions.']);
    } else {
        $_SESSION['user_status'] = [
            'icon' => 'error',
            'title' => 'Access Denied',
            'text' => 'You do not have permission to manage users.'
        ];
        header("Location: ../dashboard.php");
    }
    exit();
}

// 2. Verify Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    } else {
        header("Location: ../users.php");
    }
    exit();
}

// 3. Verify CSRF Token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        echo json_encode(['success' => false, 'message' => 'Security Error: CSRF token validation failed.']);
    } else {
        $_SESSION['user_status'] = [
            'icon' => 'error',
            'title' => 'Security Error',
            'text' => 'CSRF token validation failed. Please refresh the page.'
        ];
        header("Location: ../users.php");
    }
    exit();
}

$action = $_POST['action'] ?? '';
$allowed_roles = ['System Admin', 'DA Officer', 'Extension Worker'];

switch ($action) {
    case 'create':
        $username = preg_replace('/\s+/', '', trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? '');

        // Validation: Fields presence
        if ($username === '' || $password === '' || $role === '') {
            $_SESSION['user_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'All fields are required.'
            ];
            header("Location: ../users.php");
            exit();
        }

        // Validation: Alphanumeric username, length 3-20
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $_SESSION['user_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'Username must be 3-20 characters long and contain only letters, numbers, or underscores.'
            ];
            header("Location: ../users.php");
            exit();
        }

        // Validation: Role options
        if (!in_array($role, $allowed_roles, true)) {
            $_SESSION['user_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'Invalid role selected.'
            ];
            header("Location: ../users.php");
            exit();
        }

        // Validation: Password length
        if (strlen($password) < 4) {
            $_SESSION['user_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'Password must be at least 4 characters long.'
            ];
            header("Location: ../users.php");
            exit();
        }

        try {
            // Check for duplicate username
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE username = :username");
            $checkStmt->execute(['username' => $username]);
            if (intval($checkStmt->fetchColumn()) > 0) {
                $_SESSION['user_status'] = [
                    'icon' => 'error',
                    'title' => 'Validation Error',
                    'text' => 'Username is already taken.'
                ];
                header("Location: ../users.php");
                exit();
            }

            // Hashing password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $insertStmt = $pdo->prepare("INSERT INTO tbl_users (username, password, role) VALUES (:username, :password, :role)");
            $insertStmt->execute([
                'username' => $username,
                'password' => $hashed_password,
                'role' => $role
            ]);
            $new_id = $pdo->lastInsertId();

            // Log action
            $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
            $logStmt->execute([
                'user_id' => $_SESSION['user_id'],
                'action' => "Created system user account: '{$username}' with role '{$role}' (User ID: {$new_id})."
            ]);

            $_SESSION['user_status'] = [
                'icon' => 'success',
                'title' => 'User Created',
                'text' => "Successfully created user '{$username}' as '{$role}'."
            ];
        } catch (\PDOException $e) {
            error_log("Database error in User Create: " . $e->getMessage());
            $_SESSION['user_status'] = [
                'icon' => 'error',
                'title' => 'Database Error',
                'text' => 'Failed to create user due to a database error.'
            ];
        }
        header("Location: ../users.php");
        exit();

    case 'update':
        $id = intval($_POST['id'] ?? 0);
        $username = preg_replace('/\s+/', '', trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? '');

        if ($id <= 0) {
            $_SESSION['user_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'Invalid user ID.'
            ];
            header("Location: ../users.php");
            exit();
        }

        // Validation: Username and role presence
        if ($username === '' || $role === '') {
            $_SESSION['user_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'Username and role are required.'
            ];
            header("Location: ../users.php");
            exit();
        }

        // Validation: Alphanumeric username, length 3-20
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $_SESSION['user_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'Username must be 3-20 characters long and contain only letters, numbers, or underscores.'
            ];
            header("Location: ../users.php");
            exit();
        }

        // Validation: Role options
        if (!in_array($role, $allowed_roles, true)) {
            $_SESSION['user_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'Invalid role selected.'
            ];
            header("Location: ../users.php");
            exit();
        }

        // Safeguard: Prevent self-demotion
        if ($id === intval($_SESSION['user_id']) && $role !== 'System Admin') {
            $_SESSION['user_status'] = [
                'icon' => 'error',
                'title' => 'Safeguard Blocked',
                'text' => 'You cannot change your own role to a non-admin role to prevent locking yourself out.'
            ];
            header("Location: ../users.php");
            exit();
        }

        // Validation: Password length if provided
        if ($password !== '' && strlen($password) < 4) {
            $_SESSION['user_status'] = [
                'icon' => 'error',
                'title' => 'Validation Error',
                'text' => 'New password must be at least 4 characters long.'
            ];
            header("Location: ../users.php");
            exit();
        }

        try {
            // Check that the updated username is not taken by another user
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE username = :username AND id != :id");
            $checkStmt->execute(['username' => $username, 'id' => $id]);
            if (intval($checkStmt->fetchColumn()) > 0) {
                $_SESSION['user_status'] = [
                    'icon' => 'error',
                    'title' => 'Validation Error',
                    'text' => 'Username is already taken.'
                ];
                header("Location: ../users.php");
                exit();
            }

            // Prepare update statement
            if ($password !== '') {
                // Hashing password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE tbl_users SET username = :username, password = :password, role = :role WHERE id = :id");
                $updateParams = [
                    'username' => $username,
                    'password' => $hashed_password,
                    'role' => $role,
                    'id' => $id
                ];
            } else {
                $updateStmt = $pdo->prepare("UPDATE tbl_users SET username = :username, role = :role WHERE id = :id");
                $updateParams = [
                    'username' => $username,
                    'role' => $role,
                    'id' => $id
                ];
            }
            $updateStmt->execute($updateParams);

            // If updating currently logged in user, refresh session username
            if ($id === intval($_SESSION['user_id'])) {
                $_SESSION['username'] = $username;
            }

            // Log action
            $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
            $logStmt->execute([
                'user_id' => $_SESSION['user_id'],
                'action' => "Modified system user account details: ID {$id} - Username: '{$username}', Role: '{$role}'" . ($password !== '' ? " (Password updated)." : ".")
            ]);

            $_SESSION['user_status'] = [
                'icon' => 'success',
                'title' => 'User Updated',
                'text' => "Successfully updated details for user '{$username}'."
            ];
        } catch (\PDOException $e) {
            error_log("Database error in User Update: " . $e->getMessage());
            $_SESSION['user_status'] = [
                'icon' => 'error',
                'title' => 'Database Error',
                'text' => 'Failed to update user due to a database error.'
            ];
        }
        header("Location: ../users.php");
        exit();

    case 'delete':
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
            exit();
        }

        // Safeguard: Prevent deleting self
        if ($id === intval($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Safeguard Blocked: You cannot delete your own logged-in admin account.']);
            exit();
        }

        try {
            // Retrieve user details first to log the username
            $userStmt = $pdo->prepare("SELECT username, role FROM tbl_users WHERE id = :id");
            $userStmt->execute(['id' => $id]);
            $targetUser = $userStmt->fetch();

            if (!$targetUser) {
                echo json_encode(['success' => false, 'message' => 'User account does not exist.']);
                exit();
            }

            // Perform Delete
            $deleteStmt = $pdo->prepare("DELETE FROM tbl_users WHERE id = :id");
            $deleteStmt->execute(['id' => $id]);

            // Log action
            $logStmt = $pdo->prepare("INSERT INTO tbl_system_logs (user_id, action) VALUES (:user_id, :action)");
            $logStmt->execute([
                'user_id' => $_SESSION['user_id'],
                'action' => "Deleted system user account: '{$targetUser['username']}' with role '{$targetUser['role']}' (User ID: {$id})."
            ]);

            echo json_encode(['success' => true, 'message' => "Successfully deleted user '{$targetUser['username']}'."]);
        } catch (\PDOException $e) {
            error_log("Database error in User Delete: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to delete user account due to database error.']);
        }
        exit();

    default:
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            echo json_encode(['success' => false, 'message' => 'Invalid action request.']);
        } else {
            header("Location: ../users.php");
        }
        exit();
}
