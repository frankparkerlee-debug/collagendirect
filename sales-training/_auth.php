<?php
/**
 * Sales Training Portal - Authentication
 *
 * Include this file at the top of any training page that requires authentication.
 * Supports CollagenDirect employees and active sales reps/distributors.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection for status verification
require_once __DIR__ . '/../admin/db.php';

// Authentication check
$authorized = false;
$user_email = '';
$user_name = '';
$user_type = '';
$is_sales_rep = false;
$sales_rep_id = null;
$company_name = '';

if (isset($_SESSION['user_email']) && isset($_SESSION['user_type'])) {
    $user_email = $_SESSION['user_email'];
    $user_name = $_SESSION['user_name'] ?? '';
    $user_type = $_SESSION['user_type'];
    $is_sales_rep = $_SESSION['is_sales_rep'] ?? false;
    $sales_rep_id = $_SESSION['sales_rep_id'] ?? null;
    $company_name = $_SESSION['company_name'] ?? '';

    if ($user_type === 'employee') {
        // Employee - verify still active in admin_users
        try {
            $stmt = $pdo->prepare("SELECT status FROM admin_users WHERE LOWER(email) = ? AND status = 'active'");
            $stmt->execute([strtolower($user_email)]);
            if ($stmt->fetch()) {
                $authorized = true;
            }
        } catch (Exception $e) {
            // If DB check fails, deny access to be safe
            $authorized = false;
        }
    } elseif ($user_type === 'sales_rep' && $sales_rep_id) {
        // Sales rep/distributor - verify still active
        try {
            $stmt = $pdo->prepare("SELECT status FROM sales_reps WHERE id = ? AND status = 'active'");
            $stmt->execute([$sales_rep_id]);
            if ($stmt->fetch()) {
                $authorized = true;
            }
        } catch (Exception $e) {
            // If DB check fails, deny access to be safe
            $authorized = false;
        }
    }
}

if (!$authorized) {
    // Clear potentially stale session data
    unset($_SESSION['user_email'], $_SESSION['user_name'], $_SESSION['user_type'],
          $_SESSION['is_sales_rep'], $_SESSION['sales_rep_id'], $_SESSION['company_name']);
    header('Location: login.php');
    exit;
}

// Display name - use stored name or fall back to email username
$displayName = $user_name ?: explode('@', $user_email)[0];
