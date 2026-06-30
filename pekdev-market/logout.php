<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/auth.php';
logoutUser();
header("Location: " . BASE_URL . '/login.php');
exit;