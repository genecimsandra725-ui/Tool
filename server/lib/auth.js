const crypto = require('crypto');
const config = require('../config');
const db = require('./db');

function base64url(input) {
  return Buffer.from(input)
    .toString('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');
}

function hashPassword(password, salt) {
  return new Promise((resolve, reject) => {
    const fixedSalt = salt || crypto.randomBytes(16).toString('hex');
    crypto.scrypt(String(password), fixedSalt, 64, (error, derivedKey) => {
      if (error) {
        reject(error);
        return;
      }
      resolve(`scrypt$${fixedSalt}$${derivedKey.toString('hex')}`);
    });
  });
}

async function verifyPassword(password, stored) {
  const parts = String(stored || '').split('$');
  if (parts.length !== 3 || parts[0] !== 'scrypt') {
    return false;
  }
  const [, salt, expectedHex] = parts;
  const actual = await hashPassword(password, salt);
  const actualHex = actual.split('$')[2];
  const expected = Buffer.from(expectedHex, 'hex');
  const actualBuffer = Buffer.from(actualHex, 'hex');
  return (
    expected.length === actualBuffer.length &&
    crypto.timingSafeEqual(expected, actualBuffer)
  );
}

function signToken(user) {
  const payload = {
    uid: user.id,
    role: user.role,
    exp: Date.now() + config.tokenTtlDays * 24 * 60 * 60 * 1000,
  };
  const body = base64url(JSON.stringify(payload));
  const signature = crypto
    .createHmac('sha256', config.secret)
    .update(body)
    .digest('base64url');
  return `${body}.${signature}`;
}

function verifyToken(token) {
  const [body, signature] = String(token || '').split('.');
  if (!body || !signature) {
    return null;
  }
  const expected = crypto
    .createHmac('sha256', config.secret)
    .update(body)
    .digest('base64url');
  const signatureBuffer = Buffer.from(signature);
  const expectedBuffer = Buffer.from(expected);
  if (
    signatureBuffer.length !== expectedBuffer.length ||
    !crypto.timingSafeEqual(signatureBuffer, expectedBuffer)
  ) {
    return null;
  }
  try {
    const payload = JSON.parse(Buffer.from(body, 'base64url').toString('utf8'));
    if (!payload.exp || payload.exp < Date.now()) {
      return null;
    }
    return payload;
  } catch (error) {
    return null;
  }
}

function parseCookies(header = '') {
  const result = {};
  String(header)
    .split(';')
    .forEach((part) => {
      const index = part.indexOf('=');
      if (index > -1) {
        const key = part.slice(0, index).trim();
        result[key] = decodeURIComponent(part.slice(index + 1).trim());
      }
    });
  return result;
}

async function userFromRequest(req) {
  if (!db.isAvailable()) {
    return null;
  }
  const cookies = parseCookies(req.headers.cookie);
  const token = cookies[config.cookieName];
  const payload = verifyToken(token);
  if (!payload || !payload.uid) {
    return null;
  }
  const rows = await db.query(
    'SELECT id, username, nickname, email, role, is_active FROM users WHERE id = ? AND is_active = 1',
    [payload.uid]
  );
  return rows.length ? rows[0] : null;
}

async function requireUser(req) {
  const user = await userFromRequest(req);
  if (!user) {
    const error = new Error('请先登录');
    error.status = 401;
    throw error;
  }
  return user;
}

async function requireAdmin(req) {
  const user = await userFromRequest(req);
  if (!user) {
    const error = new Error('请先登录后台');
    error.status = 401;
    throw error;
  }
  if (user.role !== 'admin') {
    const error = new Error('无后台管理权限');
    error.status = 403;
    throw error;
  }
  return user;
}

module.exports = {
  hashPassword,
  verifyPassword,
  signToken,
  verifyToken,
  parseCookies,
  userFromRequest,
  requireUser,
  requireAdmin,
};
