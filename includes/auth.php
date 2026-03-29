<?php
// User authentication functions
require_once '../config/db.php';
function login_user($username, $password) {
    // TODO: Implement login logic
}
function register_user($username, $password) {
    // TODO: Implement registration logic
}
function logout_user() {
    session_unset();
    session_destroy();
}
