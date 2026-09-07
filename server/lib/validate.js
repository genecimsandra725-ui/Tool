const config = require('../config');

function cleanString(value, max = 255) {
  return String(value || '')
    .replace(/[\u0000-\u001f\u007f]/g, '')
    .trim()
    .slice(0, max);
}

function cleanText(value, max = 2000) {
  return String(value || '')
    .replace(/[\u0000-\u001f\u007f]/g, '')
    .trim()
    .slice(0, max);
}

function validUsername(username) {
  return /^[A-Za-z0-9_]{3,32}$/.test(String(username || ''));
}

function validEmail(email) {
  const value = String(email || '').trim();
  return value === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

function safeUrl(value) {
  if (!value) {
    return false;
  }
  try {
    const parsed = new URL(String(value));
    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
  } catch (error) {
    return false;
  }
}

function validCategory(slug) {
  return config.categories.some((item) => item.slug === slug);
}

function validRole(role) {
  return role === 'admin' || role === 'user';
}

function validationError(message, status = 400) {
  const error = new Error(message);
  error.status = status;
  return error;
}

module.exports = {
  cleanString,
  cleanText,
  validUsername,
  validEmail,
  safeUrl,
  validCategory,
  validRole,
  validationError,
};
