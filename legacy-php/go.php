<?php

require __DIR__ . '/includes/functions.php';

$siteId = isset($_GET['site']) ? trim((string) $_GET['site']) : '';
if ($siteId === '' || preg_match('/^[A-Za-z0-9_-]+$/', $siteId) !== 1) {
    http_response_code(400);
    exit('无效的站点参数');
}

$target = null;
$found = null;
foreach (toolbox_all_sites() as $site) {
    if ($site['id'] === $siteId) {
        $found = $site;
        break;
    }
}

if (!$found || empty($found['url'])) {
    http_response_code(404);
    exit('站点不存在');
}

$target = $found['url'];
if (isset($_GET['link'])) {
    $linkIndex = (int) $_GET['link'];
    $extraLinks = toolbox_extra_links($found);
    if (isset($extraLinks[$linkIndex], $extraLinks[$linkIndex]['url'])) {
        $target = $extraLinks[$linkIndex]['url'];
    }
}

toolbox_track_site_click($siteId, $target);
header('Location: ' . $target, true, 302);
exit;
