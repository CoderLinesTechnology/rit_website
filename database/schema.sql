-- RIT Foundation Ghana - database schema (MySQL 8.0+ / MariaDB 10.4+).
-- Import through phpMyAdmin AFTER selecting the target database. All datetimes are UTC.
-- Then import seed.sql. Future changes go in database/migrations/.

-- ---------------------------------------------------------------------------
-- DESTRUCTIVE RESET (kept commented out on purpose - never run in production):
-- DROP TABLE IF EXISTS email_outbox;
-- DROP TABLE IF EXISTS payment_events;
-- DROP TABLE IF EXISTS donations;
-- DROP TABLE IF EXISTS milestones;
-- DROP TABLE IF EXISTS team_members;
-- DROP TABLE IF EXISTS impact_metrics;
-- DROP TABLE IF EXISTS gallery_images;
-- DROP TABLE IF EXISTS gallery_categories;
-- DROP TABLE IF EXISTS news_articles;
-- DROP TABLE IF EXISTS news_categories;
-- DROP TABLE IF EXISTS contact_enquiries;
-- DROP TABLE IF EXISTS partnership_enquiries;
-- DROP TABLE IF EXISTS volunteer_applications;
-- DROP TABLE IF EXISTS training_applications;
-- DROP TABLE IF EXISTS program_images;
-- DROP TABLE IF EXISTS programs;
-- DROP TABLE IF EXISTS program_categories;
-- DROP TABLE IF EXISTS seo_meta;
-- DROP TABLE IF EXISTS social_links;
-- DROP TABLE IF EXISTS site_settings;
-- DROP TABLE IF EXISTS admin_audit_logs;
-- DROP TABLE IF EXISTS rate_limits;
-- DROP TABLE IF EXISTS admin_password_resets;
-- DROP TABLE IF EXISTS admins;
-- DROP TABLE IF EXISTS schema_migrations;
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- Records which schema migration files have been applied.
CREATE TABLE schema_migrations (
  version VARCHAR(100) NOT NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Administrator accounts for the back office (no default accounts are seeded).
CREATE TABLE admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','content_editor','applications_officer') NOT NULL DEFAULT 'content_editor',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  password_changed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single-use, expiring password reset tokens (only the SHA-256 hash is stored).
CREATE TABLE admin_password_resets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_password_resets_token_hash (token_hash),
  KEY idx_admin_password_resets_admin_id (admin_id),
  CONSTRAINT fk_admin_password_resets_admin_id FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed-window request counters keyed by hashed bucket name.
CREATE TABLE rate_limits (
  bucket CHAR(64) NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  reset_at DATETIME NOT NULL,
  PRIMARY KEY (bucket),
  KEY idx_rate_limits_reset_at (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only trail of administrator actions.
CREATE TABLE admin_audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(60) NULL,
  entity_id INT UNSIGNED NULL,
  summary VARCHAR(500) NOT NULL DEFAULT '',
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_audit_logs_created_at (created_at),
  KEY idx_admin_audit_logs_admin_id_created_at (admin_id, created_at),
  KEY idx_admin_audit_logs_entity_type_entity_id (entity_type, entity_id),
  CONSTRAINT fk_admin_audit_logs_admin_id FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Editable key/value site content and configuration.
CREATE TABLE site_settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value MEDIUMTEXT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  CONSTRAINT fk_site_settings_updated_by FOREIGN KEY (updated_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Social media profile links shown in the header/footer.
CREATE TABLE social_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  platform VARCHAR(30) NOT NULL,
  label VARCHAR(60) NOT NULL,
  url VARCHAR(500) NOT NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_social_links_platform (platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-page SEO title, description and share image.
CREATE TABLE seo_meta (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  page_key VARCHAR(60) NOT NULL,
  meta_title VARCHAR(150) NULL,
  meta_description VARCHAR(300) NULL,
  og_image_path VARCHAR(255) NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seo_meta_page_key (page_key),
  CONSTRAINT fk_seo_meta_updated_by FOREIGN KEY (updated_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories that group training programs.
CREATE TABLE program_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  description TEXT NULL,
  icon VARCHAR(40) NULL,
  image_path VARCHAR(255) NULL,
  thumb_path VARCHAR(255) NULL,
  image_alt VARCHAR(255) NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_program_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training programs offered by the Foundation.
CREATE TABLE programs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NULL,
  icon VARCHAR(40) NULL,
  title VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  summary VARCHAR(500) NOT NULL,
  description MEDIUMTEXT NOT NULL,
  featured_image_path VARCHAR(255) NULL,
  featured_thumb_path VARCHAR(255) NULL,
  featured_image_alt VARCHAR(255) NULL,
  target_audience VARCHAR(500) NULL,
  eligibility TEXT NULL,
  duration VARCHAR(120) NULL,
  location VARCHAR(160) NULL,
  application_opens_on DATE NULL,
  application_closes_on DATE NULL,
  accepts_applications TINYINT(1) NOT NULL DEFAULT 0,
  application_instructions TEXT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  seo_title VARCHAR(150) NULL,
  seo_description VARCHAR(300) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_programs_slug (slug),
  KEY idx_programs_status_sort_order (status, sort_order),
  KEY idx_programs_status_is_featured_sort_order (status, is_featured, sort_order),
  KEY idx_programs_category_id (category_id),
  CONSTRAINT fk_programs_category_id FOREIGN KEY (category_id) REFERENCES program_categories (id) ON DELETE SET NULL,
  CONSTRAINT fk_programs_created_by FOREIGN KEY (created_by) REFERENCES admins (id) ON DELETE SET NULL,
  CONSTRAINT fk_programs_updated_by FOREIGN KEY (updated_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Additional gallery images attached to a program.
CREATE TABLE program_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  program_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  thumb_path VARCHAR(255) NOT NULL,
  width SMALLINT UNSIGNED NOT NULL,
  height SMALLINT UNSIGNED NOT NULL,
  alt_text VARCHAR(255) NOT NULL,
  caption VARCHAR(255) NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_program_images_program_id_sort_order (program_id, sort_order),
  CONSTRAINT fk_program_images_program_id FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Applications to training programs. Rows hold personal data (Ghana Data Protection Act 2012): restrict access, apply retention and erasure.
CREATE TABLE training_applications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference VARCHAR(24) NOT NULL,
  program_id INT UNSIGNED NULL,
  program_title VARCHAR(160) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  age_range VARCHAR(20) NOT NULL,
  is_adult TINYINT(1) NOT NULL,
  guardian_name VARCHAR(150) NULL,
  guardian_phone VARCHAR(30) NULL,
  guardian_consent TINYINT(1) NOT NULL DEFAULT 0,
  location VARCHAR(150) NOT NULL,
  background TEXT NULL,
  reason TEXT NOT NULL,
  availability VARCHAR(255) NULL,
  attachment_path VARCHAR(255) NULL,
  attachment_name VARCHAR(255) NULL,
  attachment_size INT UNSIGNED NULL,
  attachment_mime VARCHAR(60) NULL,
  privacy_accepted_at DATETIME NOT NULL,
  status ENUM('new','under_review','shortlisted','accepted','rejected','withdrawn') NOT NULL DEFAULT 'new',
  status_changed_at DATETIME NULL,
  status_changed_by INT UNSIGNED NULL,
  admin_notes TEXT NULL,
  idempotency_key CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_applications_reference (reference),
  UNIQUE KEY uq_training_applications_idempotency_key (idempotency_key),
  KEY idx_training_applications_status_created_at (status, created_at),
  KEY idx_training_applications_program_id_created_at (program_id, created_at),
  KEY idx_training_applications_email (email),
  CONSTRAINT fk_training_applications_program_id FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE SET NULL,
  CONSTRAINT fk_training_applications_status_changed_by FOREIGN KEY (status_changed_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volunteer sign-ups. Rows hold personal data (Ghana Data Protection Act 2012): restrict access, apply retention and erasure.
CREATE TABLE volunteer_applications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference VARCHAR(24) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NULL,
  location VARCHAR(150) NULL,
  interest_area VARCHAR(60) NOT NULL,
  skills TEXT NULL,
  availability VARCHAR(255) NULL,
  message TEXT NULL,
  privacy_accepted_at DATETIME NOT NULL,
  status ENUM('new','contacted','active','declined','closed') NOT NULL DEFAULT 'new',
  status_changed_at DATETIME NULL,
  status_changed_by INT UNSIGNED NULL,
  admin_notes TEXT NULL,
  idempotency_key CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_volunteer_applications_reference (reference),
  UNIQUE KEY uq_volunteer_applications_idempotency_key (idempotency_key),
  KEY idx_volunteer_applications_status_created_at (status, created_at),
  KEY idx_volunteer_applications_email (email),
  CONSTRAINT fk_volunteer_applications_status_changed_by FOREIGN KEY (status_changed_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partnership enquiries from organisations. Rows hold personal data (Ghana Data Protection Act 2012): restrict access, apply retention and erasure.
CREATE TABLE partnership_enquiries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference VARCHAR(24) NOT NULL,
  organization_name VARCHAR(190) NOT NULL,
  contact_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NULL,
  website VARCHAR(255) NULL,
  partnership_type VARCHAR(60) NOT NULL,
  message TEXT NOT NULL,
  privacy_accepted_at DATETIME NOT NULL,
  status ENUM('new','in_discussion','agreed','declined','closed') NOT NULL DEFAULT 'new',
  status_changed_at DATETIME NULL,
  status_changed_by INT UNSIGNED NULL,
  admin_notes TEXT NULL,
  idempotency_key CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_partnership_enquiries_reference (reference),
  UNIQUE KEY uq_partnership_enquiries_idempotency_key (idempotency_key),
  KEY idx_partnership_enquiries_status_created_at (status, created_at),
  CONSTRAINT fk_partnership_enquiries_status_changed_by FOREIGN KEY (status_changed_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- General contact form enquiries. Rows hold personal data (Ghana Data Protection Act 2012): restrict access, apply retention and erasure.
CREATE TABLE contact_enquiries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference VARCHAR(24) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NULL,
  subject VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  privacy_accepted_at DATETIME NOT NULL,
  status ENUM('new','in_progress','replied','closed') NOT NULL DEFAULT 'new',
  status_changed_at DATETIME NULL,
  status_changed_by INT UNSIGNED NULL,
  admin_notes TEXT NULL,
  idempotency_key CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_contact_enquiries_reference (reference),
  UNIQUE KEY uq_contact_enquiries_idempotency_key (idempotency_key),
  KEY idx_contact_enquiries_status_created_at (status, created_at),
  CONSTRAINT fk_contact_enquiries_status_changed_by FOREIGN KEY (status_changed_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories that group news articles.
CREATE TABLE news_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  description TEXT NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_news_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- News, announcements and stories.
CREATE TABLE news_articles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NULL,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL,
  excerpt VARCHAR(500) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  featured_image_path VARCHAR(255) NULL,
  featured_thumb_path VARCHAR(255) NULL,
  featured_image_alt VARCHAR(255) NULL,
  author_name VARCHAR(120) NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  consent_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  seo_title VARCHAR(150) NULL,
  seo_description VARCHAR(300) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_news_articles_slug (slug),
  KEY idx_news_articles_status_published_at (status, published_at),
  KEY idx_news_articles_category_id_status_published_at (category_id, status, published_at),
  CONSTRAINT fk_news_articles_category_id FOREIGN KEY (category_id) REFERENCES news_categories (id) ON DELETE SET NULL,
  CONSTRAINT fk_news_articles_created_by FOREIGN KEY (created_by) REFERENCES admins (id) ON DELETE SET NULL,
  CONSTRAINT fk_news_articles_updated_by FOREIGN KEY (updated_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photo albums for the public gallery.
CREATE TABLE gallery_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(170) NOT NULL,
  description TEXT NULL,
  event_date DATE NULL,
  external_url VARCHAR(500) NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gallery_categories_slug (slug),
  KEY idx_gallery_categories_is_published_sort_order (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual gallery photographs (a category with images cannot be deleted).
CREATE TABLE gallery_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NULL,
  image_path VARCHAR(255) NOT NULL,
  thumb_path VARCHAR(255) NOT NULL,
  width SMALLINT UNSIGNED NOT NULL,
  height SMALLINT UNSIGNED NOT NULL,
  alt_text VARCHAR(255) NOT NULL,
  caption VARCHAR(500) NULL,
  taken_on DATE NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  consent_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gallery_images_category_id_is_published_sort_order_id (category_id, is_published, sort_order, id),
  KEY idx_gallery_images_is_featured_is_published (is_featured, is_published),
  CONSTRAINT fk_gallery_images_category_id FOREIGN KEY (category_id) REFERENCES gallery_categories (id) ON DELETE RESTRICT,
  CONSTRAINT fk_gallery_images_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Headline impact figures (only verified figures should be published).
CREATE TABLE impact_metrics (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  label VARCHAR(150) NOT NULL,
  value VARCHAR(40) NOT NULL,
  is_approximate TINYINT(1) NOT NULL DEFAULT 0,
  description TEXT NULL,
  icon VARCHAR(40) NULL,
  reporting_period VARCHAR(100) NULL,
  image_path VARCHAR(255) NULL,
  thumb_path VARCHAR(255) NULL,
  image_alt VARCHAR(255) NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  show_on_homepage TINYINT(1) NOT NULL DEFAULT 0,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_impact_metrics_status_sort_order (status, sort_order),
  CONSTRAINT fk_impact_metrics_created_by FOREIGN KEY (created_by) REFERENCES admins (id) ON DELETE SET NULL,
  CONSTRAINT fk_impact_metrics_updated_by FOREIGN KEY (updated_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team and leadership profiles (published only with consent).
CREATE TABLE team_members (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(150) NOT NULL,
  role_title VARCHAR(150) NOT NULL,
  bio TEXT NULL,
  photo_path VARCHAR(255) NULL,
  photo_thumb_path VARCHAR(255) NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  consent_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_team_members_is_published_sort_order (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- History timeline entries (publish only once verified).
CREATE TABLE milestones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  period_label VARCHAR(40) NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_milestones_is_published_sort_order (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Donation records; no card data is ever stored. Rows hold personal data (Ghana Data Protection Act 2012): restrict access, apply retention and erasure.
CREATE TABLE donations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference VARCHAR(30) NOT NULL,
  provider VARCHAR(40) NOT NULL,
  provider_reference VARCHAR(120) NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'GHS',
  donor_name VARCHAR(150) NULL,
  donor_email VARCHAR(190) NULL,
  is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('initiated','pending','successful','failed','abandoned','refunded') NOT NULL DEFAULT 'initiated',
  provider_status VARCHAR(60) NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_donations_reference (reference),
  UNIQUE KEY uq_donations_provider_provider_reference (provider, provider_reference),
  KEY idx_donations_status_created_at (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment provider webhook events (de-duplicated by provider and event id).
CREATE TABLE payment_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  donation_id BIGINT UNSIGNED NULL,
  provider VARCHAR(40) NOT NULL,
  event_id VARCHAR(190) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  signature_valid TINYINT(1) NOT NULL,
  payload_sha256 CHAR(64) NOT NULL,
  processed_at DATETIME NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_events_provider_event_id (provider, event_id),
  CONSTRAINT fk_payment_events_donation_id FOREIGN KEY (donation_id) REFERENCES donations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Queue of outgoing emails and their hand-off status.
CREATE TABLE email_outbox (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  purpose VARCHAR(60) NOT NULL,
  related_type VARCHAR(60) NULL,
  related_id BIGINT UNSIGNED NULL,
  recipient VARCHAR(190) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_text MEDIUMTEXT NULL,
  status ENUM('queued','handed_off','failed','skipped') NOT NULL DEFAULT 'queued',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(500) NULL,
  last_attempt_at DATETIME NULL,
  handed_off_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_outbox_status_attempts_id (status, attempts, id),
  KEY idx_email_outbox_related_type_related_id (related_type, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('0001_initial');

SET FOREIGN_KEY_CHECKS=1;
