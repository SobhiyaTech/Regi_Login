-- MySQL bootstrap for GUVI Internship App (Windows localhost)
-- Default DB name matches backend/db/config.php (guvi_app)

CREATE DATABASE IF NOT EXISTS guvi_app
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE guvi_app;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(32) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;