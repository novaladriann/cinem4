# CINEM4 - Website Pemesanan Tiket Bioskop Online

CINEM4 adalah aplikasi pemesanan tiket bioskop berbasis web yang dibuat menggunakan **PHP Native** dan **MySQL/MariaDB**. Aplikasi ini membantu pengguna untuk melihat daftar film, memilih jadwal tayang, memilih kursi, melakukan booking tiket, membayar melalui Midtrans, dan mendapatkan e-ticket digital.

Aplikasi ini juga menyediakan halaman admin untuk mengelola data film, bioskop, jadwal tayang, promo, user, booking, dan laporan transaksi.

---

## Fitur Utama

### User

- Registrasi akun user
- Verifikasi email menggunakan kode OTP
- Login user dengan captcha
- Melihat daftar film yang sedang tayang dan coming soon
- Melihat detail film, genre, durasi, rating umur, sinopsis, poster, dan trailer
- Memilih bioskop dan jadwal tayang
- Memilih kursi yang tersedia
- Menggunakan kode promo saat checkout
- Melakukan booking tiket
- Melakukan pembayaran melalui Midtrans Snap
- Melihat status pembayaran
- Mencetak atau menampilkan e-ticket berisi kode booking digital

### Admin

- Login admin
- Dashboard admin
- Mengelola data film
- Mengelola data bioskop
- Mengelola jadwal tayang
- Mengelola promo
- Mengelola data user
- Mengelola data booking
- Melihat detail transaksi dan status pembayaran
- Sinkronisasi status pembayaran Midtrans
- Melihat laporan transaksi dan pendapatan
- Manajemen admin berdasarkan role

---

## Teknologi yang Digunakan

- PHP Native
- MySQL / MariaDB
- HTML
- CSS
- JavaScript
- Bootstrap Icons
- PHPMailer
- Midtrans Snap Payment Gateway
- Composer

---

## Struktur Folder

```text
cinem4/
├── app/
│   ├── config/                 # Konfigurasi database, email, env, dan Midtrans
│   ├── data/                   # Data pendukung/static
│   ├── helpers/                # Helper tambahan
│   └── views/
│       ├── components/         # Komponen reusable
│       └── partials/public/    # Head, navbar, dan footer halaman publik
│
├── public/
│   ├── actions/                # Handler proses user dan pembayaran
│   ├── admin/                  # Halaman admin panel
│   ├── assets/                 # File CSS, gambar, icon, dan UI
│   ├── index.php               # Halaman utama
│   ├── movies.php              # Daftar film
│   ├── movie-detail.php        # Detail film
│   ├── booking.php             # Pemesanan tiket
│   ├── payment.php             # Halaman pembayaran
│   └── e-ticket.php            # Halaman e-ticket
│
├── tools/                      # File utilitas/development
├── vendor/                     # Dependency Composer, tidak perlu diupload ke GitHub
├── composer.json
├── composer.lock
├── README_ENV.md
└── README_STRUKTUR.md
```

---

## Struktur Database

Database yang digunakan bernama:

```text
db_cinem4_1_
```

Tabel utama:

| Tabel | Fungsi |
|---|---|
| `users` | Menyimpan data akun user |
| `admins` | Menyimpan data akun admin dan role admin |
| `movies` | Menyimpan data film |
| `cinemas` | Menyimpan data bioskop |
| `schedules` | Menyimpan jadwal tayang film |
| `bookings` | Menyimpan data booking tiket |
| `booking_seats` | Menyimpan kursi yang dipilih dalam booking |
| `promotions` | Menyimpan data promo dan kode diskon |
| `payment_logs` | Menyimpan log status pembayaran dari Midtrans |

Relasi utama database:

- Satu user dapat memiliki banyak booking.
- Satu movie dapat memiliki banyak schedule.
- Satu cinema dapat memiliki banyak schedule.
- Satu schedule dapat memiliki banyak booking.
- Satu booking dapat memiliki banyak booking seats.
- Satu booking dapat memiliki banyak payment logs.

---

## Instalasi Project

### 1. Clone Repository

```bash
git clone https://github.com/username/nama-repository.git
cd nama-repository
```

Ganti `username/nama-repository` sesuai nama repository GitHub Anda.

### 2. Install Dependency Composer

```bash
composer install
```

Dependency utama yang digunakan adalah PHPMailer.

### 3. Buat Database

Buka phpMyAdmin atau MySQL client, lalu buat database:

```sql
CREATE DATABASE db_cinem4_1_;
```

Setelah itu import file SQL database yang sudah disediakan:

```text
db_cinem4_1_.sql
```

Nama file SQL dapat disesuaikan dengan file database yang ada di project Anda.

### 4. Buat File `.env`

Copy file `.env.example` menjadi `.env`:

```bash
cp .env.example .env
```

Jika belum ada `.env.example`, buat file tersebut di root project dengan isi seperti berikut:

```env
APP_NAME=CINEM4
APP_ENV=local

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=db_cinem4_1_
DB_USERNAME=root
DB_PASSWORD=

MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_SMTP_AUTH=true
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME=CINEM4

MIDTRANS_IS_PRODUCTION=false
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxxxxx
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxxxxx
```

> Jangan upload file `.env` asli ke GitHub karena berisi data sensitif seperti password database, email, dan key Midtrans.

### 5. Jalankan Project

Jika menggunakan XAMPP, letakkan folder project di dalam folder `htdocs`, lalu akses:

```text
http://localhost/cinem4/public/
```

Halaman admin dapat diakses melalui:

```text
http://localhost/cinem4/public/admin/
```

Agar lebih rapi, document root web server sebaiknya diarahkan langsung ke folder `public/`.

---

## Konfigurasi Midtrans

Project ini menggunakan Midtrans Snap untuk proses pembayaran.

File konfigurasi Midtrans berada di:

```text
app/config/midtrans.php
```

Isi konfigurasi Midtrans melalui file `.env`:

```env
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxxxxx
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxxxxx
```

Untuk mode development, gunakan key dari Midtrans Sandbox.

Endpoint notifikasi pembayaran:

```text
public/actions/midtrans_notification.php
```

Saat project sudah online, URL tersebut dapat dipasang sebagai Payment Notification URL di dashboard Midtrans.

---

## Alur Sistem

1. User membuka website CINEM4.
2. User melakukan registrasi atau login.
3. User melihat daftar film.
4. User memilih film, bioskop, dan jadwal tayang.
5. Sistem menampilkan layout kursi.
6. User memilih kursi yang tersedia.
7. User dapat memasukkan kode promo.
8. Sistem menghitung subtotal, diskon, dan total pembayaran.
9. User melakukan konfirmasi booking.
10. Sistem membuat kode booking dan menyimpan data booking.
11. Status awal booking adalah `payment_status = pending` dan `booking_status = active`.
12. User melakukan pembayaran melalui Midtrans.
13. Sistem menerima status pembayaran.
14. Jika pembayaran berhasil, status berubah menjadi `paid` dan `completed`.
15. Sistem menerbitkan e-ticket digital.

---

## Status Pembayaran

Status pembayaran yang digunakan pada sistem:

| Status | Keterangan |
|---|---|
| `pending` | Menunggu pembayaran |
| `paid` | Pembayaran berhasil |
| `failed` | Pembayaran gagal |
| `expired` | Waktu pembayaran habis |
| `cancelled` | Booking atau pembayaran dibatalkan |

Status booking yang digunakan:

| Status | Keterangan |
|---|---|
| `active` | Booking masih aktif dan menunggu pembayaran |
| `completed` | Booking selesai setelah pembayaran berhasil |
| `cancelled` | Booking dibatalkan atau gagal |

---

## Keamanan Project

Beberapa penerapan keamanan pada project:

- Password user dan admin disimpan dalam bentuk hash.
- Konfigurasi sensitif dipindahkan ke file `.env`.
- Login user menggunakan captcha.
- Verifikasi akun menggunakan kode OTP email.
- Pemilihan kursi dicegah agar tidak terjadi double booking.
- File `.env` tidak disarankan untuk diupload ke repository publik.

---

## File yang Tidak Perlu Diupload ke GitHub

Pastikan `.gitignore` berisi:

```gitignore
# Local secrets / environment
.env
.env.*
!.env.example

# Composer dependencies
/vendor/

# OS / editor files
.DS_Store
Thumbs.db
.vscode/
.idea/

# Logs and temporary files
*.log
/tmp/
```

File yang tidak perlu diupload:

- `.env`
- `vendor/`
- `.git/` lama dari folder ZIP
- file log atau cache

File yang sebaiknya tetap diupload:

- `README.md`
- `README_ENV.md`
- `README_STRUKTUR.md`
- `.env.example`
- `composer.json`
- `composer.lock`
- file SQL database contoh

---

## Catatan Development

Jika pembayaran Midtrans tidak berjalan di localhost, kemungkinan webhook Midtrans belum bisa mengirim notifikasi ke server lokal. Untuk development, sistem menyediakan fitur sinkronisasi status pembayaran secara manual melalui file action yang tersedia di folder:

```text
public/actions/
```

Pastikan juga ekstensi PHP berikut aktif:

- mysqli
- curl
- mbstring
- openssl

---

## Pengembang

Project ini dibuat untuk kebutuhan tugas/proyek aplikasi pemesanan tiket bioskop berbasis web.

Anggota kelompok:

- Noval Adrian
- Maya Trisnawati
- M. Khafidz Fahmi
- Ahmad Ali Murtadlo

---
## Video Demo
Detail web dapat dilihat pada video demo berikut: https://youtu.be/yS2l6LNQVBg

---

## Lisensi

Project ini digunakan untuk kebutuhan pembelajaran dan pengembangan aplikasi web.
