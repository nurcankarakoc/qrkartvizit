<?php
require_once '../core/security.php';
ensure_session_started();
session_destroy();
header("Location: ../index.php");
exit();
