-- ═══════════════════════════════════════════════════════════
--  Zeekers Technology Solutions — MySQL Setup Script
--  Run this ONCE on your Hostinger MySQL database.
--  Database: create it in Hostinger hPanel → Databases first.
-- ═══════════════════════════════════════════════════════════

-- Blog Posts
CREATE TABLE IF NOT EXISTS blog_posts (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(255)  NOT NULL,
  content    TEXT,
  author     VARCHAR(100)  DEFAULT 'Zeekers Team',
  category   VARCHAR(100)  DEFAULT 'rnd',
  image_url  LONGTEXT,
  published  TINYINT(1)    DEFAULT 0,
  created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Job Listings
CREATE TABLE IF NOT EXISTS jobs (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(255) NOT NULL,
  department   VARCHAR(100),
  location     VARCHAR(100) DEFAULT 'Coimbatore, Tamil Nadu',
  type         VARCHAR(50)  DEFAULT 'Full-time',
  experience   VARCHAR(100) DEFAULT '',
  description  TEXT,
  requirements TEXT,
  active       TINYINT(1)   DEFAULT 1,
  is_new       TINYINT(1)   DEFAULT 0,
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- AICTE-IDEA Lab / Industrial Products Catalog
-- (used in the helpdesk "Affected Products" picker; category is one of
--  'electrical', 'mandatory', 'optional' (AICTE lab equipment) or 'industrial')
CREATE TABLE IF NOT EXISTS lab_products (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(255) NOT NULL,
  sub          VARCHAR(255) DEFAULT '',
  category     VARCHAR(50)  NOT NULL DEFAULT 'electrical',
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Job Applications
CREATE TABLE IF NOT EXISTS applications (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  job_id       INT,
  name         VARCHAR(255),
  email        VARCHAR(255),
  phone        VARCHAR(20),
  resume_name  VARCHAR(255) DEFAULT '',
  resume_url   LONGTEXT,
  cover_letter TEXT,
  status       VARCHAR(50)  DEFAULT 'pending',
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL
);

-- Contact Messages
CREATE TABLE IF NOT EXISTS contacts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(255),
  email        VARCHAR(255),
  phone        VARCHAR(20),
  organization VARCHAR(255) DEFAULT '',
  subject      VARCHAR(255),
  message      TEXT,
  status       VARCHAR(50)  DEFAULT 'unread',
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Helpdesk Tickets
CREATE TABLE IF NOT EXISTS tickets (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  ticket_number VARCHAR(20) UNIQUE,
  name          VARCHAR(255),
  email         VARCHAR(255),
  subject       VARCHAR(255),
  message       TEXT,
  status        VARCHAR(50)  DEFAULT 'open',
  priority      VARCHAR(20)  DEFAULT 'medium',
  category      VARCHAR(150) DEFAULT '',
  sub_category  VARCHAR(150) DEFAULT '',
  products      TEXT NULL,
  attachment_name VARCHAR(255) DEFAULT '',
  attachment_data LONGTEXT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Ticket conversation thread (admin replies + user follow-ups)
CREATE TABLE IF NOT EXISTS ticket_replies (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  ticket_number VARCHAR(20) NOT NULL,
  sender_type   VARCHAR(20) NOT NULL,
  sender_name   VARCHAR(255) DEFAULT '',
  message       TEXT NOT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX (ticket_number)
);

-- Helpdesk Users (public portal login)
CREATE TABLE IF NOT EXISTS helpdesk_users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  first_name    VARCHAR(100) NOT NULL,
  last_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(255) UNIQUE NOT NULL,
  password_hash TEXT         NOT NULL,
  org           VARCHAR(255) DEFAULT '',
  avatar        VARCHAR(255) DEFAULT '',
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Brochure Download Leads
CREATE TABLE IF NOT EXISTS brochure_leads (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(255)  NOT NULL,
  mobile     VARCHAR(20)   NOT NULL,
  email      VARCHAR(255)  NOT NULL,
  product    VARCHAR(255)  NOT NULL,
  purpose    TEXT,
  type       VARCHAR(20)   DEFAULT 'product',
  created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- Admin Users
CREATE TABLE IF NOT EXISTS admins (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(100) UNIQUE NOT NULL,
  email         VARCHAR(255) UNIQUE NOT NULL,
  password_hash TEXT         NOT NULL,
  role          VARCHAR(50)  DEFAULT 'admin',
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ─── Seed first admin ────────────────────────────────────────
-- Password: Admin@ZTS2025
-- ⚠ Change this password after first login!
-- Generate new hash: php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);"
INSERT IGNORE INTO admins (username, email, password_hash, role)
VALUES (
  'admin',
  'admin@zeekerstechnology.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- 'password' placeholder; replace!
  'superadmin'
);

-- ─── Sample blog post ────────────────────────────────────────
INSERT IGNORE INTO blog_posts (title, content, author, category, published) VALUES (
  'Welcome to Zeekers Technology Blog',
  '<p>We are thrilled to launch the official Zeekers Technology Solutions blog — your destination for insights on AI-powered welding, IoT innovations, and industrial automation from our R&D lab in Coimbatore.</p><p>Stay tuned for technical deep-dives, case studies, and updates from our AICTE-IDEA Lab programme.</p>',
  'Zeekers Team',
  'rnd',
  1
);

-- ─── Sample job ──────────────────────────────────────────────
INSERT IGNORE INTO jobs (title, department, location, type, description, requirements, active) VALUES (
  'Embedded Systems Engineer',
  'engineering',
  'Coimbatore, Tamil Nadu',
  'Full-time',
  'Lead firmware development for AI-integrated weld monitoring hardware. Own the full embedded stack from bare-metal BSP to RTOS application layer on ARM Cortex-M and A-series platforms.',
  'ARM Cortex-M, FreeRTOS, C/C++, SPI/I2C/UART, 2+ years experience',
  1
);
