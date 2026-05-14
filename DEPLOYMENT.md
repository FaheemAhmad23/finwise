# FinWise — Docker Deployment Guide
### Self-Hosted on Server IP (No Domain Required)

---

## Prerequisites

Your server needs:
- **Ubuntu 20.04+** (or any Linux distro)
- **Docker 24+** and **Docker Compose v2**
- **2 GB RAM minimum** (4 GB recommended)
- Your server's public IP address

---

## Step 1 — Install Docker on Your Server

```bash
# SSH into your server
ssh user@YOUR_SERVER_IP

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

## Step 2 — Get the Code onto Your Server

**Option A — Clone from GitHub (recommended)**
```bash
git clone https://github.com/TheMalikFaheem/finwise.git
cd finwise
```

**Option B — SCP from your Mac**
```bash
# Run this on your Mac
scp -r /Users/malikfaheem/Documents/debt-manager-app user@YOUR_SERVER_IP:/home/user/finwise
```

---

## Step 3 — Create the `.env` File

On your server inside the project folder:

```bash
cd finwise
nano .env
```

Paste and fill in your values:

```bash
# ── Database ──────────────────────────────────────────
DB_PASSWORD=choose_a_strong_password_here

# ── NextAuth ──────────────────────────────────────────
# Generate a secret:  openssl rand -base64 32
NEXTAUTH_SECRET=paste_your_generated_secret_here

# ⚠️  No domain yet — use your server's IP and port
NEXTAUTH_URL=http://YOUR_SERVER_IP:3000

# ── Google OAuth (leave blank for now) ───────────────
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

> **Generate NEXTAUTH_SECRET on your server:**
> ```bash
> openssl rand -base64 32
> ```

> **Find your server IP:**
> ```bash
> curl -4 ifconfig.me
> ```

---

## Step 4 — Open Port 3000 on the Firewall

```bash
sudo ufw allow 22      # SSH (keep this!)
sudo ufw allow 3000    # FinWise app
sudo ufw enable
sudo ufw status
```

---

## Step 5 — Build and Launch

```bash
# Inside the finwise directory
docker compose up -d --build

# Watch logs to confirm it started
docker compose logs -f app
```

You should see:
```
finwise_app  | ✓ Prisma migrations applied
finwise_app  | ▲ Next.js 16.x.x
finwise_app  | - Local: http://localhost:3000
finwise_app  | ✓ Ready in Xs
```

**Your app is now live at:**
```
http://YOUR_SERVER_IP:3000
```

Open that in your browser and you'll see the FinWise login page. ✅

---

## Useful Docker Commands

```bash
# View running containers
docker compose ps

# View app logs (live)
docker compose logs -f app

# View database logs
docker compose logs -f db

# Restart the app (after code updates)
docker compose up -d --build app

# Stop everything
docker compose down

# Open a shell inside the app
docker compose exec app sh
```

---

## Updating the App

When new code is pushed to GitHub:

```bash
cd finwise
git pull
docker compose up -d --build app
docker compose logs -f app
```

Database migrations run automatically on every restart.

---

## Backup the Database

```bash
# Manual backup
docker compose exec -T db pg_dump -U finwise_user finwise_db > backup-$(date +%Y-%m-%d).sql

# Restore from backup
cat backup-2026-05-14.sql | docker compose exec -T db psql -U finwise_user finwise_db
```

---

## When You Get a Domain Later

1. Point the domain's A record to your server IP
2. Update `.env`:
   ```bash
   NEXTAUTH_URL=https://yourdomain.com
   ```
3. Install Nginx + Certbot for SSL:
   ```bash
   sudo apt install -y nginx certbot python3-certbot-nginx
   sudo certbot --nginx -d yourdomain.com
   ```
4. Add Nginx config (see below) and close port 3000:
   ```bash
   sudo ufw delete allow 3000
   sudo ufw allow 'Nginx Full'
   ```

**Nginx config for future use:**
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    include             /etc/letsencrypt/options-ssl-nginx.conf;

    location / {
        proxy_pass         http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade    $http_upgrade;
        proxy_set_header   Connection 'upgrade';
        proxy_set_header   Host       $host;
        proxy_set_header   X-Real-IP  $remote_addr;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}
```

---

## Troubleshooting

| Problem | Fix |
|---|---|
| Can't open `http://IP:3000` | Check firewall: `sudo ufw status` → port 3000 must show ALLOW |
| App not starting | `docker compose logs app` to see the error |
| Database connection refused | Check `DB_PASSWORD` is the same in `.env` for both services |
| `NEXTAUTH_URL` mismatch | Must be `http://YOUR_SERVER_IP:3000` exactly — no trailing slash |
| Prisma migration fails | `docker compose exec app npx prisma migrate status` |
| Out of disk space | `docker system prune -af` to clear unused images |

---

## Architecture (IP-only setup)

```
Browser
    │  http://YOUR_SERVER_IP:3000
    ▼
┌─────────────────────┐     ┌─────────────────────┐
│  finwise_app        │────▶│  finwise_db          │
│  (Next.js Docker)   │     │  (PostgreSQL Docker) │
│  Port: 3000 (open)  │     │  Port: 5432 (internal│
└─────────────────────┘     │  only, not exposed)  │
                            └─────────────────────┘
         Docker Volume: postgres_data (persistent)
```
