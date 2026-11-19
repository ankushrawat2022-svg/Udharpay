# UdharPay REST API Documentation

Base URL (Local AWebServer): `http://localhost:8080/UdharPay/backend`

## Authentication Headers
- `Authorization: Bearer <token>` (or `HttpOnly` cookie `udharpay_session`)
- `X-CSRF-Token: <csrf_token>` (required when using cookie auth on state-changing requests)
- `Idempotency-Key: <unique-uuid>` (required on bill creation and repayment requests to prevent duplicate transactions)

## Endpoints

| Method | Path | Roles | Purpose |
|---|---|---|---|
| `GET` | `/api/health` | Public | API & MariaDB status check |
| `POST` | `/api/auth/register` | Public | Register Merchant or Customer |
| `POST` | `/api/auth/login` | Public | Phone/Email + Password login |
| `POST` | `/api/auth/otp/request` | Public | Generate DEMO OTP (SMS gateway NOT CONFIGURED) |
| `POST` | `/api/auth/otp/verify` | Public | Verify mobile OTP and issue session |
| `GET` | `/api/auth/me` | Authenticated | Current authenticated user profile |
| `POST` | `/api/auth/logout` | Authenticated | Revoke session token |
| `GET` | `/api/qr/shop/{qrToken}` | Public | Verified shop info only (Zero PII, Zero auto-debt) |
| `GET` | `/api/qr/bill/{billQrToken}` | Customer / Merchant | Secure bill details lookup |
| `GET` | `/api/merchant/dashboard` | Merchant | Multi-shop KPIs, customers, bills, ledger |
| `POST` | `/api/merchant/shops` | Merchant | Add new shop + permanent unique QR |
| `POST` | `/api/merchant/credit-limits` | Merchant | Set credit limit or block/unblock credit |
| `POST` | `/api/bills` | Merchant / Manager | Create multi-item credit bill (`pending_customer_approval`) |
| `POST` | `/api/bills/{id}/decide` | Customer | Explicitly `accept` or `reject` bill |
| `GET` | `/api/customer/dashboard` | Customer | Connected shops, shop-wise balances, bills, mandates |
| `POST` | `/api/payments/repay` | Customer / Merchant | Full, partial, or installment DEMO repayment |
| `POST` | `/api/mandates/{id}/cancel` | Customer | Revoke mandate while preserving debt & notifying merchant |
| `GET` | `/api/admin/overview` | Super Admin | Platform KPIs, KYC queue, partners, audit logs, DB health |
| `POST` | `/api/admin/merchants/{id}/verify` | Super Admin / Manager | Verify or reject merchant and shops |
| `GET` | `/api/support/tickets` | Support / Super Admin | View filtered support tickets |
| `POST` | `/api/complaints` | Customer / Merchant | Submit complaint and open ticket |
| `PATCH` | `/api/support/tickets/{id}` | Support / Super Admin | Assign, reply, escalate, or resolve ticket |
| `GET` | `/api/finance/overview` | Finance / Super Admin | Subscriptions, GST invoices, settlements, refunds |
| `GET` | `/api/manager/shops` | Manager / Super Admin | Assigned shops, scoped permissions, activity log |
| `POST` | `/api/webhooks/{partnerCode}` | Partner Server | HMAC-SHA256 verified payment webhook endpoint |
