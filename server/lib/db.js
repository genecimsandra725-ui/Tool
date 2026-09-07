const mysql = require('mysql2/promise');
const config = require('../config');

let pool = null;
let initialized = false;

async function init() {
  if (initialized) {
    return pool;
  }
  initialized = true;
  try {
    pool = mysql.createPool(config.db);
    await pool.query('SELECT 1');
  } catch (error) {
    pool = null;
  }
  return pool;
}

function isAvailable() {
  return pool !== null;
}

async function query(sql, params = []) {
  if (!pool) {
    await init();
  }
  if (!pool) {
    const error = new Error('MySQL 服务不可用');
    error.status = 503;
    throw error;
  }
  const [rows] = await pool.execute(sql, params);
  return rows;
}

async function close() {
  if (pool) {
    await pool.end();
    pool = null;
  }
}

module.exports = { init, isAvailable, query, close };
