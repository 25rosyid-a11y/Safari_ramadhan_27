<?php declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/password.php';

// Jika sudah login, arahkan ke dashboard.
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$cutoff = (new DateTimeImmutable('-15 minutes'))->format('Y-m-d H:i:s');
$attemptCount = $pdo->prepare(
    'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at >= ?'
);
$attemptCount->execute([$ipAddress, $cutoff]);
$tooManyAttempts = (int) $attemptCount->fetchColumn() >= 10;
$pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')
    ->execute([(new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s')]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } elseif ($tooManyAttempts) {
        http_response_code(429);
        $error = 'Terlalu banyak percobaan login. Silakan coba lagi dalam 15 menit.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, nama, username, password, role
                 FROM users
                 WHERE username = :username AND status = 'aktif'
                 LIMIT 1"
            );
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            $passwordValid = $user
                && is_string($user['password'])
                && verifikasiPasswordPengguna(
                    $pdo,
                    (int) $user['id'],
                    $user['password'],
                    $password
                );

            if ($user && $passwordValid) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                $log = $pdo->prepare(
                    'INSERT INTO log_aktivitas
                     (user_id, aktivitas, detail, ip_address)
                     VALUES (?, ?, ?, ?)'
                );
                $log->execute([
                    $user['id'],
                    'Login',
                    'Pengguna berhasil masuk',
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);

                header('Location: dashboard.php');
                exit;
            }

            $failedAttempt = $pdo->prepare(
                'INSERT INTO login_attempts (ip_address) VALUES (?)'
            );
            $failedAttempt->execute([$ipAddress]);
            $error = 'Username atau password salah, atau akun tidak aktif.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | SAFROM</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh;
            display: grid; place-items: center; padding: 20px;
            background:
                radial-gradient(ellipse at top right, #183957 0, transparent 45%),
                #09111f;
            color: #eef5ff; font-family: Arial, sans-serif;
        }
        .login {
            width: 100%; max-width: 420px; padding: 32px;
            background: #121f32; border: 1px solid #263c56;
            border-radius: 18px;
            box-shadow: 0 20px 60px #0005;
        }
        .brand {
            color: #54c9e8; font-size: 30px;
            font-weight: 800; letter-spacing: 3px;
        }
        .subtitle { color: #a9b9cd; line-height: 1.6; }
        label { display: block; margin: 18px 0 8px; }
        input {
            width: 100%; padding: 13px;
            border: 1px solid #344a65; border-radius: 9px;
            background: #0b1525; color: white; font-size: 16px;
        }
        input:focus { outline: 2px solid #38b9da; }
        button {
            width: 100%; margin-top: 24px; padding: 14px;
            border: 0; border-radius: 9px;
            background: #42c2df; color: #071421;
            font-weight: bold; font-size: 16px; cursor: pointer;
        }
        .error {
            padding: 12px; background: #47232c;
            border-radius: 8px; color: #ffd5dc;
        }
        footer { margin-top: 22px; color: #8294ab; font-size: 12px; }
    </style>
</head>
<body>
<main class="login">
    <div class="brand">SAFROM</div>
    <h1>Selamat Datang</h1>
    <p class="subtitle">
        Sistem Manajemen Safari Ramadan.<br>
        Silakan masuk menggunakan akun resmi panitia.
    </p>

    <?php if ($error !== ''): ?>
        <p class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <label for="username">Username</label>
        <input id="username" name="username"
            autocomplete="username" maxlength="50" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password"
            autocomplete="current-password" required>

        <button type="submit">Masuk ke SAFROM</button>
    </form>

    <footer>SAFROM Management System · Akses khusus panitia</footer>
</main>
</body>
</html>
