<?php
require_once '../core/security.php';
ensure_session_started();
require_once '../core/db.php';
require_once '../core/security.php';
require_role_or_redirect($pdo, 'admin', '../auth/login.php');

header('Location: dashboard.php');
exit();
