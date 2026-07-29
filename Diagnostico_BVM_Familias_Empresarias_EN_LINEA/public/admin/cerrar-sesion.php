<?php
require_once dirname(__DIR__, 2) . '/private/bootstrap.php';
$user = bvm_admin_user();
if ($user) {
    AuditRepository::log('logout', (int)$user['id']);
}
bvm_logout();
header('Location: ' . bvm_base_url() . '/index.php');
exit;
