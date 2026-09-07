<?php

function toolbox_db()
{
    static $pdo = null;
    static $attempted = false;

    if ($attempted) {
        return $pdo;
    }
    $attempted = true;

    $config = toolbox_config();
    $db = $config['database'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        (int) $db['port'],
        $db['name'],
        $db['charset']
    );

    try {
        $pdo = new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $exception) {
        return null;
    }
}

function toolbox_db_required()
{
    $pdo = toolbox_db();
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    http_response_code(500);
    exit('数据库连接失败：请先在 includes/config.php 配置 MySQL，并执行 install/database.sql 初始化。');
}

function toolbox_ip_hash()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $salt = toolbox_config()['siteName'];
    return hash('sha256', $salt . '|' . $ip);
}

function toolbox_user_agent()
{
    return isset($_SERVER['HTTP_USER_AGENT'])
        ? toolbox_truncate($_SERVER['HTTP_USER_AGENT'], 500)
        : '';
}

function toolbox_referer()
{
    return isset($_SERVER['HTTP_REFERER'])
        ? toolbox_truncate($_SERVER['HTTP_REFERER'], 500)
        : '';
}
