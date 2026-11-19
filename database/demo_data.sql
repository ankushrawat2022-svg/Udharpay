-- ============================================================================
-- UdharPay — Demo Seed Data (Clearly Marked DEMO)
-- Password for all DEMO accounts: Demo@1234 (bcrypt cost 10)
-- ============================================================================

USE `udharpay_db`;

INSERT INTO `roles` (`id`, `slug`, `name`, `description`) VALUES
(1, 'super_admin', 'Super Admin', 'Full platform oversight, KYC, RBAC, audit logs, and system configuration'),
(2, 'merchant', 'Merchant', 'Shop owner managing bills, credit limits, staff, and customer ledger'),
(3, 'customer', 'Customer', 'End user approving credit bills, managing repayments, and mandates'),
(4, 'support', 'Support', 'Customer and merchant complaints, disputes, and ticket resolution'),
(5, 'finance', 'Finance', 'Subscriptions, invoices, settlements, GST tax records, and refunds'),
(6, 'manager', 'Manager / Staff', 'Assigned shop verification, staff oversight, and limited reporting')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `permissions` (`code`, `module`, `description`) VALUES
('merchant.shop.manage', 'merchant', 'Create and update owned shops and permanent QR'),
('merchant.bill.create', 'merchant', 'Create customer credit bills awaiting customer approval'),
('merchant.credit_limit.set', 'merchant', 'Configure shop-level customer credit limits and block credit'),
('customer.bill.approve', 'customer', 'Explicitly accept or reject credit bills'),
('customer.repayment.demo', 'customer', 'Perform full, partial, or installment repayments'),
('customer.mandate.cancel', 'customer', 'Request cancellation or revocation of AutoPay mandate'),
('admin.merchant.verify', 'admin', 'Approve or reject merchant and shop verification'),
('admin.kyc.review', 'admin', 'Review and update customer KYC statuses'),
('admin.audit.read', 'admin', 'View immutable platform audit logs'),
('support.ticket.manage', 'support', 'Assign, reply, escalate, and resolve support tickets'),
('finance.reports.view', 'finance', 'View revenue, invoices, settlements, and tax reports'),
('manager.assigned_shops.manage', 'manager', 'Manage assigned shops with scoped permissions')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- All demo users share bcrypt hash for 'Demo@1234'
SET @DEMO_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

INSERT INTO `users` (`id`, `role_id`, `full_name`, `phone`, `email`, `password_hash`, `is_demo`, `status`) VALUES
(1, 1, 'Vikramaditya Rao (DEMO Admin)', '9800000001', 'admin@udharpay.demo', @DEMO_HASH, 1, 'active'),
(2, 2, 'Rajesh Sharma (DEMO Merchant)', '9800000002', 'merchant@udharpay.demo', @DEMO_HASH, 1, 'active'),
(3, 3, 'Amit Verma (DEMO Customer)', '9800000003', 'customer@udharpay.demo', @DEMO_HASH, 1, 'active'),
(4, 4, 'Neha Kulkarni (DEMO Support)', '9800000004', 'support@udharpay.demo', @DEMO_HASH, 1, 'active'),
(5, 5, 'Suresh Nair (DEMO Finance)', '9800000005', 'finance@udharpay.demo', @DEMO_HASH, 1, 'active'),
(6, 6, 'Pradeep Joshi (DEMO Manager)', '9800000006', 'manager@udharpay.demo', @DEMO_HASH, 1, 'active'),
(7, 3, 'Priya Deshmukh (DEMO Customer)', '9800000007', 'priya@udharpay.demo', @DEMO_HASH, 1, 'active')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

INSERT INTO `merchants` (`id`, `user_id`, `business_legal_name`, `gstin`, `pan_masked`, `verification_status`, `verified_by`, `verified_at`, `subscription_plan`) VALUES
(1, 2, 'Sharma Retail Enterprises', '07AAACS1234F1Z5', 'AAACS****F', 'verified', 1, NOW(), 'growth')
ON DUPLICATE KEY UPDATE `business_legal_name` = VALUES(`business_legal_name`);

INSERT INTO `shops` (`id`, `merchant_id`, `shop_code`, `shop_name`, `category`, `address`, `city`, `state`, `pincode`, `phone`, `default_credit_limit`, `is_verified`, `status`) VALUES
(1, 1, 'SHP-DL-1001', 'Sharma Kirana Mart', 'Grocery & Daily Needs', 'Shop 14, Sector 8 Market, Rohini', 'New Delhi', 'Delhi', '110085', '9800000002', 15000.00, 1, 'active'),
(2, 1, 'SHP-DL-1002', 'Sharma Medicos & Wellness', 'Pharmacy & Healthcare', 'Plot 22, Main Road, Pitampura', 'New Delhi', 'Delhi', '110034', '9800000012', 10000.00, 1, 'active')
ON DUPLICATE KEY UPDATE `shop_name` = VALUES(`shop_name`);

INSERT INTO `shop_qr_codes` (`shop_id`, `qr_token`, `qr_payload_url`, `is_active`, `scan_count`) VALUES
(1, 'QR-SHOP-SHP-DL-1001-VERIFIED', 'https://udharpay.local/qr/shop/QR-SHOP-SHP-DL-1001-VERIFIED', 1, 148),
(2, 'QR-SHOP-SHP-DL-1002-VERIFIED', 'https://udharpay.local/qr/shop/QR-SHOP-SHP-DL-1002-VERIFIED', 1, 64)
ON DUPLICATE KEY UPDATE `is_active` = 1;

INSERT INTO `shop_staff` (`shop_id`, `user_id`, `staff_role`, `can_create_bill`, `can_record_payment`, `can_change_limit`, `status`) VALUES
(1, 6, 'manager', 1, 1, 0, 'active'),
(2, 6, 'verifier', 0, 0, 0, 'active')
ON DUPLICATE KEY UPDATE `status` = 'active';

INSERT INTO `customers` (`id`, `user_id`, `customer_code`, `address`, `city`, `pincode`, `kyc_status`) VALUES
(1, 3, 'CUST-IN-9001', 'B-42, Sector 7, Rohini', 'New Delhi', '110085', 'verified'),
(2, 7, 'CUST-IN-9002', 'C-11, Prashant Vihar', 'New Delhi', '110085', 'pending')
ON DUPLICATE KEY UPDATE `kyc_status` = VALUES(`kyc_status`);

INSERT INTO `credit_limits` (`shop_id`, `customer_id`, `credit_limit`, `suggested_limit`, `is_credit_blocked`, `updated_by`) VALUES
(1, 1, 15000.00, 18000.00, 0, 2),
(2, 1, 8000.00, 8000.00, 0, 2),
(1, 2, 5000.00, 5000.00, 0, 2)
ON DUPLICATE KEY UPDATE `credit_limit` = VALUES(`credit_limit`);

INSERT INTO `credit_accounts` (`id`, `shop_id`, `customer_id`, `total_credited`, `total_repaid`, `outstanding_balance`, `status`, `last_activity_at`) VALUES
(1, 1, 1, 6450.00, 2500.00, 3950.00, 'active', NOW()),
(2, 2, 1, 1850.00, 0.00, 1850.00, 'active', NOW()),
(3, 1, 2, 2200.00, 2200.00, 0.00, 'settled', NOW())
ON DUPLICATE KEY UPDATE `outstanding_balance` = VALUES(`outstanding_balance`);

INSERT INTO `credit_bills` (`id`, `bill_number`, `bill_qr_token`, `shop_id`, `customer_id`, `created_by_user_id`, `subtotal_amount`, `tax_amount`, `total_amount`, `paid_amount`, `pending_amount`, `installment_count`, `due_date`, `notes`, `status`, `customer_approved_at`, `idempotency_key`) VALUES
(1, 'UP-2026-0001', 'BILLQR-UP-2026-0001', 1, 1, 2, 4250.00, 0.00, 4250.00, 2500.00, 1750.00, 2, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Monthly ration & staples', 'partially_paid', NOW(), 'seed-bill-1'),
(2, 'UP-2026-0002', 'BILLQR-UP-2026-0002', 1, 1, 2, 2200.00, 0.00, 2200.00, 0.00, 2200.00, 1, DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'Festival dry fruits & oil', 'active', NOW(), 'seed-bill-2'),
(3, 'UP-2026-0003', 'BILLQR-UP-2026-0003', 1, 1, 2, 1340.00, 0.00, 1340.00, 0.00, 1340.00, 1, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'Fresh dairy & flour pack — awaiting customer approval', 'pending_customer_approval', NULL, 'seed-bill-3'),
(4, 'UP-2026-0004', 'BILLQR-UP-2026-0004', 2, 1, 2, 1850.00, 0.00, 1850.00, 0.00, 1850.00, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Monthly prescription refill', 'active', NOW(), 'seed-bill-4')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

INSERT INTO `credit_bill_items` (`bill_id`, `item_name`, `quantity`, `unit`, `unit_price`, `line_total`) VALUES
(1, 'Sharbat Atta 10kg', 2, 'bag', 460.00, 920.00),
(1, 'Basmati Rice Premium 10kg', 2, 'bag', 1150.00, 2300.00),
(1, 'Cold Pressed Mustard Oil 5L', 1, 'can', 1030.00, 1030.00),
(2, 'California Almonds 1kg', 2, 'pack', 850.00, 1700.00),
(2, 'Pure Cow Ghee 500ml', 1, 'jar', 500.00, 500.00),
(3, 'A2 Milk Pack', 4, 'ltr', 95.00, 380.00),
(3, 'Toor Dal Unpolished 4kg', 4, 'kg', 190.00, 760.00),
(3, 'Organic Jaggery 2kg', 2, 'kg', 100.00, 200.00);

INSERT INTO `payments` (`id`, `payment_ref`, `idempotency_key`, `shop_id`, `customer_id`, `bill_id`, `amount`, `payment_mode`, `payment_type`, `is_demo`, `partner_name`, `partner_transaction_id`, `status`, `receipt_number`, `verified_at`) VALUES
(1, 'PAY-DEMO-20260001', 'seed-pay-1', 1, 1, 1, 2500.00, 'demo_upi', 'partial', 1, 'DEMO_GATEWAY', 'DEMO-UPI-9928371', 'succeeded', 'RCPT-UP-2026-0001', NOW())
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

INSERT INTO `ledger_entries` (`reference_number`, `credit_account_id`, `shop_id`, `customer_id`, `bill_id`, `payment_id`, `entry_type`, `amount`, `running_balance`, `description`, `created_by_user_id`) VALUES
('LED-2026-0001', 1, 1, 1, 1, NULL, 'credit_debit', 4250.00, 4250.00, 'Customer approved credit bill UP-2026-0001', 3),
('LED-2026-0002', 1, 1, 1, 1, 1, 'repayment_credit', 2500.00, 1750.00, 'DEMO partial repayment RCPT-UP-2026-0001', 3),
('LED-2026-0003', 1, 1, 1, 2, NULL, 'credit_debit', 2200.00, 3950.00, 'Customer approved credit bill UP-2026-0002', 3),
('LED-2026-0004', 2, 2, 1, 4, NULL, 'credit_debit', 1850.00, 1850.00, 'Customer approved credit bill UP-2026-0004', 3)
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT INTO `payment_mandates` (`mandate_ref`, `customer_id`, `shop_id`, `partner_name`, `max_amount`, `frequency`, `status`, `valid_from`, `valid_until`, `customer_vpa_masked`) VALUES
('MND-DEMO-001', 1, 1, 'NOT_CONFIGURED_DEMO', 5000.00, 'monthly', 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY), 'amit****@okhdfcbank')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

INSERT INTO `partner_accounts` (`partner_code`, `partner_name`, `capability`, `environment`, `configuration_status`, `fee_percent`) VALUES
('DEMO_MODE', 'UdharPay Safe Demo Simulator', 'upi_payment', 'demo', 'configured_sandbox', 0.00),
('RAZORPAY_UPI', 'Razorpay UPI & AutoPay (NOT CONFIGURED)', 'upi_autopay', 'production', 'not_configured', 0.45),
('CASHFREE_PG', 'Cashfree Payment Gateway (NOT CONFIGURED)', 'payment_gateway', 'production', 'not_configured', 0.60),
('MSG91_OTP', 'MSG91 DLT SMS OTP (NOT CONFIGURED)', 'sms_otp', 'production', 'not_configured', 0.00)
ON DUPLICATE KEY UPDATE `configuration_status` = VALUES(`configuration_status`);

INSERT INTO `subscriptions` (`id`, `merchant_id`, `plan_code`, `monthly_fee`, `status`, `current_period_start`, `current_period_end`) VALUES
(1, 1, 'growth', 999.00, 'active', DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY))
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

INSERT INTO `invoices` (`invoice_number`, `merchant_id`, `subscription_id`, `subtotal`, `gst_amount`, `total_amount`, `status`, `due_date`, `paid_at`) VALUES
('INV-UP-2026-001', 1, 1, 999.00, 179.82, 1178.82, 'paid', CURDATE(), NOW())
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

INSERT INTO `support_tickets` (`ticket_number`, `requester_user_id`, `assigned_agent_id`, `priority`, `category`, `subject`, `last_reply`, `status`) VALUES
('TKT-2026-101', 3, 4, 'medium', 'incorrect_bill', 'Clarification on quantity in bill UP-2026-0002', 'Support verified item weights with Sharma Kirana Mart.', 'assigned'),
('TKT-2026-102', 2, 4, 'high', 'mandate', 'Merchant inquiry on customer AutoPay pre-debit timeline', 'Shared RBI/NPCI 24-hour pre-debit notification compliance guidelines.', 'open')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);
