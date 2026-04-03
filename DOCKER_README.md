# Docker Setup - TopUp Games

## Quick Start

```bash
# 1. Build dan start containers
docker-compose up -d --build

# 2. Tunggu hingga MySQL ready
docker-compose logs -f db

# 3. Cek status containers
docker-compose ps

# 4. Buka browser
http://localhost:8080
```

## Commands

### Start Services
```bash
docker-compose up -d
```

### Stop Services
```bash
docker-compose down
```

### Stop & Remove Volumes
```bash
docker-compose down -v
```

### Rebuild
```bash
docker-compose down
docker-compose up -d --build --no-cache
```

### View Logs
```bash
# All logs
docker-compose logs -f

# App only
docker-compose logs -f app

# Database only
docker-compose logs -f db
```

### Access Container
```bash
# PHP container
docker-compose exec app bash

# MySQL container
docker-compose exec db mysql -u docker -p docker_password topup_games
```

### Check Health
```bash
docker-compose ps
```

## Ports

| Service | Internal | External |
|---------|----------|----------|
| App     | 80       | 8080     |
| MySQL   | 3306     | 3307     |

## Database

- **Host:** localhost:3307 (external) atau `db:3306` (internal container)
- **Database:** topup_games
- **Username:** docker
- **Password:** docker_password
- **Root Password:** root_password

## Environment

Untuk development, copy `.env.docker` ke `.env`:

```bash
cp .env.docker .env
```

## Troubleshooting

### MySQL tidak mau start
```bash
docker-compose down -v
docker-compose up -d --build
```

### Permission denied
```bash
docker-compose exec app chown -R www-data:www-data /var/www/html/writable
```

### Clear cache
```bash
docker-compose exec app php spark cache:clear
```

### Run migrations
```bash
docker-compose exec app php spark migrate
```

### Check PHP errors
```bash
docker-compose logs app --tail=100
```

## Production Ready

Untuk production, ubah:
1. `CI_ENVIRONMENT=production`
2. Generate new encryption key
3. Update database credentials
4. Setup SSL certificate
