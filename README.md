# SAFROM

## Persiapan database

PHP 8.1+ diperlukan, termasuk ekstensi `pdo_mysql` untuk MySQL atau `pdo_sqlite` untuk SQLite. Impor tepat satu skema berikut sebelum membuka aplikasi:

- MySQL: `database/schema.mysql.sql`
- SQLite: `database/schema.sqlite.sql`

Untuk MySQL, buat database dan akun aplikasi (ganti nilai password contoh dengan password kuat), lalu impor `database/schema.mysql.sql` setelah memilih database `safrom_db` di phpMyAdmin:

```sql
CREATE DATABASE safrom_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'safrom_app'@'localhost' IDENTIFIED BY 'GANTI_DENGAN_PASSWORD_KUAT';
GRANT SELECT, INSERT, UPDATE, DELETE ON safrom_db.* TO 'safrom_app'@'localhost';
```

Untuk SQLite, jalankan `sqlite3 database.sqlite ".read database/schema.sqlite.sql"` dari folder proyek.

Driver default aplikasi adalah MySQL dan nama database default-nya `safrom_db`. Untuk pengembangan lokal, salin `config/database.local.php.example` menjadi `config/database.local.php`, lalu isi `user` dan `password` dengan kredensial MySQL yang Anda gunakan di phpMyAdmin. File lokal tersebut diabaikan Git agar password tidak ikut dibagikan. Contoh:

```php
return [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => '3306',
    'name' => 'safrom_db',
    'user' => 'USERNAME_MYSQL_ANDA',
    'password' => 'PASSWORD_MYSQL_ANDA',
];
```

Untuk menjalankan server lokal, buka PowerShell di folder proyek dan jalankan `php -S localhost:8000 -t .`, lalu buka `http://localhost:8000`. Alamat `phpmyadmin.test` adalah antarmuka pengelola database; aplikasi PHP tersambung ke MySQL melalui host, port, nama database, username, dan password. Jangan menaruh password di file source code yang dibagikan atau mengirimkannya melalui chat. Variabel environment `SAFROM_DB_*` tetap didukung dan nilainya mengesampingkan file lokal. Untuk SQLite gunakan `SAFROM_DB_DRIVER=sqlite` dan opsional `SAFROM_DB_PATH`. Aplikasi tidak berpindah database otomatis ketika koneksi gagal.

## Membuat admin pertama

Atur `SAFROM_ADMIN_PASSWORD` hanya pada sesi terminal yang sedang digunakan, lalu jalankan:

```powershell
$env:SAFROM_ADMIN_PASSWORD = 'GantiDenganPasswordKuat'
php tools/create_admin.php admin "Administrator SAFROM"
Remove-Item Env:SAFROM_ADMIN_PASSWORD
```

Perintah tersebut hanya bekerja jika tabel `users` masih kosong. Setelah berhasil, login lalu buat akun bendahara melalui menu Manajemen Pengguna.

## Mengatur ulang password

Jika tidak dapat masuk ke akun admin, reset password melalui PowerShell di folder proyek. Ganti `USERNAME_AKUN` dengan username yang tampil pada tabel **Akun Terdaftar** atau yang Anda ingat. Password diminta secara tersembunyi, tidak perlu dikirim melalui chat, dan minimal 12 karakter:

```powershell
$passwordA = Read-Host 'Password baru (minimal 12 karakter)' -AsSecureString
$passwordB = Read-Host 'Ulangi password baru' -AsSecureString
$bstrA = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($passwordA)
$bstrB = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($passwordB)
try {
    $plainA = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstrA)
    $plainB = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstrB)
    if ($plainA -ne $plainB) { throw 'Password tidak sama.' }
    $env:SAFROM_RESET_PASSWORD = $plainA
    php tools/reset_password.php USERNAME_AKUN
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstrA)
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstrB)
    Remove-Item Env:SAFROM_RESET_PASSWORD -ErrorAction SilentlyContinue
    Remove-Variable passwordA,passwordB,bstrA,bstrB,plainA,plainB -ErrorAction SilentlyContinue
}
```

Reset ini hanya mengganti password akun yang dipilih; akun dan data lainnya tidak dihapus. Hapus password yang diatur dari environment sudah dilakukan oleh blok `finally`.

## Membuat dan menghapus akun panitia

Login sebagai admin, lalu buka menu **Manajemen Pengguna**. Gunakan formulir **Daftarkan Admin** atau **Daftarkan Bendahara** yang terpisah untuk memasukkan nama, username, dan password secara manual. Password minimal 12 karakter. Proses pendaftaran akun baru tidak menghapus akun yang sudah ada.

Untuk kompatibilitas dengan akun lama yang password-nya tersimpan sebagai teks biasa, login yang berhasil akan langsung mengubah password tersebut menjadi hash aman. Akun baru selalu menyimpan password dalam bentuk hash.

Akun lama dapat dihapus satu per satu melalui tombol **Hapus Akun** pada daftar. Akun admin yang sedang digunakan dan admin aktif terakhir dilindungi. Penghapusan akun tidak menghapus data pembayaran/pengeluaran; referensi ke akun yang dihapus akan dikosongkan dan catatan aktivitas tetap tersimpan.

Role `admin` dapat mengelola data utama serta menyetujui/menolak transaksi. Role `bendahara` dapat mencatat peserta, pembayaran, dan pengeluaran, tetapi tidak dapat menyetujui transaksi atau mengelola akun.