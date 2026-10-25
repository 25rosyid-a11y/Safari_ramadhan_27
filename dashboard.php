<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
wajibLogin();

$peserta = (int) $pdo->query(
    "SELECT COUNT(*) FROM peserta WHERE status = 'aktif'"
)->fetchColumn();

$pemasukan = (float) $pdo->query(
    "SELECT COALESCE(SUM(jumlah), 0)
     FROM pembayaran WHERE status = 'diterima'"
)->fetchColumn();

$pengeluaran = (float) $pdo->query(
    "SELECT COALESCE(SUM(jumlah), 0)
     FROM pengeluaran WHERE status = 'disetujui'"
)->fetchColumn();

$tunggakan = (float) $pdo->query(
        "SELECT COALESCE(SUM(CASE
            WHEN p.biaya > COALESCE(b.total, 0)
            THEN p.biaya - COALESCE(b.total, 0)
            ELSE 0 END), 0)
     FROM peserta p
     LEFT JOIN (
         SELECT peserta_id, SUM(jumlah) AS total
         FROM pembayaran
         WHERE status = 'diterima'
         GROUP BY peserta_id
     ) b ON b.peserta_id = p.id
     WHERE p.status = 'aktif'"
)->fetchColumn();

$saldo = $pemasukan - $pengeluaran;

function rupiah(float $nilai): string
{
    return 'Rp ' . number_format($nilai, 0, ',', '.');
}

function h(string $nilai): string
{
    return htmlspecialchars($nilai, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | SAFROM</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; background: #0b1220; color: #edf3fc;
            font-family: Arial, sans-serif;
        }
        .layout { display: flex; min-height: 100vh; }
        aside {
            width: 235px; padding: 25px 18px;
            background: #111d30; border-right: 1px solid #26354a;
        }
        .brand { font-size: 25px; color: #53c7e5; font-weight: 800; }
        aside p { color: #96a8bf; font-size: 13px; }
        nav { display: grid; gap: 8px; margin-top: 30px; }
        nav a {
            color: #d5e0ef; text-decoration: none;
            padding: 12px; border-radius: 8px;
        }
        nav a.active, nav a:hover { background: #20334d; }
        main { padding: 30px; width: 100%; min-width: 0; }
        .top {
            display: flex; justify-content: space-between;
            align-items: center; gap: 15px; flex-wrap: wrap;
        }
        .muted { color: #97a9c0; }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px; margin-top: 28px;
        }
        .card {
            padding: 22px; background: #142238;
            border: 1px solid #293c56; border-radius: 14px;
        }
        .card p { color: #a9b9cc; margin: 0 0 12px; }
        .value { font-size: clamp(20px, 2.5vw, 27px); font-weight: bold; }
        .links { margin-top: 25px; display: flex; gap: 10px; flex-wrap: wrap; }
        .links a, .logout {
            padding: 11px 14px; border-radius: 8px;
            background: #213550; color: #eaf4ff; text-decoration: none;
        }
        .logout { background: #67303b; }
        @media (max-width: 650px) {
            .layout { display: block; }
            aside { width: 100%; }
            nav { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            main { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside>
        <div class="brand">SAFROM</div>
        <p>Management System</p>
        <nav>
            <a class="active" href="dashboard.php">Dashboard</a>
            <a href="peserta/">Data Peserta</a>
            <a href="kelompok/">Kelompok / Pos</a>
            <a href="pembayaran/">Pembayaran</a>
            <a href="pengeluaran/">Pengeluaran</a>
            <a href="laporan/">Laporan</a>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <a href="pengguna/">Manajemen Pengguna</a>
            <?php endif; ?>
        </nav>
    </aside>

    <main>
        <div class="top">
            <div>
                <h1>Dashboard</h1>
                <p class="muted">
                    Selamat datang, <?= h((string) $_SESSION['nama']) ?>
                    · <?= h((string) $_SESSION['role']) ?>
                </p>
            </div>
            <a class="logout" href="logout.php">Keluar</a>
        </div>

        <section class="cards">
            <div class="card">
                <p>Peserta Aktif</p>
                <div class="value"><?= number_format($peserta, 0, ',', '.') ?></div>
            </div>
            <div class="card">
                <p>Total Pembayaran</p>
                <div class="value"><?= rupiah($pemasukan) ?></div>
            </div>
            <div class="card">
                <p>Total Pengeluaran</p>
                <div class="value"><?= rupiah($pengeluaran) ?></div>
            </div>
            <div class="card">
                <p>Saldo Kas</p>
                <div class="value"><?= rupiah($saldo) ?></div>
            </div>
            <div class="card">
                <p>Total Tunggakan</p>
                <div class="value"><?= rupiah($tunggakan) ?></div>
            </div>
        </section>

        <div class="links">
            <a href="peserta/">Kelola Peserta</a>
            <a href="pembayaran/">Catat Pembayaran</a>
            <a href="pengeluaran/">Catat Pengeluaran</a>
        </div>
    </main>
</div>
</body>
</html>
