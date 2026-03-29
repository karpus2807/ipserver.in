<?php
require_once '../includes/session.php';
logout_user();
header('Location: login.php');
exit();
