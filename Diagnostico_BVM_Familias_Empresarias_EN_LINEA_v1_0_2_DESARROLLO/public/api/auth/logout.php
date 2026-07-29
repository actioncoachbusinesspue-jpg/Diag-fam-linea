<?php
require_once dirname(__DIR__, 3) . '/private/bootstrap.php';
bvm_require_method('POST');
$user = bvm_require_admin_api();
AuditRepository::log('logout', (int)$user['id']);
bvm_logout();
bvm_json_response(['ok' => true, 'redirect' => bvm_base_url() . '/index.php']);
