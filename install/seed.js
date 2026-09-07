const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');

const config = require('../server/config');
const auth = require('../server/lib/auth');

async function connectWithoutDatabase() {
  return mysql.createConnection({
    host: config.db.host,
    port: config.db.port,
    user: config.db.user,
    password: config.db.password,
    multipleStatements: true,
  });
}

async function connectWithDatabase() {
  return mysql.createConnection({
    host: config.db.host,
    port: config.db.port,
    user: config.db.user,
    password: config.db.password,
    database: config.db.database,
    charset: config.db.charset,
    multipleStatements: true,
  });
}

async function createDatabase(connection) {
  const dbName = config.db.database;
  await connection.query(
    `CREATE DATABASE IF NOT EXISTS \`${dbName}\`
     DEFAULT CHARACTER SET utf8mb4
     DEFAULT COLLATE utf8mb4_unicode_ci`
  );
}

async function runSchema(connection) {
  const schemaPath = path.join(__dirname, 'database.sql');
  const raw = fs.readFileSync(schemaPath, 'utf8');
  const statements = raw
    .split('\n')
    .filter((line) => !line.trim().startsWith('--'))
    .join('\n')
    .split(';')
    .map((statement) => statement.trim())
    .filter(Boolean);

  for (const statement of statements) {
    await connection.query(statement);
  }
}

async function seedCategories(connection) {
  const insert = `
    INSERT INTO categories (slug, name, icon, description, sort_order)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      name = VALUES(name),
      icon = VALUES(icon),
      description = VALUES(description),
      sort_order = VALUES(sort_order)`;
  for (let index = 0; index < config.categories.length; index += 1) {
    const item = config.categories[index];
    await connection.execute(insert, [
      item.slug,
      item.name,
      item.icon,
      item.desc,
      index + 1,
    ]);
  }
}

async function seedSites(connection) {
  const data = JSON.parse(fs.readFileSync(config.dataFile, 'utf8'));
  const sites = Array.isArray(data.sites) ? data.sites : [];
  const insert = `
    INSERT INTO sites
      (id, category, name, description, url, extra_links, sort_order, is_active, click_count, like_count)
    VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
    ON DUPLICATE KEY UPDATE
      category = VALUES(category),
      name = VALUES(name),
      description = VALUES(description),
      url = VALUES(url),
      extra_links = VALUES(extra_links),
      sort_order = VALUES(sort_order),
      is_active = 1,
      like_count = VALUES(like_count)`;

  for (let index = 0; index < sites.length; index += 1) {
    const site = sites[index];
    const links = Array.isArray(site.links) ? site.links : [];
    await connection.execute(insert, [
      site.id,
      site.category || 'tool',
      site.name,
      site.desc || '',
      site.url || '',
      JSON.stringify(links),
      index + 1,
      0,
      Number(site.like_count || 0),
    ]);
  }
}

async function seedUsers(connection) {
  const [rows] = await connection.query('SELECT COUNT(*) AS n FROM users');
  if (Number(rows[0].n) > 0) {
    return;
  }
  const passwordHash = await auth.hashPassword('admin123');
  await connection.execute(
    `INSERT INTO users
      (username, password_hash, nickname, email, role, is_active)
     VALUES (?, ?, ?, '', 'admin', 1)`,
    ['admin', passwordHash, '管理员']
  );
}

async function main() {
  const bootstrap = await connectWithoutDatabase();
  await createDatabase(bootstrap);
  await bootstrap.end();

  const connection = await connectWithDatabase();
  try {
    await runSchema(connection);
    await seedCategories(connection);
    await seedSites(connection);
    await seedUsers(connection);
    const [siteCount] = await connection.query('SELECT COUNT(*) AS n FROM sites');
    const [userCount] = await connection.query('SELECT COUNT(*) AS n FROM users');
    console.log(`seed 完成：${siteCount[0].n} 个站点，${userCount[0].n} 个用户`);
    console.log('后台账号：admin / admin123');
  } finally {
    await connection.end();
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
