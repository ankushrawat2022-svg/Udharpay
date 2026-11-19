-- ============================================================================
-- UdharPay — Digital Credit & Udhar Management Platform (India)
-- Complete MariaDB / MySQL 10.6+ / 8.0+ Production-Oriented Schema
-- phpMyAdmin Import Ready (InnoDB, utf8mb4_unicode_ci)
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+05:30";
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `udharpay_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `udharpay_db`;

-- ----------------------------------------------------------------------------
-- 1. RBAC: roles, permissions, role_permissions
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(40) NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(80) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_code` (`code`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` TINYINT UNSIGNED NOT NULL,
  `permission_id` SMALLINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Users & Sessions
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` TINYINT UNSIGNED NOT NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(15) NOT NULL,
  `email` VARCHAR(160) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_demo` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive','suspended','pending_verification') NOT NULL DEFAULT 'active',
  `otp_hash` VARCHAR(255) DEFAULT NULL,
  `otp_expires_at` DATETIME DEFAULT NULL,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_phone` (`phone`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_status` (`role_id`, `status`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `csrf_token` CHAR(64) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sessions_token_hash` (`token_hash`),
  KEY `idx_sessions_user_exp` (`user_id`, `expires_at`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Merchants, Shops, Shop Staff & Permanent Shop QR Codes
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `merchants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `business_legal_name` VARCHAR(160) NOT NULL,
  `gstin` VARCHAR(20) DEFAULT NULL,
  `pan_masked` VARCHAR(20) DEFAULT NULL,
  `verification_status` ENUM('pending','verified','rejected','suspended') NOT NULL DEFAULT 'pending',
  `verified_by` BIGINT UNSIGNED DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `subscription_plan` VARCHAR(40) NOT NULL DEFAULT 'starter',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_merchants_user` (`user_id`),
  KEY `idx_merchants_verification` (`verification_status`),
  CONSTRAINT `fk_merchants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_merchants_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shops` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `merchant_id` BIGINT UNSIGNED NOT NULL,
  `shop_code` VARCHAR(32) NOT NULL,
  `shop_name` VARCHAR(160) NOT NULL,
  `category` VARCHAR(80) NOT NULL DEFAULT 'Kirana & General',
  `address` VARCHAR(300) NOT NULL,
  `city` VARCHAR(80) NOT NULL,
  `state` VARCHAR(80) NOT NULL,
  `pincode` VARCHAR(10) NOT NULL,
  `phone` VARCHAR(15) NOT NULL,
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `default_credit_limit` DECIMAL(12,2) NOT NULL DEFAULT 5000.00,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shops_code` (`shop_code`),
  KEY `idx_shops_merchant` (`merchant_id`, `status`),
  CONSTRAINT `fk_shops_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_staff` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `staff_role` ENUM('manager','cashier','collector','verifier') NOT NULL DEFAULT 'cashier',
  `can_create_bill` TINYINT(1) NOT NULL DEFAULT 1,
  `can_record_payment` TINYINT(1) NOT NULL DEFAULT 1,
  `can_change_limit` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_staff_pair` (`shop_id`, `user_id`),
  KEY `idx_shop_staff_user` (`user_id`),
  CONSTRAINT `fk_shop_staff_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shop_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_qr_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` BIGINT UNSIGNED NOT NULL,
  `qr_token` VARCHAR(64) NOT NULL,
  `qr_payload_url` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `scan_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_qr_shop` (`shop_id`),
  UNIQUE KEY `uq_shop_qr_token` (`qr_token`),
  CONSTRAINT `fk_shop_qr_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Customers, KYC & Explicit Consent Records
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `customer_code` VARCHAR(32) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(80) DEFAULT NULL,
  `pincode` VARCHAR(10) DEFAULT NULL,
  `kyc_status` ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_user` (`user_id`),
  UNIQUE KEY `uq_customers_code` (`customer_code`),
  KEY `idx_customers_kyc` (`kyc_status`),
  CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_kyc` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `document_type` ENUM('aadhaar_masked','pan','voter_id','driving_license') NOT NULL,
  `document_number_masked` VARCHAR(32) NOT NULL,
  `document_file_path` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` BIGINT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `rejection_reason` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kyc_customer_status` (`customer_id`, `status`),
  CONSTRAINT `fk_kyc_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kyc_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `consents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `shop_id` BIGINT UNSIGNED DEFAULT NULL,
  `bill_id` BIGINT UNSIGNED DEFAULT NULL,
  `consent_type` ENUM('shop_connection','credit_bill_approval','mandate_authorization','mandate_revocation','terms_privacy') NOT NULL,
  `consent_text_version` VARCHAR(32) NOT NULL DEFAULT 'v1.0',
  `decision` ENUM('accepted','rejected','revoked') NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `device_info` VARCHAR(255) DEFAULT NULL,
  `consented_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_consents_customer` (`customer_id`, `consent_type`),
  KEY `idx_consents_shop` (`shop_id`),
  CONSTRAINT `fk_consents_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_consents_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Tenant-Isolated Credit Limits & Credit Accounts
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `credit_limits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `credit_limit` DECIMAL(12,2) NOT NULL DEFAULT 5000.00,
  `suggested_limit` DECIMAL(12,2) DEFAULT NULL,
  `is_credit_blocked` TINYINT(1) NOT NULL DEFAULT 0,
  `block_reason` VARCHAR(255) DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_credit_limits_shop_customer` (`shop_id`, `customer_id`),
  KEY `idx_credit_limits_customer` (`customer_id`),
  CONSTRAINT `fk_cl_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cl_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cl_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `credit_accounts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `total_credited` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total_repaid` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `outstanding_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active','blocked','settled') NOT NULL DEFAULT 'active',
  `last_activity_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_credit_accounts_shop_customer` (`shop_id`, `customer_id`),
  KEY `idx_ca_shop_outstanding` (`shop_id`, `outstanding_balance`),
  KEY `idx_ca_customer` (`customer_id`),
  CONSTRAINT `fk_ca_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ca_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. Credit Bills & Line Items (Explicit Customer Approval Required)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `credit_bills` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bill_number` VARCHAR(40) NOT NULL,
  `bill_qr_token` VARCHAR(64) NOT NULL,
  `shop_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `created_by_user_id` BIGINT UNSIGNED NOT NULL,
  `subtotal_amount` DECIMAL(12,2) NOT NULL,
  `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL,
  `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `pending_amount` DECIMAL(12,2) NOT NULL,
  `installment_count` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `due_date` DATE NOT NULL,
  `notes` VARCHAR(500) DEFAULT NULL,
  `status` ENUM('pending_customer_approval','rejected','active','partially_paid','paid','overdue','disputed') NOT NULL DEFAULT 'pending_customer_approval',
  `customer_approved_at` DATETIME DEFAULT NULL,
  `customer_rejected_at` DATETIME DEFAULT NULL,
  `rejection_reason` VARCHAR(255) DEFAULT NULL,
  `idempotency_key` VARCHAR(80) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bills_number` (`bill_number`),
  UNIQUE KEY `uq_bills_qr_token` (`bill_qr_token`),
  UNIQUE KEY `uq_bills_idempotency` (`idempotency_key`),
  KEY `idx_bills_shop_status` (`shop_id`, `status`),
  KEY `idx_bills_customer_status` (`customer_id`, `status`),
  KEY `idx_bills_due_date` (`due_date`),
  CONSTRAINT `fk_bills_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bills_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bills_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `credit_bill_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bill_id` BIGINT UNSIGNED NOT NULL,
  `item_name` VARCHAR(160) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit` VARCHAR(24) NOT NULL DEFAULT 'pcs',
  `unit_price` DECIMAL(12,2) NOT NULL,
  `line_total` DECIMAL(12,2) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bill_items_bill` (`bill_id`),
  CONSTRAINT `fk_bill_items_bill` FOREIGN KEY (`bill_id`) REFERENCES `credit_bills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. Append-Only Financial Ledger Engine
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ledger_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_number` VARCHAR(64) NOT NULL,
  `credit_account_id` BIGINT UNSIGNED NOT NULL,
  `shop_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `bill_id` BIGINT UNSIGNED DEFAULT NULL,
  `payment_id` BIGINT UNSIGNED DEFAULT NULL,
  `entry_type` ENUM('credit_debit','repayment_credit','refund_adjustment','reversal_entry') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `running_balance` DECIMAL(14,2) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `created_by_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ledger_ref` (`reference_number`),
  KEY `idx_ledger_account_created` (`credit_account_id`, `created_at`),
  KEY `idx_ledger_shop_customer` (`shop_id`, `customer_id`),
  KEY `idx_ledger_bill` (`bill_id`),
  CONSTRAINT `fk_ledger_account` FOREIGN KEY (`credit_account_id`) REFERENCES `credit_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ledger_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ledger_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ledger_bill` FOREIGN KEY (`bill_id`) REFERENCES `credit_bills` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ledger_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. Payments, Repayments, Payment Attempts & Webhooks
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_ref` VARCHAR(64) NOT NULL,
  `idempotency_key` VARCHAR(80) NOT NULL,
  `shop_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `bill_id` BIGINT UNSIGNED DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_mode` ENUM('demo_upi','demo_cash','upi_intent','upi_autopay','payment_gateway') NOT NULL DEFAULT 'demo_upi',
  `payment_type` ENUM('full','partial','installment') NOT NULL DEFAULT 'full',
  `is_demo` TINYINT(1) NOT NULL DEFAULT 1,
  `partner_name` VARCHAR(60) NOT NULL DEFAULT 'DEMO_GATEWAY',
  `partner_transaction_id` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('initiated','pending_partner_confirmation','succeeded','failed','refunded') NOT NULL DEFAULT 'initiated',
  `failure_reason` VARCHAR(255) DEFAULT NULL,
  `receipt_number` VARCHAR(48) DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payments_ref` (`payment_ref`),
  UNIQUE KEY `uq_payments_idempotency` (`idempotency_key`),
  UNIQUE KEY `uq_payments_receipt` (`receipt_number`),
  KEY `idx_payments_shop_status` (`shop_id`, `status`),
  KEY `idx_payments_customer` (`customer_id`, `created_at`),
  KEY `idx_payments_bill` (`bill_id`),
  CONSTRAINT `fk_payments_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_payments_bill` FOREIGN KEY (`bill_id`) REFERENCES `credit_bills` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `repayments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` BIGINT UNSIGNED NOT NULL,
  `bill_id` BIGINT UNSIGNED NOT NULL,
  `credit_account_id` BIGINT UNSIGNED NOT NULL,
  `allocated_amount` DECIMAL(12,2) NOT NULL,
  `installment_number` TINYINT UNSIGNED DEFAULT NULL,
  `reconciled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_repayments_bill` (`bill_id`),
  KEY `idx_repayments_payment` (`payment_id`),
  CONSTRAINT `fk_repayments_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_repayments_bill` FOREIGN KEY (`bill_id`) REFERENCES `credit_bills` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_repayments_account` FOREIGN KEY (`credit_account_id`) REFERENCES `credit_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` BIGINT UNSIGNED NOT NULL,
  `attempt_number` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `partner_request_id` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('created','redirected','webhook_verified','failed','expired') NOT NULL DEFAULT 'created',
  `error_code` VARCHAR(60) DEFAULT NULL,
  `error_message` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pa_payment` (`payment_id`),
  CONSTRAINT `fk_pa_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. RBI/NPCI Compliant UPI AutoPay Mandates & Mandate Events
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_mandates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mandate_ref` VARCHAR(64) NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `shop_id` BIGINT UNSIGNED NOT NULL,
  `partner_name` VARCHAR(60) NOT NULL DEFAULT 'NOT_CONFIGURED_DEMO',
  `max_amount` DECIMAL(12,2) NOT NULL,
  `frequency` ENUM('as_presented','monthly','weekly') NOT NULL DEFAULT 'monthly',
  `status` ENUM('pending_customer_approval','active','pause_requested','cancellation_requested','cancelled','revoked','expired') NOT NULL DEFAULT 'pending_customer_approval',
  `valid_from` DATE NOT NULL,
  `valid_until` DATE NOT NULL,
  `customer_vpa_masked` VARCHAR(80) DEFAULT NULL,
  `cancellation_reason` VARCHAR(255) DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mandates_ref` (`mandate_ref`),
  KEY `idx_mandates_customer` (`customer_id`, `status`),
  KEY `idx_mandates_shop` (`shop_id`, `status`),
  CONSTRAINT `fk_mandates_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_mandates_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mandate_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mandate_id` BIGINT UNSIGNED NOT NULL,
  `event_type` ENUM('created','approved','pre_debit_notified','debit_attempted','cancellation_requested','cancelled','revoked','merchant_notified') NOT NULL,
  `actor_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mandate_events_mandate` (`mandate_id`, `created_at`),
  CONSTRAINT `fk_me_mandate` FOREIGN KEY (`mandate_id`) REFERENCES `payment_mandates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_me_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. Risk Assessments & Credit Decisions (Manual Merchant Review, No Fabricated Lending)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `risk_assessments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `on_time_repayments` INT UNSIGNED NOT NULL DEFAULT 0,
  `late_repayments` INT UNSIGNED NOT NULL DEFAULT 0,
  `overdue_bills_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `risk_band` ENUM('low','moderate','elevated','review_required') NOT NULL DEFAULT 'review_required',
  `suggested_limit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `basis_summary` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_risk_shop_customer` (`shop_id`, `customer_id`),
  CONSTRAINT `fk_risk_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_risk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `credit_decisions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `decided_by_user_id` BIGINT UNSIGNED NOT NULL,
  `previous_limit` DECIMAL(12,2) NOT NULL,
  `new_limit` DECIMAL(12,2) NOT NULL,
  `decision` ENUM('approved','reduced','blocked','unblocked') NOT NULL,
  `rationale` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cd_shop_customer` (`shop_id`, `customer_id`),
  CONSTRAINT `fk_cd_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cd_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cd_user` FOREIGN KEY (`decided_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. Complaints, Disputes & Support Tickets
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `complaints` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `complaint_ref` VARCHAR(40) NOT NULL,
  `submitted_by_user_id` BIGINT UNSIGNED NOT NULL,
  `shop_id` BIGINT UNSIGNED DEFAULT NULL,
  `bill_id` BIGINT UNSIGNED DEFAULT NULL,
  `payment_id` BIGINT UNSIGNED DEFAULT NULL,
  `category` ENUM('incorrect_bill','duplicate_payment','mandate_issue','merchant_conduct','customer_default','other') NOT NULL,
  `subject` VARCHAR(160) NOT NULL,
  `description` TEXT NOT NULL,
  `status` ENUM('open','in_review','escalated','resolved','closed') NOT NULL DEFAULT 'open',
  `resolution_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_complaints_ref` (`complaint_ref`),
  KEY `idx_complaints_status` (`status`, `category`),
  CONSTRAINT `fk_complaints_user` FOREIGN KEY (`submitted_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_complaints_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_complaints_bill` FOREIGN KEY (`bill_id`) REFERENCES `credit_bills` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_complaints_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `disputes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dispute_ref` VARCHAR(40) NOT NULL,
  `bill_id` BIGINT UNSIGNED NOT NULL,
  `raised_by_user_id` BIGINT UNSIGNED NOT NULL,
  `disputed_amount` DECIMAL(12,2) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `status` ENUM('open','investigating','resolved_merchant','resolved_customer','closed') NOT NULL DEFAULT 'open',
  `assigned_to_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_disputes_ref` (`dispute_ref`),
  KEY `idx_disputes_status` (`status`),
  CONSTRAINT `fk_disputes_bill` FOREIGN KEY (`bill_id`) REFERENCES `credit_bills` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_disputes_raiser` FOREIGN KEY (`raised_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_disputes_assignee` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_number` VARCHAR(40) NOT NULL,
  `complaint_id` BIGINT UNSIGNED DEFAULT NULL,
  `requester_user_id` BIGINT UNSIGNED NOT NULL,
  `assigned_agent_id` BIGINT UNSIGNED DEFAULT NULL,
  `priority` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `category` ENUM('customer','merchant','payment','duplicate_payment','incorrect_bill','mandate') NOT NULL,
  `subject` VARCHAR(160) NOT NULL,
  `last_reply` TEXT DEFAULT NULL,
  `status` ENUM('open','assigned','awaiting_user','escalated','resolved','closed') NOT NULL DEFAULT 'open',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tickets_number` (`ticket_number`),
  KEY `idx_tickets_status_priority` (`status`, `priority`),
  KEY `idx_tickets_agent` (`assigned_agent_id`),
  CONSTRAINT `fk_tickets_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tickets_requester` FOREIGN KEY (`requester_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tickets_agent` FOREIGN KEY (`assigned_agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. Subscriptions, Invoices, Partner Accounts, Webhooks
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `merchant_id` BIGINT UNSIGNED NOT NULL,
  `plan_code` ENUM('starter','growth','enterprise') NOT NULL DEFAULT 'starter',
  `monthly_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active','past_due','cancelled','trial') NOT NULL DEFAULT 'active',
  `current_period_start` DATE NOT NULL,
  `current_period_end` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subscriptions_merchant` (`merchant_id`, `status`),
  CONSTRAINT `fk_subscriptions_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(48) NOT NULL,
  `merchant_id` BIGINT UNSIGNED NOT NULL,
  `subscription_id` BIGINT UNSIGNED DEFAULT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL,
  `gst_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL,
  `status` ENUM('draft','issued','paid','void','refunded') NOT NULL DEFAULT 'issued',
  `due_date` DATE NOT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoices_number` (`invoice_number`),
  KEY `idx_invoices_merchant` (`merchant_id`, `status`),
  CONSTRAINT `fk_invoices_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_invoices_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `partner_accounts` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `partner_code` VARCHAR(50) NOT NULL,
  `partner_name` VARCHAR(120) NOT NULL,
  `capability` ENUM('upi_payment','payment_gateway','upi_autopay','sms_otp') NOT NULL,
  `environment` ENUM('demo','sandbox','production') NOT NULL DEFAULT 'demo',
  `configuration_status` ENUM('not_configured','configured_sandbox','active_production') NOT NULL DEFAULT 'not_configured',
  `fee_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partner_code` (`partner_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhook_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `partner_code` VARCHAR(50) NOT NULL,
  `event_id` VARCHAR(120) NOT NULL,
  `event_type` VARCHAR(80) NOT NULL,
  `signature_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `payload_json` JSON NOT NULL,
  `processing_status` ENUM('received','processed','rejected_signature','duplicate_ignored','failed') NOT NULL DEFAULT 'received',
  `processed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_webhook_partner_event` (`partner_code`, `event_id`),
  KEY `idx_webhook_status` (`processing_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 13. Audit Logs, Admin Actions & Notifications
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `actor_role` VARCHAR(40) DEFAULT NULL,
  `action` VARCHAR(80) NOT NULL,
  `entity_type` VARCHAR(60) NOT NULL,
  `entity_id` VARCHAR(64) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `metadata_json` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_actor` (`actor_user_id`, `created_at`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_actions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_user_id` BIGINT UNSIGNED NOT NULL,
  `action_type` VARCHAR(80) NOT NULL,
  `target_table` VARCHAR(60) NOT NULL,
  `target_id` BIGINT UNSIGNED NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_actions_admin` (`admin_user_id`, `created_at`),
  CONSTRAINT `fk_admin_actions_user` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `message` VARCHAR(500) NOT NULL,
  `category` ENUM('bill','payment','reminder','mandate','kyc','support','security') NOT NULL DEFAULT 'bill',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`, `is_read`, `created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 14. Append-Only Ledger Protection Triggers (No Silent Deletion/Mutation)
-- ----------------------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER `trg_ledger_no_delete`
BEFORE DELETE ON `ledger_entries`
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Append-only ledger violation: financial ledger entries cannot be deleted. Insert a reversal_entry instead.';
END$$

CREATE TRIGGER `trg_ledger_no_update`
BEFORE UPDATE ON `ledger_entries`
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Append-only ledger violation: financial ledger entries are immutable.';
END$$
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;
