const fs = require('fs');
const http = require('http');
const path = require('path');

const config = require('./config');
const db = require('./lib/db');
const auth = require('./lib/auth');
const sites = require('./lib/sites');
const analytics = require('./lib/analytics');
const v = require('./lib/validate');

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon',
  '.woff2': 'font/woff2',
};

function sendJson(res, status, payload) {
  res.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Cache-Control': 'no-store',
  });
  res.end(JSON.stringify(payload));
  return true;
}

function sendError(res, error) {
  const status = error.status || 500;
  if (status === 500) {
    console.error(error);
  }
  sendJson(res, status, {
    ok: false,
    error: error.message || '服务器内部错误',
  });
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    let raw = '';
    let size = 0;
    req.on('data', (chunk) => {
      size += chunk.length;
      if (size > 2 * 1024 * 1024) {
        reject(new Error('请求体过大'));
        req.destroy();
        return;
      }
      raw += chunk;
    });
    req.on('end', () => {
      if (!raw) {
        resolve({});
        return;
      }
      try {
        resolve(JSON.parse(raw));
      } catch (error) {
        reject(Object.assign(new Error('无效的 JSON 请求体'), { status: 400 }));
      }
    });
    req.on('error', reject);
  });
}

function setAuthCookie(res, token) {
  res.setHeader(
    'Set-Cookie',
    `${config.cookieName}=${token}; Path=/; HttpOnly; SameSite=Lax; Max-Age=${config.tokenTtlDays * 86400}`
  );
}

function clearAuthCookie(res) {
  res.setHeader(
    'Set-Cookie',
    `${config.cookieName}=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0`
  );
}

function publicUser(user) {
  if (!user) {
    return null;
  }
  return {
    id: user.id,
    username: user.username,
    nickname: user.nickname,
    email: user.email,
    role: user.role,
  };
}

async function serveStatic(req, res, pathname) {
  const safeName = pathname === '/' ? '/index.html' : decodeURIComponent(pathname);
  const filePath = path.join(config.publicDir, safeName);
  if (!filePath.startsWith(config.publicDir)) {
    sendJson(res, 403, { ok: false, error: 'forbidden' });
    return;
  }

  try {
    const stat = fs.statSync(filePath);
    if (stat.isFile()) {
      const ext = path.extname(filePath).toLowerCase();
      if (filePath === path.join(config.publicDir, 'index.html')) {
        const [categories, siteList, meta] = await Promise.all([
          sites.categoriesWithCounts(),
          sites.getSites({}),
          Promise.resolve(sites.metaPayload()),
        ]);
        const initial = JSON.stringify({
          meta,
          categories,
          sites: siteList,
        }).replace(/</g, '\\u003c');
        let html = fs.readFileSync(filePath, 'utf8');
        html = html.replace('__INITIAL_DATA__', initial);
        res.writeHead(200, {
          'Content-Type': MIME['.html'],
          'Cache-Control': 'no-cache',
        });
        res.end(html);
        return;
      }
      res.writeHead(200, {
        'Content-Type': MIME[ext] || 'application/octet-stream',
        'Cache-Control': ext === '.html' ? 'no-cache' : 'public, max-age=3600',
      });
      fs.createReadStream(filePath).pipe(res);
      return;
    }
  } catch (error) {
    // continue to SPA fallback
  }

  const indexFile = path.join(config.publicDir, 'index.html');
  if (fs.existsSync(indexFile) && !path.extname(pathname)) {
    res.writeHead(200, { 'Content-Type': MIME['.html'], 'Cache-Control': 'no-cache' });
    fs.createReadStream(indexFile).pipe(res);
    return;
  }

  sendJson(res, 404, { ok: false, error: 'not found' });
}

async function handlePublicApi(req, res, url, body) {
  const pathname = url.pathname;

  if (req.method === 'GET' && pathname === '/api/meta') {
    return sendJson(res, 200, { ok: true, data: sites.metaPayload() });
  }

  if (req.method === 'GET' && pathname === '/api/categories') {
    const list = await sites.categoriesWithCounts();
    return sendJson(res, 200, { ok: true, data: list });
  }

  if (req.method === 'GET' && pathname === '/api/sites') {
    const list = await sites.getSites({
      category: url.searchParams.get('category') || 'all',
      keyword: url.searchParams.get('q') || '',
    });
    return sendJson(res, 200, { ok: true, data: list });
  }

  if (req.method === 'POST' && pathname === '/api/analytics/visit') {
    await analytics.recordVisit(req, body);
    return sendJson(res, 200, { ok: true });
  }

  if (req.method === 'POST' && pathname === '/api/analytics/click') {
    if (!v.cleanString(body.siteId, 64)) {
      return sendJson(res, 400, { ok: false, error: 'siteId 参数无效' });
    }
    const result = await analytics.recordClick(req, body);
    return sendJson(res, 200, { ok: true, url: result.url, siteId: result.site.id });
  }

  if (req.method === 'POST' && pathname === '/api/sites/like') {
    const siteId = v.cleanString(body.siteId, 64);
    if (!siteId) {
      return sendJson(res, 400, { ok: false, error: 'siteId 参数无效' });
    }
    let count = 0;
    if (db.isAvailable()) {
      const rows = await db.query('SELECT like_count FROM sites WHERE id = ?', [siteId]);
      if (!rows.length) {
        return sendJson(res, 404, { ok: false, error: '站点不存在' });
      }
      count = Number(rows[0].like_count) + 1;
      await db.query('UPDATE sites SET like_count = ? WHERE id = ?', [count, siteId]);
      await sites.syncJsonFromDb();
    } else {
      const list = sites.loadJsonSites();
      const index = list.findIndex((item) => item.id === siteId);
      if (index < 0) {
        return sendJson(res, 404, { ok: false, error: '站点不存在' });
      }
      count = Number(list[index].like_count || 0) + 1;
      list[index].like_count = count;
      const raw = JSON.parse(fs.readFileSync(config.dataFile, 'utf8'));
      raw.sites = list;
      fs.writeFileSync(config.dataFile, JSON.stringify(raw, null, 2), 'utf8');
    }
    return sendJson(res, 200, { ok: true, count });
  }

  if (req.method === 'POST' && pathname === '/api/auth/register') {
    if (!db.isAvailable()) {
      return sendJson(res, 503, { ok: false, error: 'MySQL 未连接，无法注册' });
    }
    const username = v.cleanString(body.username, 32);
    const password = String(body.password || '');
    const nickname = v.cleanString(body.nickname, 60);
    const email = v.cleanString(body.email, 160);
    if (!v.validUsername(username) || username.length < 3) {
      return sendJson(res, 400, { ok: false, error: '用户名需为 3-32 位字母、数字或下划线' });
    }
    if (password.length < 6 || password.length > 72) {
      return sendJson(res, 400, { ok: false, error: '密码长度需在 6-72 位之间' });
    }
    if (!v.validEmail(email)) {
      return sendJson(res, 400, { ok: false, error: '邮箱格式不正确' });
    }
    const duplicate = await db.query('SELECT id FROM users WHERE username = ?', [username]);
    if (duplicate.length) {
      return sendJson(res, 409, { ok: false, error: '用户名已存在' });
    }
    const passwordHash = await auth.hashPassword(password);
    const result = await db.query(
      'INSERT INTO users (username, password_hash, nickname, email, role, is_active) VALUES (?, ?, ?, ?, ?, 1)',
      [username, passwordHash, nickname, email, 'user']
    );
    const user = {
      id: result.insertId,
      username,
      nickname,
      email,
      role: 'user',
    };
    setAuthCookie(res, auth.signToken(user));
    return sendJson(res, 200, { ok: true, user: publicUser(user) });
  }

  if (req.method === 'POST' && pathname === '/api/auth/login') {
    if (!db.isAvailable()) {
      return sendJson(res, 503, { ok: false, error: 'MySQL 未连接，无法登录' });
    }
    const username = v.cleanString(body.username, 32);
    const password = String(body.password || '');
    if (!username) {
      return sendJson(res, 400, { ok: false, error: '用户名不能为空' });
    }
    if (!password) {
      return sendJson(res, 400, { ok: false, error: '密码不能为空' });
    }
    const rows = await db.query(
      'SELECT * FROM users WHERE username = ? AND is_active = 1',
      [username]
    );
    if (!rows.length || !(await auth.verifyPassword(password, rows[0].password_hash))) {
      return sendJson(res, 401, { ok: false, error: '账号或密码错误' });
    }
    const user = rows[0];
    await db.query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [user.id]);
    setAuthCookie(res, auth.signToken(user));
    return sendJson(res, 200, { ok: true, user: publicUser(user) });
  }

  if (req.method === 'POST' && pathname === '/api/auth/logout') {
    clearAuthCookie(res);
    return sendJson(res, 200, { ok: true });
  }

  if (req.method === 'GET' && pathname === '/api/auth/me') {
    const user = await auth.userFromRequest(req);
    return sendJson(res, 200, { ok: true, user: publicUser(user) });
  }

  if (pathname === '/api/feedback') {
    const user = await auth.requireUser(req);
    if (req.method === 'GET') {
      const rows = await db.query(
        `SELECT id, type, title, content, site_url, status, admin_note, created_at, updated_at
         FROM feedback WHERE user_id = ? ORDER BY id DESC LIMIT 100`,
        [user.id]
      );
      return sendJson(res, 200, { ok: true, data: rows });
    }
    if (req.method === 'POST') {
      const type = ['broken', 'suggestion', 'other'].includes(v.cleanString(body.type, 20))
        ? v.cleanString(body.type, 20)
        : 'suggestion';
      const content = v.cleanText(body.content, 2000);
      const title = v.cleanString(body.title, 200);
      const siteUrl = v.cleanString(body.siteUrl, 2048);
      if (content.length < 5) {
        return sendJson(res, 400, { ok: false, error: '反馈内容至少 5 个字' });
      }
      if (content.length > 2000) {
        return sendJson(res, 400, { ok: false, error: '反馈内容不能超过 2000 字' });
      }
      if (siteUrl !== '' && !v.safeUrl(siteUrl)) {
        return sendJson(res, 400, { ok: false, error: '相关网址格式不正确' });
      }
      await db.query(
        `INSERT INTO feedback
          (user_id, type, title, content, site_url, status)
         VALUES (?, ?, ?, ?, ?, 'pending')`,
        [user.id, type, title, content, siteUrl]
      );
      return sendJson(res, 200, { ok: true });
    }
  }

  return null;
}

async function handleAdminApi(req, res, url, body, currentUser) {
  const pathname = url.pathname;
  const requireAdmin = currentUser && currentUser.role === 'admin';
  if (!requireAdmin) {
    const error = new Error('无后台管理权限');
    error.status = 403;
    throw error;
  }

  if (req.method === 'GET' && pathname === '/api/admin/stats') {
    const counts = {};
    counts.sites = (await db.query('SELECT COUNT(*) AS n FROM sites'))[0].n;
    counts.users = (await db.query('SELECT COUNT(*) AS n FROM users'))[0].n;
    counts.visits = (await db.query('SELECT COUNT(*) AS n FROM page_visits'))[0].n;
    counts.clicks = (await db.query('SELECT COUNT(*) AS n FROM site_clicks'))[0].n;
    counts.pending = (await db.query("SELECT COUNT(*) AS n FROM feedback WHERE status = 'pending'"))[0].n;
    counts.categories = (await db.query('SELECT COUNT(*) AS n FROM categories'))[0].n;
    const recent = await db.query(
      `SELECT f.*, u.username
       FROM feedback f LEFT JOIN users u ON u.id = f.user_id
       ORDER BY f.id DESC LIMIT 8`
    );
    return sendJson(res, 200, { ok: true, data: { counts, recentFeedback: recent } });
  }

  if (req.method === 'GET' && pathname === '/api/admin/analytics') {
    const days = Math.min(90, Math.max(1, Number(url.searchParams.get('days') || 14)));
    const since = new Date(Date.now() - days * 86400000).toISOString().slice(0, 19).replace('T', ' ');
    const trend = await db.query(
      `SELECT DATE(visited_at) AS day, COUNT(*) AS visits
       FROM page_visits WHERE visited_at >= ?
       GROUP BY DATE(visited_at) ORDER BY day`,
      [since]
    );
    const categoryRows = await db.query(
      `SELECT category, COUNT(*) AS visits
       FROM page_visits WHERE visited_at >= ?
       GROUP BY category ORDER BY visits DESC LIMIT 12`,
      [since]
    );
    const topSites = await db.query(
      `SELECT s.id, s.name, s.url, c.name AS category_name, COUNT(cl.id) AS clicks
       FROM site_clicks cl
       JOIN sites s ON s.id = cl.site_id
       LEFT JOIN categories c ON c.slug = s.category
       GROUP BY cl.site_id
       ORDER BY clicks DESC
       LIMIT 20`
    );
    const recentClicks = await db.query(
      `SELECT cl.clicked_at, cl.target_url, s.name AS site_name, cl.ip_address
       FROM site_clicks cl
       LEFT JOIN sites s ON s.id = cl.site_id
       ORDER BY cl.id DESC
       LIMIT 30`
    );
    const totals = {
      visits: (await db.query('SELECT COUNT(*) AS n FROM page_visits'))[0].n,
      unique: (await db.query('SELECT COUNT(DISTINCT ip_hash) AS n FROM page_visits'))[0].n,
      clicks: (await db.query('SELECT COUNT(*) AS n FROM site_clicks'))[0].n,
    };
    return sendJson(res, 200, {
      ok: true,
      data: { days, totals, trend, categories: categoryRows, topSites, recentClicks },
    });
  }

  if (req.method === 'GET' && pathname === '/api/admin/sites') {
    const rows = await db.query(
      `SELECT s.*, c.name AS category_name
       FROM sites s LEFT JOIN categories c ON c.slug = s.category
       ORDER BY s.category, s.sort_order, s.id`
    );
    return sendJson(res, 200, { ok: true, data: rows.map(sites.normalizeDbSite) });
  }

  if ((req.method === 'POST' || req.method === 'PUT') && pathname === '/api/admin/sites') {
    const id = await saveSiteFromAdmin(body);
    await sites.syncJsonFromDb();
    return sendJson(res, 200, { ok: true, id });
  }

  if ((req.method === 'POST' || req.method === 'PUT') && /^\/api\/admin\/sites\/[^/]+$/.test(pathname)) {
    const id = decodeURIComponent(pathname.split('/').pop());
    const saved = await saveSiteFromAdmin({ ...body, id });
    await sites.syncJsonFromDb();
    return sendJson(res, 200, { ok: true, id: saved });
  }

  if (req.method === 'DELETE' && /^\/api\/admin\/sites\/[^/]+$/.test(pathname)) {
    const id = decodeURIComponent(pathname.split('/').pop());
    await db.query('DELETE FROM sites WHERE id = ?', [id]);
    await sites.syncJsonFromDb();
    return sendJson(res, 200, { ok: true });
  }

  if (req.method === 'GET' && pathname === '/api/admin/users') {
    const rows = await db.query('SELECT id, username, nickname, email, role, is_active, created_at, last_login_at FROM users ORDER BY id DESC');
    return sendJson(res, 200, { ok: true, data: rows });
  }

  if (req.method === 'POST' && pathname === '/api/admin/users') {
    const username = v.cleanString(body.username, 32);
    const password = String(body.password || '');
    const nickname = v.cleanString(body.nickname, 60);
    const email = v.cleanString(body.email, 160);
    if (!v.validUsername(username)) {
      return sendJson(res, 400, { ok: false, error: '用户名需为 3-32 位字母、数字或下划线' });
    }
    if (password.length < 6 || password.length > 72) {
      return sendJson(res, 400, { ok: false, error: '密码长度需在 6-72 位之间' });
    }
    if (!v.validEmail(email)) {
      return sendJson(res, 400, { ok: false, error: '邮箱格式不正确' });
    }
    const role = v.validRole(body.role) ? body.role : 'user';
    const active = body.is_active === false ? 0 : 1;
    const passwordHash = await auth.hashPassword(password);
    const duplicate = await db.query('SELECT id FROM users WHERE username = ?', [username]);
    if (duplicate.length) {
      return sendJson(res, 409, { ok: false, error: '用户名已存在' });
    }
    await db.query(
      'INSERT INTO users (username, password_hash, nickname, email, role, is_active) VALUES (?, ?, ?, ?, ?, ?)',
      [username, passwordHash, nickname, email, role, active]
    );
    return sendJson(res, 200, { ok: true });
  }

  if ((req.method === 'POST' || req.method === 'PUT') && /^\/api\/admin\/users\/\d+$/.test(pathname)) {
    const id = Number(pathname.split('/').pop());
    const role = v.validRole(body.role) ? body.role : 'user';
    const active = body.is_active === false ? 0 : 1;
    if (id === Number(currentUser.id) && (role !== 'admin' || active === 0)) {
      return sendJson(res, 400, { ok: false, error: '不能取消自己的管理员权限或停用自己' });
    }
    const nickname = v.cleanString(body.nickname, 60);
    const email = v.cleanString(body.email, 160);
    if (!v.validEmail(email)) {
      return sendJson(res, 400, { ok: false, error: '邮箱格式不正确' });
    }
    if (body.username !== undefined) {
      const username = v.cleanString(body.username, 32);
      if (!v.validUsername(username)) {
        return sendJson(res, 400, { ok: false, error: '用户名格式不正确' });
      }
      const duplicate = await db.query('SELECT id FROM users WHERE username = ? AND id <> ?', [username, id]);
      if (duplicate.length) {
        return sendJson(res, 409, { ok: false, error: '用户名已存在' });
      }
      await db.query('UPDATE users SET username = ? WHERE id = ?', [username, id]);
    }
    await db.query(
      'UPDATE users SET nickname = ?, email = ?, role = ?, is_active = ? WHERE id = ?',
      [nickname, email, role, active, id]
    );
    if (body.password) {
      if (String(body.password).length < 6 || String(body.password).length > 72) {
        return sendJson(res, 400, { ok: false, error: '密码长度需在 6-72 位之间' });
      }
      const passwordHash = await auth.hashPassword(body.password);
      await db.query('UPDATE users SET password_hash = ? WHERE id = ?', [passwordHash, id]);
    }
    return sendJson(res, 200, { ok: true });
  }

  if (req.method === 'DELETE' && /^\/api\/admin\/users\/\d+$/.test(pathname)) {
    const id = Number(pathname.split('/').pop());
    if (id === Number(currentUser.id)) {
      return sendJson(res, 400, { ok: false, error: '不能删除自己' });
    }
    await db.query('DELETE FROM users WHERE id = ?', [id]);
    return sendJson(res, 200, { ok: true });
  }

  if (req.method === 'GET' && pathname === '/api/admin/feedback') {
    const status = url.searchParams.get('status') || '';
    const params = [];
    let sql = `SELECT f.*, u.username
      FROM feedback f LEFT JOIN users u ON u.id = f.user_id`;
    if (status && status !== 'all') {
      sql += ' WHERE f.status = ?';
      params.push(status);
    }
    sql += ' ORDER BY f.id DESC';
    const rows = await db.query(sql, params);
    return sendJson(res, 200, { ok: true, data: rows });
  }

  if ((req.method === 'POST' || req.method === 'PUT') && /^\/api\/admin\/feedback\/\d+$/.test(pathname)) {
    const id = Number(pathname.split('/').pop());
    const status = ['pending', 'processing', 'resolved', 'closed'].includes(v.cleanString(body.status, 20))
      ? v.cleanString(body.status, 20)
      : 'pending';
    const adminNote = v.cleanText(body.adminNote, 2000);
    const existing = await db.query('SELECT id FROM feedback WHERE id = ?', [id]);
    if (!existing.length) {
      return sendJson(res, 404, { ok: false, error: '反馈工单不存在' });
    }
    await db.query(
      'UPDATE feedback SET status = ?, admin_note = ?, handler_id = ?, updated_at = NOW() WHERE id = ?',
      [status, adminNote, currentUser.id, id]
    );
    return sendJson(res, 200, { ok: true });
  }

  return null;
}

async function saveSiteFromAdmin(body) {
  const name = v.cleanString(body.name, 255);
  const url = v.cleanString(body.url, 2048);
  const category = v.cleanString(body.category, 32);
  const desc = v.cleanText(body.desc, 2000);
  const sortOrder = Number.isFinite(Number(body.sort_order))
    ? Math.max(0, Math.min(99999, Number(body.sort_order)))
    : 0;
  const isActive = body.is_active === false ? 0 : 1;
  if (!name || !v.safeUrl(url)) {
    const error = new Error('名称与地址不能为空');
    error.status = 400;
    throw error;
  }
  if (!v.validCategory(category)) {
    const error = new Error('分类不存在');
    error.status = 400;
    throw error;
  }
  if (!Array.isArray(body.links) || body.links.length > 10) {
    const error = new Error('备用链接格式不正确或数量超限');
    error.status = 400;
    throw error;
  }
  const links = body.links
    .filter((link) => link && link.url)
    .map((link) => {
      const label = v.cleanString(link.label, 100);
      const linkUrl = v.cleanString(link.url, 2048);
      if (!v.safeUrl(linkUrl)) {
        const error = new Error('备用链接包含非法地址');
        error.status = 400;
        throw error;
      }
      return { label, url: linkUrl };
    });
  let id = String(body.id || '');
  if (id !== '' && !/^[A-Za-z0-9_-]{1,64}$/.test(id)) {
    const error = new Error('站点 ID 格式不正确');
    error.status = 400;
    throw error;
  }

  if (id) {
    await db.query(
      `UPDATE sites
       SET category = ?, name = ?, description = ?, url = ?, extra_links = ?, sort_order = ?, is_active = ?
       WHERE id = ?`,
      [category, name, desc, url, JSON.stringify(links), sortOrder, isActive, id]
    );
  } else {
    const maxRow = await db.query(
      "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(id, '-', -1) AS UNSIGNED)), 0) AS n FROM sites"
    );
    const next = Number(maxRow[0].n) + 1;
    id = `am-${String(next).padStart(3, '0')}`;
    await db.query(
      `INSERT INTO sites
        (id, category, name, description, url, extra_links, sort_order, is_active)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [id, category, name, desc, url, JSON.stringify(links), sortOrder, isActive]
    );
  }
  return id;
}

const server = http.createServer(async (req, res) => {
  const parsedUrl = new URL(req.url || '/', 'http://localhost');
  const pathname = parsedUrl.pathname;

  try {
    if (pathname.startsWith('/api/')) {
      const body = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(req.method)
        ? await readBody(req)
        : {};
      const currentUser = await auth.userFromRequest(req);

      if (pathname.startsWith('/api/admin/')) {
        const handled = await handleAdminApi(req, res, parsedUrl, body, currentUser);
        if (handled) return;
      } else {
        const handled = await handlePublicApi(req, res, parsedUrl, body);
        if (handled) return;
      }
      return sendJson(res, 404, { ok: false, error: '接口不存在' });
    }

    if (req.method === 'GET') {
      return serveStatic(req, res, pathname);
    }
    return sendJson(res, 405, { ok: false, error: 'method not allowed' });
  } catch (error) {
    sendError(res, error);
  }
});

server.listen(config.port, () => {
  console.log(`Node 导航站已启动: http://127.0.0.1:${config.port}`);
  db.init().then(() => {
    if (db.isAvailable()) {
      console.log('MySQL 连接成功');
    } else {
      console.log('MySQL 未连接，前台站点数据将回退到 data/sites.json');
    }
  });
});

module.exports = server;
