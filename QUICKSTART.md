# KTB360 - Quick Reference

## Local Development
```bash
# Start
docker compose up -d

# Stop
docker compose down

# View logs
docker compose logs -f

# Access
- App: http://localhost
- PHPMyAdmin: http://localhost:8080
- MySQL: localhost:3306
```

## Production Deployment (VPS)

### Initial Setup
```bash
# 1. SSH to VPS
ssh root@your-vps-ip

# 2. Install Docker
curl -fsSL https://get.docker.com | sh

# 3. Clone project
git clone <repo-url>
cd ktb-evaluation

# 4. Deploy
docker compose -f docker-compose.prod.yml up -d
```

### Configuration
- Update domain in `docker/nginx/default.conf`
- Setup SSL with Let's Encrypt
- Configure environment variables in `.env`

### Monitoring
```bash
docker compose ps              # Status
docker compose logs -f nginx   # Logs
docker stats                   # Resource usage
```

### Database Backup
```bash
docker exec ktb_mysql mysqldump -u ktb_user -p ktb_evaluation > backup.sql
```

## Key Differences: Dev vs Prod

| Aspect | Dev | Prod |
|--------|-----|------|
| Compose file | docker-compose.yml | docker-compose.prod.yml |
| Volumes | Read-write | Read-only (ro) |
| Ports | All exposed | MySQL/PHPMyAdmin: localhost only |
| Restart | No | unless-stopped |
| Healthchecks | No | Yes |

See `DEPLOYMENT.md` for detailed guide.
