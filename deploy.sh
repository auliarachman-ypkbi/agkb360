#!/bin/bash
git add .
git commit -m "Update aplikasi"
git push origin main
echo "Waiting for GitHub..."
sleep 2
ssh root@145.79.10.123 "cd /root/agkb360 && git pull origin main --rebase && docker compose -f docker-compose.prod.yml restart"
echo "Deployed!"
