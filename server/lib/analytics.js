const crypto = require('crypto');
const config = require('../config');
const db = require('./db');
const sites = require('./sites');

function ipAddress(req) {
  const forwarded = req.headers['x-forwarded-for'];
  if (forwarded) {
    return String(forwarded).split(',')[0].trim();
  }
  return req.socket?.remoteAddress?.replace(/^::ffff:/, '') || '';
}

function ipHash(req) {
  return crypto
    .createHash('sha256')
    .update(config.secret + '|' + ipAddress(req))
    .digest('hex');
}

function userAgent(req) {
  return String(req.headers['user-agent'] || '').slice(0, 500);
}

function referer(req) {
  return String(req.headers.referer || req.headers.referrer || '').slice(0, 500);
}

async function recordVisit(req, body = {}) {
  if (!db.isAvailable()) {
    return;
  }
  const pageUrl = String(body.pageUrl || req.url || '').slice(0, 500);
  const category = String(body.category || 'all').slice(0, 32);
  await db.query(
    `INSERT INTO page_visits
      (page_url, category, referer, user_agent, ip_address, ip_hash, visited_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW())`,
    [
      pageUrl,
      category,
      referer(req),
      userAgent(req),
      ipAddress(req),
      ipHash(req),
    ]
  );
}

async function targetForClick(body) {
  const siteId = String(body.siteId || '');
  const linkIndex = Number.isInteger(body.linkIndex) ? body.linkIndex : null;
  const site = await sites.getSiteById(siteId);
  if (!site) {
    const error = new Error('站点不存在');
    error.status = 404;
    throw error;
  }
  if (linkIndex !== null) {
    const primary = site.url;
    const extras = site.links.filter((link) => link.url !== primary);
    if (extras[linkIndex] && extras[linkIndex].url) {
      return { site, url: extras[linkIndex].url };
    }
  }
  return { site, url: site.url };
}

async function recordClick(req, body = {}) {
  const { site, url } = await targetForClick(body);
  if (db.isAvailable()) {
    await db.query(
      `INSERT INTO site_clicks
        (site_id, target_url, referer, user_agent, ip_address, ip_hash, clicked_at)
       VALUES (?, ?, ?, ?, ?, ?, NOW())`,
      [site.id, url, referer(req), userAgent(req), ipAddress(req), ipHash(req)]
    );
    await db.query('UPDATE sites SET click_count = click_count + 1 WHERE id = ?', [site.id]);
  }
  return { site, url };
}

module.exports = {
  ipAddress,
  recordVisit,
  recordClick,
};
