<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/logger.php';
app_deny_direct_access(__FILE__);

final class AccessControl {

    public static function requireLogin() {

        app_start_session();

        if (!self::isLoggedIn()) {
            self::redirectToLogin();
        }

    }

    public static function hasRole($role) {

        return is_string($role) &&
            self::isLoggedIn() &&
            isset($_SESSION['role']) &&
            is_string($_SESSION['role']) &&
            hash_equals($role, $_SESSION['role']);

    }

    public static function requireRole($role) {

        self::requireLogin();

        if (!self::hasRole($role)) {
            self::denyAccess();
        }

    }

    public static function requireAdmin() {

        self::requireRole(APP_ROLE_ADMIN);

    }

    public static function isLoggedIn() {

        return self::userId() > 0;

    }

    public static function userId() {

        $userId = filter_var(
            $_SESSION['user_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        return $userId !== false && $userId > 0
            ? $userId
            : 0;

    }

    private static function redirectToLogin() {

        header('Location: ' . rtrim(APP_URL, '/') . '/auth/login.php');
        exit;

    }

    private static function denyAccess() {

        app_log(
            'Administrator mengakses rute yang tidak diizinkan.',
            'WARNING',
            'authz.denied',
            [
                'route' => basename($_SERVER['SCRIPT_NAME'] ?? 'unknown'),
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            ]
        );

        app_render_http_error(403);

    }
}

function require_login() {
    AccessControl::requireLogin();
}

function has_role($role) {
    return AccessControl::hasRole($role);
}

function require_role($role) {
    AccessControl::requireRole($role);
}

function require_admin() {
    AccessControl::requireAdmin();
}

function is_logged_in() {
    return AccessControl::isLoggedIn();
}

function current_user_id() {
    return AccessControl::userId();
}
