-- 阿旻同学工具箱导航 MySQL 初始化脚本
-- 用法：mysql -u root -p < install/database.sql
-- 默认数据库 toolbox_nav，可在 includes/config.php 中修改

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS toolbox_nav
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE toolbox_nav;

CREATE TABLE IF NOT EXISTS categories (
    slug VARCHAR(32) NOT NULL PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    icon VARCHAR(16) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sites (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    category VARCHAR(32) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    url VARCHAR(2048) NOT NULL,
    extra_links TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    click_count INT NOT NULL DEFAULT 0,
    like_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_site_category (category),
    KEY idx_site_active_sort (is_active, sort_order),
    CONSTRAINT fk_sites_category
        FOREIGN KEY (category) REFERENCES categories(slug)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(32) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nickname VARCHAR(60) NOT NULL DEFAULT '',
    email VARCHAR(160) NOT NULL DEFAULT '',
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    UNIQUE KEY uk_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feedback (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    type ENUM('broken', 'suggestion', 'other') NOT NULL DEFAULT 'suggestion',
    title VARCHAR(200) NOT NULL DEFAULT '',
    content TEXT NOT NULL,
    site_url VARCHAR(2048) NOT NULL DEFAULT '',
    status ENUM('pending', 'processing', 'resolved', 'closed') NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    handler_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_feedback_status (status),
    KEY idx_feedback_user (user_id),
    KEY idx_feedback_created (created_at),
    CONSTRAINT fk_feedback_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_feedback_handler
        FOREIGN KEY (handler_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_visits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    page_url VARCHAR(500) NOT NULL DEFAULT '',
    category VARCHAR(32) NOT NULL DEFAULT '',
    referer VARCHAR(500) NOT NULL DEFAULT '',
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    ip_address VARCHAR(64) NOT NULL DEFAULT '',
    ip_hash CHAR(64) NOT NULL DEFAULT '',
    visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_visit_created (visited_at),
    KEY idx_visit_category (category, visited_at),
    KEY idx_visit_ip (ip_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_clicks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    site_id VARCHAR(64) NOT NULL,
    target_url VARCHAR(2048) NOT NULL DEFAULT '',
    referer VARCHAR(500) NOT NULL DEFAULT '',
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    ip_address VARCHAR(64) NOT NULL DEFAULT '',
    ip_hash CHAR(64) NOT NULL DEFAULT '',
    clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_click_site (site_id, clicked_at),
    KEY idx_click_created (clicked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
