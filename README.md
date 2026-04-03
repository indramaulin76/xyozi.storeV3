# XyoziStore - Topup Games PG Sakurupiah

Platform top-up games dengan CodeIgniter 4.

## Requirements

- PHP 8.1+
- MySQL 8.0+
- Composer

## Installation

1. Clone repository:
```bash
git clone https://github.com/username/xyozistore.git
cd xyozistore
```

2. Install dependencies:
```bash
composer install
```

3. Setup environment:
```bash
cp .env .env
# Edit .env sesuai konfigurasi server Anda
```

4. Import database:
```bash
mysql -u root -p topup_games < DATABASENYA.sql
```

5. Setup folder permissions:
```bash
writable/cache/
writable/logs/
writable/session/
writable/uploads/
```

6. Jalankan server:
```bash
php spark serve
```

## Konfigurasi Server

- **Domain:** xyozistore.my.id
- **Environment:** production
- **PHP:** 8.1+

## Akun Admin

- Username: admin123
- Password: (sesuaikan dengan yang Anda buat)

## Troubleshooting

### Error CSP saat development
Jika layout rusak, bisa matikan sementara CSP di `app/Config/App.php`:
```php
public bool $CSPEnabled = false;
```

### Error SQL IN()
Pastikan array tidak kosong sebelum menggunakan `whereIn()`

## Lisensi

MIT