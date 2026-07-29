<?php
declare(strict_types=1);

/** csrf.php — protección CSRF por token de sesión. */

function bvm_csrf_token(): string
{
    bvm_session_start();
    if (empty($_SESSION['bvm_csrf'])) {
        $_SESSION['bvm_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['bvm_csrf'];
}

function bvm_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(bvm_csrf_token()) . '">';
}

/** Valida token recibido por encabezado X-CSRF-Token o campo csrf_token. */
function bvm_csrf_verify(): bool
{
    bvm_session_start();
    $expected = $_SESSION['bvm_csrf'] ?? '';
    if ($expected === '') {
        return false;
    }
    $got = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if ($got === '') {
        $json = bvm_json_input();
        $got = (string)($json['csrf_token'] ?? '');
    }
    return is_string($got) && $got !== '' && hash_equals($expected, $got);
}

function bvm_csrf_require(): void
{
    if (!bvm_csrf_verify()) {
        bvm_json_error('Solicitud rechazada por seguridad (CSRF).', 403);
    }
}
