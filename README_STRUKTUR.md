# CINEM4 - Struktur Folder Dirapikan

Project ini sudah dirapikan menjadi struktur yang lebih standar untuk PHP native.

## Struktur utama

```text
cinema_3_rapi/
├── app/
│   ├── config/                 # konfigurasi database
│   ├── data/                   # data dummy/static
│   └── views/
│       ├── components/         # komponen reusable
│       └── partials/public/    # head, navbar, footer publik
├── public/
│   ├── actions/                # handler form: login, register, reset password
│   ├── admin/                  # halaman admin panel
│   ├── assets/                 # css, gambar, ui
│   ├── index.php
│   ├── movies.php
│   ├── movie-detail.php
│   ├── booking.php
│   ├── payment.php
│   └── ...
├── tools/                      # file utilitas/dev, misalnya test_email.php
├── vendor/                     # dependency Composer / PHPMailer
├── composer.json
└── composer.lock
```

## Cara menjalankan

Paling rapi: arahkan document root web server ke folder `public/`.

Jika memakai XAMPP tanpa virtual host, buka:

```text
http://localhost/cinema_3_rapi/public/
```

Admin panel:

```text
http://localhost/cinema_3_rapi/public/admin/
```

## Perubahan path penting

- `config/koneksi.php` dipindah ke `app/config/koneksi.php`.
- `partials/` publik dipindah ke `app/views/partials/public/`.
- Komponen carousel dipindah ke `app/views/components/poster_carousel.php`.
- Action form dipindah ke `public/actions/`.
- `assets/` tetap berada di area public agar path database lama seperti `assets/img/...` tetap aman.
- Link navbar `profile.php` diganti ke `dashboard.php` karena file `profile.php` tidak ada di project asli.
- Ditambahkan `public/payment.php` supaya tombol `Lanjut Bayar` dari halaman booking tidak mengarah ke file yang hilang.

## Catatan

Folder `.git` dari ZIP asli tidak disertakan dalam hasil akhir agar ukuran lebih ringan dan struktur bersih.
