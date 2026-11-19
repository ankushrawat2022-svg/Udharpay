<?php
declare(strict_types=1);

final class AuthController
{
    public static function register(array $config): never
    {
        $input = Validator::readJsonBody();
        Validator::requireFields($input, ['full_name', 'phone', 'password', 'role']);

        $roleSlug = in_array($input['role'], ['merchant', 'customer'], true) ? $input['role'] : 'customer';
        $phone = Validator::indianPhone((string)$input['phone']);
        $fullName = Validator::cleanString((string)$input['full_name'], 120);
        $email = isset($input['email']) && trim((string)$input['email']) !== ''
            ? filter_var(trim((string)$input['email']), FILTER_VALIDATE_EMAIL)
            : null;
        if ($email === false) {
            Response::error('Invalid email address.', 422);
        }
        $password = (string)$input['password'];
        if (strlen($password) < 8) {
            Response::error('Password must be at least 8 characters.', 422);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $roleId = (int)$pdo->query("SELECT id FROM roles WHERE slug = " . $pdo->quote($roleSlug))->fetchColumn();
            $stmt = $pdo->prepare(
                'INSERT INTO users (role_id, full_name, phone, email, password_hash, status)
                 VALUES (:role_id, :full_name, :phone, :email, :hash, "active")'
            );
            $stmt->execute([
                'role_id' => $roleId,
                'full_name' => $fullName,
                'phone' => $phone,
                'email' => $email,
                'hash' => Security::hashPassword($password),
            ]);
            $userId = (int)$pdo->lastInsertId();

            if ($roleSlug === 'merchant') {
                $bizName = Validator::cleanString((string)($input['business_name'] ?? ($fullName . ' Enterprises')), 160);
                $mStmt = $pdo->prepare(
                    'INSERT INTO merchants (user_id, business_legal_name, verification_status) VALUES (:uid, :biz, "pending")'
                );
                $mStmt->execute(['uid' => $userId, 'biz' => $bizName]);
            } else {
                $custCode = Security::generateReference('CUST');
                $cStmt = $pdo->prepare(
                    'INSERT INTO customers (user_id, customer_code, kyc_status) VALUES (:uid, :code, "unverified")'
                );
                $cStmt->execute(['uid' => $userId, 'code' => $custCode]);
            }

            $session = UserModel::createSession($userId, (int)$config['session_ttl']);
            $pdo->commit();

            AuditService::log($userId, $roleSlug, 'user.register', 'users', (string)$userId);
            Response::success([
                'user' => ['id' => $userId, 'full_name' => $fullName, 'phone' => $phone, 'role' => $roleSlug],
                'session' => $session,
            ], 'Registration successful.', 201);
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                Response::error('An account with this phone or email already exists.', 409);
            }
            Response::error('Registration failed.', 500);
        }
    }

    public static function requestOtp(): never
    {
        $input = Validator::readJsonBody();
        Validator::requireFields($input, ['phone']);
        $phone = Validator::indianPhone((string)$input['phone']);

        $user = UserModel::findByPhoneOrEmail($phone);
        if (!$user) {
            Response::error('No account found for this mobile number.', 404);
        }

        // Real SMS OTP provider is NOT CONFIGURED; generate a verifiable 6-digit demo OTP
        $otp = (string)random_int(100000, 999999);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE users SET otp_hash = :hash, otp_expires_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE id = :id');
        $stmt->execute([
            'hash' => Security::hashPassword($otp),
            'id'   => $user['id'],
        ]);

        Response::success([
            'phone' => $phone,
            'otp_mode' => 'DEMO_SMS_NOT_CONFIGURED',
            'demo_otp' => $otp,
            'expires_in_seconds' => 300,
        ], 'DEMO OTP generated (SMS provider is NOT CONFIGURED).');
    }

    public static function verifyOtp(array $config): never
    {
        $input = Validator::readJsonBody();
        Validator::requireFields($input, ['phone', 'otp']);
        $phone = Validator::indianPhone((string)$input['phone']);
        $otp = trim((string)$input['otp']);

        $user = UserModel::findByPhoneOrEmail($phone);
        if (!$user || empty($user['otp_hash']) || empty($user['otp_expires_at'])) {
            Response::error('OTP not requested or expired.', 401);
        }
        if (strtotime((string)$user['otp_expires_at']) < time() || !Security::verifyPassword($otp, (string)$user['otp_hash'])) {
            Response::error('Invalid or expired OTP.', 401);
        }

        $pdo = Database::connection();
        $pdo->prepare('UPDATE users SET otp_hash = NULL, otp_expires_at = NULL WHERE id = :id')->execute(['id' => $user['id']]);
        $session = UserModel::createSession((int)$user['id'], (int)$config['session_ttl']);

        AuditService::log((int)$user['id'], (string)$user['role'], 'auth.otp_login', 'users', (string)$user['id']);
        Response::success([
            'user' => [
                'id' => (int)$user['id'],
                'full_name' => $user['full_name'],
                'phone' => $user['phone'],
                'email' => $user['email'],
                'role' => $user['role'],
                'is_demo' => (bool)$user['is_demo'],
            ],
            'session' => $session,
        ], 'OTP login successful.');
    }

    public static function login(array $config): never
    {
        $input = Validator::readJsonBody();
        Validator::requireFields($input, ['identifier', 'password']);

        $user = UserModel::findByPhoneOrEmail(trim((string)$input['identifier']));
        if (!$user || !Security::verifyPassword((string)$input['password'], (string)$user['password_hash'])) {
            Response::error('Invalid credentials.', 401);
        }
        if ($user['status'] !== 'active') {
            Response::error('Account is suspended or inactive.', 403);
        }

        $session = UserModel::createSession((int)$user['id'], (int)$config['session_ttl']);
        setcookie('udharpay_session', $session['token'], [
            'expires' => time() + (int)$config['session_ttl'],
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        AuditService::log((int)$user['id'], (string)$user['role'], 'auth.password_login', 'users', (string)$user['id']);
        Response::success([
            'user' => [
                'id' => (int)$user['id'],
                'full_name' => $user['full_name'],
                'phone' => $user['phone'],
                'email' => $user['email'],
                'role' => $user['role'],
                'is_demo' => (bool)$user['is_demo'],
            ],
            'session' => $session,
        ], 'Signed in successfully.');
    }

    public static function me(array $user): never
    {
        Response::success(['user' => $user]);
    }

    public static function logout(array $user): never
    {
        UserModel::revokeSession((int)$user['session_id']);
        setcookie('udharpay_session', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        AuditService::log((int)$user['id'], (string)$user['role'], 'auth.logout', 'users', (string)$user['id']);
        Response::success(null, 'Signed out.');
    }
}
