<?php
require_once dirname(__DIR__) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
$user = bvm_admin_user();
if ($user) {
    AuditRepository::log('logout', (int)$user['id']);
}
bvm_logout();
header('Location: ' . bvm_base_url() . '/index.php');
exit;
