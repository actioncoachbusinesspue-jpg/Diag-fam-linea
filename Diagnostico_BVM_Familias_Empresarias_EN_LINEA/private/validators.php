<?php
declare(strict_types=1);

/** validators.php — validación de entradas. */

function bvm_valid_answer_value($v): bool
{
    return is_int($v) ? ($v >= 1 && $v <= 5)
        : (is_numeric($v) && (string)(int)$v === (string)$v && (int)$v >= 1 && (int)$v <= 5);
}

function bvm_valid_question_id($id): bool
{
    return is_numeric($id) && (int)$id >= 1 && (int)$id <= BVM_TOTAL_QUESTIONS;
}

function bvm_valid_external_id($id): bool
{
    return is_string($id) && in_array($id, BVM_EXTERNAL_QUESTION_IDS, true);
}

function bvm_valid_participant_name($name): bool
{
    if (!is_string($name)) {
        return false;
    }
    $n = trim($name);
    return $n !== '' && mb_strlen($n, 'UTF-8') >= 2 && mb_strlen($n, 'UTF-8') <= 120;
}

function bvm_valid_family_name($name): bool
{
    if (!is_string($name)) {
        return false;
    }
    $n = trim($name);
    return $n !== '' && mb_strlen($n, 'UTF-8') >= 2 && mb_strlen($n, 'UTF-8') <= 150;
}

function bvm_valid_generation($g): bool
{
    return is_string($g) && in_array($g, BVM_GENERATIONS, true);
}

function bvm_valid_participation_role($r): bool
{
    return is_string($r) && in_array($r, BVM_PARTICIPATION_ROLES, true);
}

function bvm_valid_family_status($s): bool
{
    return is_string($s) && in_array($s, BVM_FAMILY_STATUSES, true);
}

function bvm_valid_slug($s): bool
{
    return is_string($s) && preg_match('/^[a-z0-9]{6,32}$/', $s) === 1;
}

function bvm_valid_date($d): bool
{
    if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return false;
    }
    [$y, $m, $day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}
