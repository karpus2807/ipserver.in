<?php
// Home page - redirect to login or dashboard
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
} else {
    header('Location: login.php');
    exit();
}
