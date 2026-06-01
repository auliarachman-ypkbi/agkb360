#!/bin/bash
set -e

echo "Adding all changes..."
git add .

echo "Committing..."
git commit -m "Update aplikasi" || true

echo "Pulling latest from GitHub..."
git pull origin main --rebase

echo "Pushing to GitHub..."
git push origin main

echo "Waiting for GitHub sync..."
sleep 2

echo "Syncing to VPS..."
ssh root@145.79.10.123 "cd /root/agkb360 && git pull origin main --rebase && docker compose -f docker-compose.prod.yml restart"

echo "✓ Deployed!"
