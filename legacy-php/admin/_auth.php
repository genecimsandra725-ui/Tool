<?php

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/auth.php';

$adminUser = toolbox_require_admin();
$adminPdo = toolbox_db_required();
$adminConfig = toolbox_config();

function admin_status_text($status)
{
    $labels = [
        'pending' => '待处理',
        'processing' => '处理中',
        'resolved' => '已解决',
        'closed' => '已关闭',
    ];
    return isset($labels[$status]) ? $labels[$status] : $status;
}

function admin_feedback_type_text($type)
{
    $labels = [
        'broken' => '链接失效',
        'suggestion' => '建议',
        'other' => '其他',
    ];
    return isset($labels[$type]) ? $labels[$type] : $type;
}
