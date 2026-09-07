<?php

require_once __DIR__ . '/database.php';

$TOOLBOX_CONFIG = null;
$TOOLBOX_JSON_DATA = null;

function toolbox_config()
{
    global $TOOLBOX_CONFIG;
    if ($TOOLBOX_CONFIG === null) {
        $TOOLBOX_CONFIG = require __DIR__ . '/config.php';
    }
    return $TOOLBOX_CONFIG;
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function toolbox_json_data()
{
    global $TOOLBOX_JSON_DATA;
    if ($TOOLBOX_JSON_DATA === null) {
        $path = __DIR__ . '/../data/sites.json';
        $raw = file_exists($path) ? file_get_contents($path) : '{}';
        $decoded = json_decode($raw, true);
        $TOOLBOX_JSON_DATA = is_array($decoded) ? $decoded : ['sites' => []];
        if (!isset($TOOLBOX_JSON_DATA['meta'])) {
            $TOOLBOX_JSON_DATA['meta'] = [];
        }
        if (!isset($TOOLBOX_JSON_DATA['sites'])) {
            $TOOLBOX_JSON_DATA['sites'] = [];
        }
    }
    return $TOOLBOX_JSON_DATA;
}

function toolbox_truncate($value, $max = 500)
{
    $value = (string) $value;
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return preg_match('/^.{0,' . (int) $max . '}/us', $value, $match)
        ? $match[0]
        : substr($value, 0, $max);
}

function toolbox_like_file_counts()
{
    $path = __DIR__ . '/../data/likes.json';
    if (!file_exists($path)) {
        return [];
    }
    $likes = json_decode((string) file_get_contents($path), true);
    return is_array($likes) ? $likes : [];
}

function toolbox_db_sites($activeOnly = true)
{
    $pdo = toolbox_db();
    if (!$pdo) {
        return null;
    }
    try {
        $sql = 'SELECT * FROM sites';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY category ASC, sort_order ASC, id ASC';
        $rows = $pdo->query($sql)->fetchAll();
    } catch (PDOException $exception) {
        return null;
    }

    $likes = toolbox_like_file_counts();
    foreach ($rows as &$row) {
        $row['links'] = toolbox_decode_links($row['extra_links'] ?? '');
        $row['like_count'] = isset($likes[$row['id']])
            ? (int) $likes[$row['id']]
            : (int) ($row['like_count'] ?? 0);
        unset($row['extra_links']);
    }
    return $rows;
}

function toolbox_json_sites($activeOnly = true)
{
    $sites = toolbox_json_data()['sites'];
    if (!$activeOnly) {
        return $sites;
    }
    return array_values(
        array_filter($sites, function ($site) {
            return !isset($site['is_active']) || (int) $site['is_active'] === 1;
        })
    );
}

function toolbox_all_sites()
{
    $dbSites = toolbox_db_sites(true);
    if ($dbSites !== null) {
        return $dbSites;
    }
    $likes = toolbox_like_file_counts();
    return array_map(function ($site) use ($likes) {
        $site['like_count'] = isset($likes[$site['id']])
            ? (int) $likes[$site['id']]
            : 0;
        return $site;
    }, toolbox_json_sites(true));
}

function toolbox_decode_links($value)
{
    if (is_array($value)) {
        return $value;
    }
    if (is_string($value) && $value !== '') {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return [];
}

function toolbox_categories()
{
    $config = toolbox_config();
    return $config['categories'];
}

function toolbox_category_counts()
{
    $counts = [];
    foreach (toolbox_all_sites() as $site) {
        $slug = isset($site['category']) ? $site['category'] : 'tool';
        if (!isset($counts[$slug])) {
            $counts[$slug] = 0;
        }
        $counts[$slug]++;
    }
    return $counts;
}

function toolbox_find_category($slug)
{
    foreach (toolbox_categories() as $category) {
        if ($category['slug'] === $slug) {
            return $category;
        }
    }
    return null;
}

function toolbox_selected_sites($slug)
{
    $sites = toolbox_all_sites();
    if ($slug === 'all') {
        return $sites;
    }
    return array_values(
        array_filter($sites, function ($site) use ($slug) {
            return isset($site['category']) && $site['category'] === $slug;
        })
    );
}

function toolbox_first_letter($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '#';
    }
    if (preg_match('/./us', $value, $match)) {
        return $match[0];
    }
    return '#';
}

function toolbox_host($url)
{
    $parts = parse_url((string) $url);
    return isset($parts['host']) ? $parts['host'] : '';
}

function toolbox_favicon($url)
{
    $host = toolbox_host($url);
    return $host !== ''
        ? 'https://icons.duckduckgo.com/ip3/' . rawurlencode($host) . '.ico'
        : '';
}

function toolbox_like_count($siteId)
{
    $likes = toolbox_like_file_counts();
    if (isset($likes[$siteId])) {
        return (int) $likes[$siteId];
    }
    $pdo = toolbox_db();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare('SELECT like_count FROM sites WHERE id = ?');
            $stmt->execute([$siteId]);
            $row = $stmt->fetch();
            if ($row) {
                return (int) $row['like_count'];
            }
        } catch (PDOException $exception) {
            // Tables not initialized yet; fall through to JSON counts.
        }
    }
    return 0;
}

function toolbox_site_like_count($site)
{
    if (isset($site['like_count'])) {
        return (int) $site['like_count'];
    }
    return toolbox_like_count(isset($site['id']) ? $site['id'] : '');
}

function toolbox_extra_links($site)
{
    $siteId = isset($site['id']) ? $site['id'] : '';
    $primary = isset($site['url']) ? $site['url'] : '';
    $links = isset($site['links']) && is_array($site['links']) ? $site['links'] : [];
    $result = [];
    foreach ($links as $index => $link) {
        $href = isset($link['url']) ? $link['url'] : '';
        if ($href === '' || $href === $primary) {
            continue;
        }
        $result[] = [
            'index' => count($result),
            'label' => isset($link['label']) && $link['label'] !== ''
                ? $link['label']
                : '备用链接',
            'url' => $href,
        ];
    }
    return $result;
}

function toolbox_render_extra_links($site)
{
    $siteId = isset($site['id']) ? $site['id'] : '';
    foreach (toolbox_extra_links($site) as $link) {
        echo '<a class="extra-link" href="go.php?site=' . e($siteId) . '&link=' . (int) $link['index']
            . '" target="_blank" rel="noopener noreferrer">' . e($link['label']) . '</a>';
    }
}

function toolbox_render_site_card($site)
{
    $id = isset($site['id']) ? $site['id'] : '';
    $name = isset($site['name']) ? $site['name'] : '';
    $desc = isset($site['desc']) ? $site['desc'] : '';
    $url = isset($site['url']) ? $site['url'] : '';
    $count = toolbox_site_like_count($site);
    $letter = toolbox_first_letter($name);
    $favicon = toolbox_favicon($url);
    $hasLinks = count(toolbox_extra_links($site)) > 0;
    ?>
    <article class="site-card">
        <div class="card-head">
            <span class="site-icon">
                <span class="icon-letter"><?= e($letter) ?></span>
                <?php if ($favicon !== ''): ?>
                    <img src="<?= e($favicon) ?>" alt="" width="28" height="28" loading="lazy" onerror="this.remove()">
                <?php endif; ?>
            </span>
            <div class="card-heading">
                <h3 class="site-name">
                    <a href="go.php?site=<?= e($id) ?>" target="_blank" rel="noopener noreferrer"><?= e($name) ?></a>
                </h3>
                <div class="site-domain"><?= e(toolbox_host($url)) ?></div>
            </div>
        </div>
        <p class="site-desc"><?= e($desc) ?></p>
        <div class="card-actions">
            <button
                type="button"
                class="like-btn"
                data-id="<?= e($id) ?>"
                aria-pressed="false"
                title="点赞"
            >
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M12 20.5s-7.5-4.7-9.3-9A5.4 5.4 0 0 1 12 6.7a5.4 5.4 0 0 1 9.3 4.8c-1.8 4.3-9.3 9-9.3 9Z"></path>
                </svg>
                <span class="like-count"><?= (int) $count ?></span>
            </button>
            <a class="visit-link" href="go.php?site=<?= e($id) ?>" target="_blank" rel="noopener noreferrer">
                <span>访问</span>
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M7 17 17 7M9 7h8v8"></path>
                </svg>
            </a>
        </div>
        <?php if ($hasLinks): ?>
            <div class="extra-links"><?php toolbox_render_extra_links($site); ?></div>
        <?php endif; ?>
    </article>
    <?php
}

function toolbox_render_cards($sites)
{
    foreach ($sites as $site) {
        toolbox_render_site_card($site);
    }
}

function toolbox_track_page_view($category = 'all', $pageUrl = '')
{
    $pdo = toolbox_db();
    if (!$pdo) {
        return;
    }
    try {
        $pageUrl = $pageUrl !== '' ? $pageUrl : ($_SERVER['REQUEST_URI'] ?? 'index.php');
        $stmt = $pdo->prepare(
            'INSERT INTO page_visits (page_url, category, referer, user_agent, ip_hash, visited_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            toolbox_truncate($pageUrl, 500),
            toolbox_truncate($category, 32),
            toolbox_truncate(toolbox_referer(), 500),
            toolbox_truncate(toolbox_user_agent(), 500),
            toolbox_ip_hash(),
        ]);
    } catch (PDOException $exception) {
        // Analytics is best-effort.
    }
}

function toolbox_track_site_click($siteId, $targetUrl)
{
    $pdo = toolbox_db();
    if (!$pdo) {
        return;
    }
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO site_clicks (site_id, target_url, referer, user_agent, ip_hash, clicked_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $siteId,
            toolbox_truncate($targetUrl, 2048),
            toolbox_truncate(toolbox_referer(), 500),
            toolbox_truncate(toolbox_user_agent(), 500),
            toolbox_ip_hash(),
        ]);
        $update = $pdo->prepare('UPDATE sites SET click_count = click_count + 1 WHERE id = ?');
        $update->execute([$siteId]);
    } catch (PDOException $exception) {
        // Analytics is best-effort; never block the redirect.
    }
}

function toolbox_sync_sites_json()
{
    $pdo = toolbox_db_required();
    $sites = $pdo->query(
        'SELECT id, category, name, description AS `desc`, url, extra_links, sort_order,
                is_active, click_count, like_count
         FROM sites
         ORDER BY category ASC, sort_order ASC, id ASC'
    )->fetchAll();

    $outputSites = [];
    foreach ($sites as $site) {
        if ((int) $site['is_active'] !== 1) {
            continue;
        }
        $links = toolbox_decode_links($site['extra_links']);
        $outputSites[] = [
            'id' => $site['id'],
            'name' => $site['name'],
            'desc' => $site['desc'],
            'url' => $site['url'],
            'category' => $site['category'],
            'links' => $links,
            'like_count' => (int) $site['like_count'],
        ];
    }

    $config = toolbox_config();
    $payload = json_encode(
        [
            'meta' => [
                'title' => $config['siteTitle'],
                'source' => $config['sourceUrl'],
                'updated' => date('Y-m-d'),
            ],
            'sites' => $outputSites,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );

    $path = __DIR__ . '/../data/sites.json';
    $temp = $path . '.tmp';
    file_put_contents($temp, $payload, LOCK_EX);
    rename($temp, $path);
}
