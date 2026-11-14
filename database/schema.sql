-- =====================================================================
--  PT Candaka Tiga Utama — Company Profile & Sistem Internal
--  Skema database MySQL / MariaDB
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Pengguna sistem internal (admin & investor)
CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(120) NOT NULL,
  email           VARCHAR(160) NOT NULL UNIQUE,
  phone           VARCHAR(40)  NULL,
  password_hash   VARCHAR(255) NOT NULL,
  role            ENUM('admin','investor') NOT NULL DEFAULT 'investor',
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at   DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dokumen legal perusahaan (akta, NIB, NPWP, SBU, dll.)
CREATE TABLE IF NOT EXISTS legal_documents (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title               VARCHAR(200) NOT NULL,
  category            VARCHAR(60)  NOT NULL,
  doc_number          VARCHAR(120) NULL,
  issuer              VARCHAR(160) NULL,
  issued_date         DATE NULL,
  expiry_date         DATE NULL,
  notes               TEXT NULL,
  visible_to_investor TINYINT(1) NOT NULL DEFAULT 0,
  file_path           VARCHAR(255) NOT NULL,
  original_name       VARCHAR(255) NOT NULL,
  mime_type           VARCHAR(100) NOT NULL,
  file_size           INT UNSIGNED NOT NULL,
  uploaded_by         INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_legal_category (category),
  INDEX idx_legal_expiry (expiry_date),
  CONSTRAINT fk_legal_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proyek yang dijalankan per divisi
CREATE TABLE IF NOT EXISTS projects (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code            VARCHAR(40)  NULL,
  name            VARCHAR(200) NOT NULL,
  division        ENUM('konstruksi','pengadaan','it') NOT NULL,
  client          VARCHAR(200) NULL,
  location        VARCHAR(200) NULL,
  contract_number VARCHAR(120) NULL,
  contract_value  DECIMAL(18,2) NULL,
  start_date      DATE NULL,
  end_date        DATE NULL,
  status          ENUM('persiapan','berjalan','selesai','tertunda','batal') NOT NULL DEFAULT 'berjalan',
  progress        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  summary         VARCHAR(300) NULL,
  description     TEXT NULL,
  is_public       TINYINT(1) NOT NULL DEFAULT 0,
  cover_image     VARCHAR(255) NULL,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_proj_division (division),
  INDEX idx_proj_status (status),
  INDEX idx_proj_public (is_public),
  CONSTRAINT fk_proj_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dokumen milik proyek (kontrak, SPK, BAST, invoice, foto, dll.)
CREATE TABLE IF NOT EXISTS project_documents (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id          INT UNSIGNED NOT NULL,
  title               VARCHAR(200) NOT NULL,
  doc_type            VARCHAR(60)  NOT NULL,
  doc_date            DATE NULL,
  notes               VARCHAR(300) NULL,
  visible_to_investor TINYINT(1) NOT NULL DEFAULT 1,
  file_path           VARCHAR(255) NOT NULL,
  original_name       VARCHAR(255) NOT NULL,
  mime_type           VARCHAR(100) NOT NULL,
  file_size           INT UNSIGNED NOT NULL,
  uploaded_by         INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pdoc_project (project_id),
  CONSTRAINT fk_pdoc_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pdoc_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hak akses investor ke proyek tertentu
CREATE TABLE IF NOT EXISTS project_investors (
  project_id  INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (project_id, user_id),
  CONSTRAINT fk_pi_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pesan dari formulir kontak website
CREATE TABLE IF NOT EXISTS contact_messages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(160) NOT NULL,
  phone       VARCHAR(40)  NULL,
  company     VARCHAR(160) NULL,
  division    VARCHAR(20)  NULL,
  message     TEXT NOT NULL,
  ip_address  VARCHAR(45)  NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_msg_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catatan aktivitas (unggah/hapus dokumen, login, dll.)
CREATE TABLE IF NOT EXISTS activity_log (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(60)  NOT NULL,
  detail      VARCHAR(300) NULL,
  ip_address  VARCHAR(45)  NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_log_created (created_at),
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Perhitungan margin & net profit per proyek (satu perhitungan aktif per proyek)
CREATE TABLE IF NOT EXISTS project_calculations (
  project_id      INT UNSIGNED PRIMARY KEY,
  tax_year        SMALLINT UNSIGNED NOT NULL,
  inputs          TEXT NOT NULL,
  net_sales       DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_cost      DECIMAL(18,2) NOT NULL DEFAULT 0,
  gross_profit    DECIMAL(18,2) NOT NULL DEFAULT 0,
  ebt             DECIMAL(18,2) NOT NULL DEFAULT 0,
  pph             DECIMAL(18,2) NOT NULL DEFAULT 0,
  net_profit      DECIMAL(18,2) NOT NULL DEFAULT 0,
  net_margin      DECIMAL(9,4)  NULL,
  withholding     DECIMAL(18,2) NOT NULL DEFAULT 0,
  ppn_out         DECIMAL(18,2) NOT NULL DEFAULT 0,
  ppn_in          DECIMAL(18,2) NOT NULL DEFAULT 0,
  ppn_by_client   TINYINT(1) NOT NULL DEFAULT 0,
  is_final        TINYINT(1) NOT NULL DEFAULT 0,
  updated_by      INT UNSIGNED NULL,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_calc_year (tax_year),
  CONSTRAINT fk_calc_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_calc_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data pajak tahunan di luar proyek (untuk kalkulator PPh Badan tahunan)
CREATE TABLE IF NOT EXISTS annual_tax_inputs (
  tax_year        SMALLINT UNSIGNED PRIMARY KEY,
  inputs          TEXT NOT NULL,
  updated_by      INT UNSIGNED NULL,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_annual_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
