# UdharPay Payment & UPI AutoPay Integration Guide (RBI / NPCI Compliance)

## Current Status: SAFE DEMO MODE
- Live UPI Gateway, Payment Aggregator, and UPI AutoPay Mandate integrations are **DISABLED** and marked **`NOT CONFIGURED`**.
- All test transactions run through `DemoPaymentService` with explicit `is_demo = 1` flags and `RCPT-DEMO-...` receipt numbers.

## Mandatory Legal & Technical Rules Implemented
1. **No Unauthorized Debits**: UdharPay never performs unauthorized bank debits and never claims to debit funds simply because money enters a customer's bank account.
2. **Authorized Partner & Customer-Approved Mandate Only**: Live UPI AutoPay requires onboarding with an RBI-licensed Payment Aggregator / NPCI-approved UPI PSP and explicit customer mandate authorization (plus mandatory 24-hour pre-debit notification per RBI e-mandate directives).
3. **Mandate Revocation Rights**: Customers can cancel or revoke a mandate at any time (`POST /api/mandates/{id}/cancel`). When cancelled:
   - Existing outstanding Udhar balances are **strictly preserved** in the append-only ledger.
   - The merchant receives an immediate notification.
   - Alternative repayment methods (Standard UPI Intent, Counter Cash Receipt, Installment Plan) are offered.
4. **Server-Side Webhook Verification**: Payments are NEVER marked successful in live mode based on client-side callbacks. `WebhookController` + `WebhookService` verify HMAC-SHA256 signatures server-side using `hash_equals` and deduplicate events via `(partner_code, event_id)`.
5. **No Payment Secrets Stored**: Never collect or store UPI PIN, MPIN, netbanking passwords, or card CVV.
