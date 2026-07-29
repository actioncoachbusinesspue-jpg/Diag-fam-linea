<?php
require_once dirname(__DIR__) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
bvm_require_admin_page();
header('Location: familias.php');
exit;
