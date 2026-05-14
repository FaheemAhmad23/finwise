# FinWise — Docker Deployment Guide
### Self-Hosted on Your Own Server

---

## Prerequisites

Your server needs:
- **Ubuntu 20.04+** (or any Linux distro)
- **Docker 24+** and **Docker Compose v2**
- **2 GB RAM minimum** (4 GB recommended)
- A domain name pointed at your server IP (for HTTPS)

---

## Step 1 — Install Docker on Your Server

```bash
# SSH into your server
ssh user@your-server-ip

# Install Docker
curl -fsSL https://get.docker.com | sh

# Add your user to the docker group (no sudo needed)
sudo usermod -aG docker $USER
newgrp docker

# Verify
docker --version
docker compose version
```

---

## Step 2 — Upload Your Code to the Server

**Option A — Git (recommended)**
```bash
# On your server
git clone https://github.com/yourusername/finwise.git
cd finwise
```

**Option B — SCP from your Mac**
```bash
# On your Mac, from the project directory
scp -r /Users/malikfaheem/Documents/debt-manager-app user@your-server-ip:/home/user/finwise
```

---

## Step 3 — Create the `.env` File

On your server, inside the project directory:

```bash
cd /home/user/finwise
nano .env
```

Paste and fill in all values:

```bash
# ── Database ──────────────────────────────────────────
DB_PASSWORD=choose_a_very_strong_password_here

# ── NextAuth ──────────────────────────────────────────
# Generate: openssl rand -base64 32
NEXTAUTH_SECRET=paste_your_generated_secret_here
NEXTAUTH_URL=https://yourdomain.com

# ── Google OAuth (optional) ───────────────────────────
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

> **Generate NEXTAUTH_SECRET:**
> ```bash
> openssl rand -base64 32
> ```

---

## Step 4 — Build and Launch

```bash
# Build the Docker image and start both services
docker compose up -d --build

# Watch logs to confirm it started correctly
docker compose logs -f app
```

You should see:
```
finwise_app  | ✓ Prisma migrations applied
finwise_app  | ▲ Next.js 16.x.x
finwise_app  | - Local: http://localhost:3000
finwise_app  | ✓ Ready in Xs
```

**Test it works:**
```bash
curl http://localhost:3000
```

---

## Step 5 — Set Up Nginx as Reverse Proxy (with HTTPS)

### Install Nginx + Certbot

```bash
sudo apt update
sudo apt install -y nginx certbot python3-certbot-nginx
```

### Create Nginx Config

```bash
sudo nano /etc/nginx/sites-available/finwise
```

Paste this (replace `yourdomain.com`):

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;

    # Redirect all HTTP to HTTPS
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    # SSL — managed by Certbot (filled in automatically)
    ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    include             /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam         /etc/letsencrypt/ssl-dhparams.pem;

    # Security headers
    add_header X-Frame-Options       "SAMEORIGIN"   always;
    add_header X-Content-Type-Options "nosniff"     always;
    add_header X-XSS-Protection      "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000" always;

    # Proxy to Docker container
    location / {
        proxy_pass         http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade    $http_upgrade;
        proxy_set_header   Connection 'upgrade';
        proxy_set_header   Host       $host;
        proxy_set_header   X-Real-IP  $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
        proxy_read_timeout 60s;
    }
}
```

### Enable the Config + Get SSL Certificate

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/finwise /etc/nginx/sites-enabled/

# Test config
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx

# Get free SSL certificate from Let's Encrypt
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Verify auto-renewal works
sudo certbot renew --dry-run
```

Your app is now live at `https://yourdomain.com` 🎉

---

## Step 6 — Firewall Setup

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

> Do **NOT** expose port 3000 or 5432 publicly. Nginx handles all external traffic.

---

## Useful Docker Commands

```bash
# View running containers
docker compose ps

# View app logs
docker compose logs -f app

# View database logs
docker compose logs -f db

# Restart the app (after code changes)
docker compose up -d --build app

# Stop everything
docker compose down

# Stop and delete database data (⚠️ DESTRUCTIVE)
docker compose down -v

# Open a shell inside the app container
docker compose exec app sh

# Run Prisma Studio (DB GUI) — only locally, not in production
docker compose exec app npx prisma studio
```

---

## Updating the App

When you push new code:

```bash
# On your server
cd /home/user/finwise
git pull

# Rebuild and restart only the app (DB stays running)
docker compose up -d --build app

# Check it started correctly
docker compose logs -f app
```

Database migrations run automatically on container start via:
```
CMD ["sh", "-c", "npx prisma migrate deploy && node server.js"]
```

---

## Backup the Database

Set up automated daily backups:

```bash
# Create backup script
sudo nano /home/user/backup-finwise.sh
```

```bash
#!/bin/bash
DATE=$(date +%Y-%m-%d)
BACKUP_DIR="/home/user/backups/finwise"
mkdir -p "$BACKUP_DIR"

docker compose -f /home/user/finwise/docker-compose.yml exec -T db \
  pg_dump -U finwise_user finwise_db > "$BACKUP_DIR/finwise-$DATE.sql"

# Keep only last 30 days
find "$BACKUP_DIR" -name "*.sql" -mtime +30 -delete

echo "Backup completed: finwise-$DATE.sql"
```

```bash
chmod +x /home/user/backup-finwise.sh

# Schedule daily at 2 AM
crontab -e
# Add this line:
0 2 * * * /home/user/backup-finwise.sh >> /var/log/finwise-backup.log 2>&1
```

**Restore from backup:**
```bash
cat /home/user/backups/finwise/finwise-2026-05-14.sql | \
  docker compose exec -T db psql -U finwise_user finwise_db
```

---

## Monitoring (Optional)

```bash
# Install htop for resource monitoring
sudo apt install -y htop

# Watch Docker container resource usage
docker stats

# Check disk usage
df -h
du -sh /var/lib/docker/volumes/
```

---

## Troubleshooting

| Problem | Fix |
|---|---|
| App not starting | `docker compose logs app` to see error |
| Database connection refused | Check `DB_PASSWORD` matches in `.env` |
| 502 Bad Gateway in Nginx | App container may be starting; wait 10s |
| `NEXTAUTH_URL` mismatch error | Must exactly match your domain with `https://` |
| Prisma migration fails | `docker compose exec app npx prisma migrate status` |
| Out of disk space | `docker system prune -af` to clear unused images |
| SSL certificate expired | `sudo certbot renew` (auto-renewal should handle this) |

---

## Architecture Diagram

```
Internet
    │  HTTPS :443
    ▼
┌─────────────────────┐
│  Nginx (reverse     │
│  proxy + SSL)       │
└────────┬────────────┘
         │ http://127.0.0.1:3000
         ▼
┌─────────────────────┐     ┌─────────────────────┐
│  finwise_app        │────▶│  finwise_db          │
│  (Next.js Docker)   │     │  (PostgreSQL Docker) │
│  Port: 3000         │     │  Port: 5432          │
└─────────────────────┘     └─────────────────────┘
         │
    Docker Volume: postgres_data (persistent)
```
