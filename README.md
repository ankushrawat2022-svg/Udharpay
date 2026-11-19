# UdharPay — Digital Credit & Udhar Management Platform (India)

UdharPay is a separated-architecture Digital Credit & Udhar Management Platform designed for Indian neighborhood merchants, kirana stores, pharmacies, and their customers.

## Architecture
- **Frontend (`frontend/`)**: React + Vite + JavaScript + CSS mobile-first SPA communicating strictly over REST APIs.
- **Backend (`backend/`)**: Core PHP 8.3+ REST API with PDO prepared statements, RBAC, append-only ledger transactions, rate limiting, CSRF checks, audit logging, and webhook HMAC verification.
- **Database (`database/`)**: MariaDB / MySQL (`database.sql` + `demo_data.sql`) with 34 normalized tables, foreign keys, indexes, and append-only ledger protection triggers.
- **Documentation (`docs/`)**: Includes Beginner Hindi Android (AWebServer) Installation Guide, API Docs, Database Guide, Security Guide, and RBI/NPCI Payment Integration Guide.

## Core Safeguards
1. **Explicit Customer Bill Approval**: Scanning a shop QR code shows verified shop info and pending bills only. Debt is NEVER created automatically on QR scan.
2. **Append-Only Ledger Engine**: Successful financial entries are immutable (`ledger_entries` forbids DELETE/UPDATE at the MariaDB trigger level). Corrections require explicit reversal entries.
3. **Duplicate Transaction Protection**: Idempotency keys (`idempotency_key`) prevent double billing and double repayment submissions.
4. **Compliant Payment Architecture**: Real UPI / AutoPay partner integrations are disabled and marked `NOT CONFIGURED` until authorized partner credentials and RBI/NPCI approvals are provisioned. Safe `DEMO` payment mode is clearly labeled throughout.

## Quick Start
See `docs/INSTALLATION.md` for complete Hindi step-by-step instructions for Android (AWebServer + phpMyAdmin).
