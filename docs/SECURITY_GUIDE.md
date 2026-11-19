# UdharPay Security Architecture Guide

1. **Password Hashing**: Uses `password_hash(..., PASSWORD_BCRYPT, ['cost' => 11])` and constant-time `password_verify`. Zero plaintext passwords stored anywhere.
2. **SQL Injection Defense**: Strict PDO prepared statements with `PDO::ATTR_EMULATE_PREPARES => false`.
3. **Authentication & Session Hygiene**: Random 256-bit tokens hashed via SHA-256 before storage in `user_sessions`. Cookies set with `HttpOnly` and `SameSite=Lax`.
4. **CSRF Protection**: Enforced via `CsrfMiddleware` comparing `X-CSRF-Token` against session-bound CSRF token for cookie-authenticated mutations.
5. **Role-Based Access Control (RBAC)**: Every endpoint checks `RoleMiddleware::requireRole()` before reading or mutating records.
6. **Tenant Isolation**: Merchants can only read/write shops owned by their `merchant_id`. Customers can only approve or repay their own bills.
7. **Zero PII in QR Codes**: Shop QR codes encode only opaque tokens (`QR-SHOP-...`). No customer phone, Aadhaar, PAN, or balance ever appears in any QR code.
8. **File Upload Hardening**: `UploadHelper` validates MIME types via `finfo`, caps size at 2 MB, renames files randomly, and `uploads/.htaccess` disables PHP execution.
9. **Secret Hygiene**: `.env` is kept outside public webroot and blocked by `.htaccess`. No payment secrets or DB credentials ever enter frontend code.
10. **Payment Auth Data Rule**: UdharPay NEVER collects or stores UPI PINs, banking passwords, or card CVVs.
