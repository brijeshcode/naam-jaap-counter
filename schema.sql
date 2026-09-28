CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  session_token CHAR(64) NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS mantras (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL
);
INSERT INTO mantras (id, name) SELECT 1, 'Default'
  WHERE NOT EXISTS (SELECT 1 FROM mantras WHERE id = 1);

CREATE TABLE IF NOT EXISTS auth_attempts (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  rkey       VARCHAR(64) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rkey_time (rkey, created_at)
);

CREATE TABLE IF NOT EXISTS daily_counts (
  user_id   INT NOT NULL,
  mantra_id INT NOT NULL DEFAULT 1,
  day       DATE NOT NULL,
  count     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, mantra_id, day),
  FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (mantra_id) REFERENCES mantras(id)
);
