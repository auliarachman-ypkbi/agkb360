# KTB360 Evaluation - Docker Deployment Guide

## Prerequisites
- VPS atau dedicated server dengan Linux (Ubuntu 20.04+)
- Min. 2GB RAM, 20GB storage
- SSH access ke server
- Domain (optional, bisa guna IP)

## Step 1: Setup VPS

SSH ke server Anda:
```bash
ssh root@your-vps-ip
```

### Install Docker & Docker Compose
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Add user ke docker group (opsional, biar tidak perlu sudo)
sudo usermod -aG docker $USER
newgrp docker

# Verify
docker --version
docker compose --version
```

## Step 2: Clone & Setup Project

```bash
# Clone repository (ganti URL)
git clone https://github.com/username/ktb-evaluation.git
cd ktb-evaluation

# Copy environment file (opsional, untuk production)
cp .env.example .env
# Edit .env dengan credentials production Anda
```

## Step 3: Deploy dengan Docker Compose

### Opsi A: Development (localhost)
```bash
docker compose up -d
# Akses: http://localhost
# PHPMyAdmin: http://localhost:8080
```

### Opsi B: Production (di VPS)
```bash
# Gunakan production compose file
docker compose -f docker-compose.prod.yml up -d

# Verify
docker compose ps
```

## Step 4: Setup Domain & SSL

### Update Nginx Config
Edit `docker/nginx/default.conf`:
```nginx
server {
    listen 80;
    listen 443 ssl;
    server_name your-domain.com www.your-domain.com;  # ← Ganti domain
    
    # SSL certificates (jika sudah ada)
    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;
    
    # ... rest of config
}
```

### Setup Let's Encrypt SSL (Recommended)
```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx -y

# Generate certificate
sudo certbot certonly --standalone -d your-domain.com

# Copy ke docker folder
sudo cp /etc/letsencrypt/live/your-domain.com/fullchain.pem docker/nginx/ssl/cert.pem
sudo cp /etc/letsencrypt/live/your-domain.com/privkey.pem docker/nginx/ssl/key.pem

# Restart nginx container
docker compose restart nginx
```

## Step 5: Firewall & Security

```bash
# Allow SSH, HTTP, HTTPS
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

## Management Commands

```bash
# View logs
docker compose logs -f nginx
docker compose logs -f php
docker compose logs -f mysql

# Stop all services
docker compose down

# Restart services
docker compose restart

# Backup database
docker exec ktb_mysql mysqldump -u ktb_user -p ktb_evaluation > backup.sql

# View container stats
docker stats
```

## Monitoring & Updates

```bash
# Check health
docker compose ps

# Update images
docker compose pull
docker compose up -d

# Cleanup unused images
docker image prune -a
```

## Troubleshooting

**Container keeps restarting?**
```bash
docker compose logs mysql  # Check MySQL logs
docker compose logs php    # Check PHP logs
```

**Port already in use?**
```bash
# Change port in docker-compose.prod.yml
# Atau kill process
sudo lsof -i :80
```

**Database connection error?**
- Verify credentials di environment variables
- Check MySQL container is running: `docker compose ps`
- Test connection: `docker exec ktb_mysql mysql -u ktb_user -p ktb_evaluation`

## Support
- Docker Docs: https://docs.docker.com/
- Server: IDCloudHost atau Hostinger (cek apakah support Docker)
