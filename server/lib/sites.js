const fs = require('fs');
const path = require('path');
const config = require('../config');
const db = require('./db');

function readJsonFile(filePath, fallback) {
  try {
    const raw = fs.readFileSync(filePath, 'utf8');
    return JSON.parse(raw);
  } catch (error) {
    return fallback;
  }
}

function loadJsonSites() {
  const data = readJsonFile(config.dataFile, { meta: {}, sites: [] });
  return Array.isArray(data.sites) ? data.sites : [];
}

function normalizeDbSite(row) {
  let links = [];
  try {
    links = row.extra_links ? JSON.parse(row.extra_links) : [];
  } catch (error) {
    links = [];
  }
  return {
    id: row.id,
    name: row.name,
    desc: row.description || '',
    url: row.url,
    category: row.category,
    links: Array.isArray(links) ? links : [],
    sort_order: row.sort_order || 0,
    is_active: row.is_active,
    click_count: row.click_count || 0,
    like_count: row.like_count || 0,
    created_at: row.created_at || '',
    updated_at: row.updated_at || '',
  };
}

function normalizeJsonSite(site) {
  return {
    id: site.id,
    name: site.name,
    desc: site.desc || '',
    url: site.url,
    category: site.category || 'tool',
    links: Array.isArray(site.links) ? site.links : [],
    sort_order: site.sort_order || 0,
    is_active: site.is_active === undefined ? 1 : site.is_active,
    click_count: site.click_count || 0,
    like_count: site.like_count || 0,
    created_at: '',
    updated_at: '',
  };
}

function hostOf(url) {
  try {
    return new URL(url).hostname;
  } catch (error) {
    return '';
  }
}

function faviconOf(url) {
  const host = hostOf(url);
  return host ? `https://icons.duckduckgo.com/ip3/${host}.ico` : '';
}

async function getSites(options = {}) {
  const { category = 'all', keyword = '', includeInactive = false } = options;

  if (db.isAvailable()) {
    const where = [];
    const params = [];
    if (!includeInactive) {
      where.push('s.is_active = 1');
    }
    if (category && category !== 'all') {
      where.push('s.category = ?');
      params.push(category);
    }
    if (keyword) {
      where.push('(s.name LIKE ? OR s.description LIKE ? OR s.url LIKE ?)');
      const like = `%${keyword}%`;
      params.push(like, like, like);
    }
    let sql = 'SELECT s.*, c.name AS category_name FROM sites s LEFT JOIN categories c ON c.slug = s.category';
    if (where.length) {
      sql += ' WHERE ' + where.join(' AND ');
    }
    sql += ' ORDER BY s.category, s.sort_order, s.id';
    const rows = await db.query(sql, params);
    return rows.map(normalizeDbSite);
  }

  const jsonSites = loadJsonSites()
    .map(normalizeJsonSite)
    .filter((site) => includeInactive || site.is_active === 1);

  return jsonSites.filter((site) => {
    const categoryOk = !category || category === 'all' || site.category === category;
    const text = `${site.name} ${site.desc} ${site.url}`.toLowerCase();
    const keywordOk = !keyword || text.includes(keyword.toLowerCase());
    return categoryOk && keywordOk;
  });
}

async function getSiteById(id, includeInactive = false) {
  if (db.isAvailable()) {
    const rows = await db.query('SELECT * FROM sites WHERE id = ?', [id]);
    if (rows.length) {
      return normalizeDbSite(rows[0]);
    }
    return null;
  }
  const found = loadJsonSites().map(normalizeJsonSite).find((site) => site.id === id);
  return found && (includeInactive || found.is_active === 1) ? found : null;
}

async function categoriesWithCounts() {
  const sites = await getSites({ includeInactive: false });
  const counts = {};
  sites.forEach((site) => {
    counts[site.category] = (counts[site.category] || 0) + 1;
  });
  return config.categories.map((item) => ({
    ...item,
    count: counts[item.slug] || 0,
  }));
}

async function syncJsonFromDb() {
  const rows = await db.query(
    `SELECT id, category, name, description, url, extra_links, sort_order,
            is_active, click_count, like_count
     FROM sites
     ORDER BY category, sort_order, id`
  );
  const sites = rows
    .filter((row) => Number(row.is_active) === 1)
    .map(normalizeDbSite)
    .map((site) => ({
      id: site.id,
      name: site.name,
      desc: site.desc,
      url: site.url,
      category: site.category,
      links: site.links,
      like_count: site.like_count,
    }));

  const payload = JSON.stringify(
    {
      meta: {
        title: config.site.title,
        source: 'MySQL tool_nav sync',
        updated: new Date().toISOString().slice(0, 10),
      },
      sites,
    },
    null,
    2
  );
  const temp = config.dataFile + '.tmp';
  fs.writeFileSync(temp, payload, 'utf8');
  fs.renameSync(temp, config.dataFile);
}

function metaPayload() {
  return {
    site: config.site,
    friendLinks: config.friendLinks,
    sourceUrl: config.site.sourceUrl || 'https://ncnjicnef38d.feishu.cn/wiki/PHAtw00YAiKcYLkGYyHc6yVmn6d',
  };
}

module.exports = {
  loadJsonSites,
  normalizeDbSite,
  normalizeJsonSite,
  getSites,
  getSiteById,
  categoriesWithCounts,
  syncJsonFromDb,
  hostOf,
  faviconOf,
  metaPayload,
};
