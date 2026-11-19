# UdharPay MariaDB Database Guide

## Schema Highlights (`database/database.sql`)
- **Engine**: InnoDB with `utf8mb4_unicode_ci` for full Indian regional language support (Hindi/Devanagari names & addresses).
- **34 Normalized Tables**: Covers RBAC, user sessions, merchants, multi-shop tenant isolation, permanent shop QR tokens, customer KYC, explicit legal consents, shop-level credit limits, itemized credit bills, append-only ledger entries, payments, repayments, payment attempts, RBI/NPCI mandates & mandate events, transparent risk assessments, manual credit decisions, complaints, disputes, support tickets, subscriptions, GST invoices, partner accounts, webhooks, audit logs, admin actions, and notifications.

## Append-Only Financial Ledger Guarantee
Two MariaDB triggers protect `ledger_entries`:
1. `trg_ledger_no_delete`: Blocks any `DELETE` on `ledger_entries` with SQLSTATE `45000`.
2. `trg_ledger_no_update`: Blocks any `UPDATE` on `ledger_entries` with SQLSTATE `45000`.

Every adjustment or refund must be recorded as a new `refund_adjustment` or `reversal_entry` row with its own unique `reference_number`.

## Tenant Isolation
Every shop-scoped table (`credit_limits`, `credit_accounts`, `credit_bills`, `ledger_entries`, `payments`, `payment_mandates`) enforces `shop_id` foreign keys and composite indexes `(shop_id, customer_id)`, and backend controllers verify ownership via `ShopModel::verifyMerchantOwnsShop()`.
