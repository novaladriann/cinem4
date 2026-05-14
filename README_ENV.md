# Konfigurasi `.env` CINEM4

Patch ini memindahkan konfigurasi sensitif dari kode ke file `.env`, terutama:

- SMTP email
- Midtrans Client Key
- Midtrans Server Key
- Database connection

## Cara pakai

1. Copy file `.env.example` menjadi `.env` di root project.
2. Isi nilai SMTP dan Midtrans sesuai akun Anda.
3. Pastikan `.env` tidak di-commit ke GitHub.
4. Jika secret lama sudah pernah masuk GitHub, regenerate/rotate secret tersebut di Gmail dan Midtrans.

## File penting

- `app/config/env.php` membaca file `.env`.
- `app/config/mail.php` menyatukan konfigurasi PHPMailer.
- `app/config/midtrans.php` membaca Midtrans key dari `.env`.
- `app/config/koneksi.php` membaca database config dari `.env`, tetapi tetap punya default lokal.

## Contoh Gmail

Untuk Gmail, gunakan **App Password**, bukan password akun utama.

```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME=CINEM4
```

## Contoh Midtrans Sandbox

```env
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxxxxx
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxxxxx
```
