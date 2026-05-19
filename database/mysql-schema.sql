CREATE TABLE contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  phone_normalized VARCHAR(32) NOT NULL UNIQUE,
  phone_display VARCHAR(64) NOT NULL,
  name VARCHAR(160) DEFAULT '',
  type VARCHAR(32) NOT NULL DEFAULT 'customer',
  ignored TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT,
  external_source VARCHAR(64),
  external_contact_id VARCHAR(128),
  last_synced_at DATETIME,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contact_id INT,
  phone_normalized VARCHAR(32) NOT NULL,
  phone_display VARCHAR(64) NOT NULL,
  customer_name VARCHAR(160) DEFAULT '',
  source VARCHAR(32) NOT NULL DEFAULT 'phone',
  product_name VARCHAR(180) DEFAULT '',
  product_price DECIMAL(12,2),
  quoted_amount DECIMAL(12,2),
  delivery_area VARCHAR(180) DEFAULT '',
  delivery_fee DECIMAL(12,2),
  resolution VARCHAR(32) NOT NULL DEFAULT 'quoting',
  notes TEXT,
  call_started_at DATETIME,
  call_ended_at DATETIME,
  call_duration_seconds INT NOT NULL DEFAULT 0,
  followup_1_done TINYINT(1) NOT NULL DEFAULT 0,
  followup_2_done TINYINT(1) NOT NULL DEFAULT 0,
  followup_3_done TINYINT(1) NOT NULL DEFAULT 0,
  next_followup_at DATE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_leads_created_at (created_at),
  INDEX idx_leads_phone (phone_normalized),
  CONSTRAINT fk_leads_contact FOREIGN KEY (contact_id) REFERENCES contacts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE call_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lead_id INT,
  contact_id INT,
  phone_normalized VARCHAR(32) NOT NULL,
  phone_display VARCHAR(64) NOT NULL,
  event_type VARCHAR(32) NOT NULL,
  ignored TINYINT(1) NOT NULL DEFAULT 0,
  ignore_reason VARCHAR(64),
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  payload TEXT,
  INDEX idx_call_events_occurred_at (occurred_at),
  INDEX idx_call_events_phone (phone_normalized),
  CONSTRAINT fk_call_events_lead FOREIGN KEY (lead_id) REFERENCES leads(id),
  CONSTRAINT fk_call_events_contact FOREIGN KEY (contact_id) REFERENCES contacts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
