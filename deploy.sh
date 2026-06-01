#!/bin/bash
set -e

echo "=== KTB360 Docker Deployment Script ==="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Step 1: Check Docker
echo -e "${YELLOW}[1/4] Checking Docker installation...${NC}"
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Docker not found. Install Docker first: https://docs.docker.com/engine/install/${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Docker installed${NC}"

# Step 2: Check Docker Compose
echo -e "${YELLOW}[2/4] Checking Docker Compose installation...${NC}"
if ! command -v docker compose &> /dev/null; then
    echo -e "${RED}Docker Compose not found. Install Docker Compose first.${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Docker Compose installed${NC}"

# Step 3: Pull latest images
echo -e "${YELLOW}[3/4] Building images...${NC}"
docker compose build --no-cache

# Step 4: Start services
echo -e "${YELLOW}[4/4] Starting services...${NC}"
docker compose up -d

# Wait for services to be ready
echo -e "${YELLOW}Waiting for services to start...${NC}"
sleep 5

# Check status
echo ""
echo -e "${GREEN}=== Deployment Complete ===${NC}"
echo ""
docker compose ps
echo ""
echo -e "${GREEN}Services running:${NC}"
echo "  • App: http://your-domain.com"
echo "  • PHPMyAdmin: http://your-domain.com:8080"
echo "  • MySQL: localhost:3306"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "  1. Update nginx config with your domain (docker/nginx/default.conf)"
echo "  2. Setup SSL certificate (Let's Encrypt)"
echo "  3. Configure firewall"
