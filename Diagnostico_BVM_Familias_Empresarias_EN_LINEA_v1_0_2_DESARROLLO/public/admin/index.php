<?php
require_once dirname(__DIR__, 2) . '/private/bootstrap.php';
bvm_require_admin_page();
header('Location: familias.php');
exit;
