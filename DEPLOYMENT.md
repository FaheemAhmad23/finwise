# FinWise — Docker Deployment Guide
### Self-Hosted on Server IP · MySQL Database

---

## What You'll Have Running

```
Your Browser
     │  http://168.144.35.138:3000
     ▼
┌──────────────────┐     ┌──────────────────┐
│  finwise_app     │────▶│  finwise_db      │
│  Next.js         │     │  MySQL 8         │
│  Port: 3000      │     │  Port: 3306      │
└──────────────────┘     │  (internal only) │
                         └──────────────────┘
     Both run inside Docker on your server.
     MySQL data is saved to a Docker volume (survives restarts).
```

---

## Step 1 — SSH into Your Server

```bash
ssh root@168.144.35.138
```
*(or `ssh user@168.144.35.138` if you have a non-root user)*

---

## Step 2 — Install Docker

```bash
# Download and run the official Docker install script
curl -fsSL https://get.docker.com | sh

# Verify it works
docker --version
docker compose version
```

Expected output: `Docker version 24.x.x` and `Docker Compose version v2.x.x`

---

## Step 3 — Get the Code

```bash
git clone https://github.com/TheMalikFaheem/finwise.git
cd finwise
```

---

## Step 4 — Create the `.env` File

This file holds all your secrets. **Never share or commit this file.**

```bash
nano .env
```

Copy-paste exactly this, then change the passwords and secret:

```bash
# MySQL passwords — choose anything strong
DB_PASSWORD=Finwise@2026!
DB_ROOT_PASSWORD=RootFinwise@2026!

# NextAuth secret — generate a random one with the command below
NEXTAUTH_SECRET=REPLACE_THIS_WITH_GENERATED_SECRET

# Your server IP (already filled in for you)
NEXTAUTH_URL=http://168.144.35.138:3000

# Google login — leave blank for now
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

**To generate NEXTAUTH_SECRET, run this in another terminal:**
```bash
openssl rand -base64 32
```
Copy the output and paste it as the value for `NEXTAUTH_SECRET`.

**Save the file:** press `Ctrl+X`, then `Y`, then `Enter`

---

## Step 5 — Open the Firewall Port

```bash
# Allow SSH (always keep this open!)
sudo ufw allow 22

# Allow the app port
sudo ufw allow 3000

# Enable firewall
sudo ufw enable

# Confirm
sudo ufw status
```

You should see `3000` listed as `ALLOW`.

---

## Step 6 — Build and Launch

```bash
# Build the Docker image and start MySQL + the app
# First launch takes 3-5 minutes (downloads images, builds app)
docker compose up -d --build

# Watch the logs to see when it's ready
docker compose logs -f app
```

**What you'll see in the logs:**
```
finwise_db   | MySQL init process done. Ready for start up.
finwise_app  | ✓ Prisma migrations applied
finwise_app  | ▲ Next.js 16.x.x
finwise_app  | ✓ Ready in 3s
```

Once you see `Ready`, your app is live!

---

## Step 7 — Open in Your Browser

Go to: **http://168.144.35.138:3000**

You'll see the FinWise login page. Click **"Create one free"** to register your account.

---

## Daily Management Commands

```bash
# Check if containers are running
docker compose ps

# View live app logs
docker compose logs -f app

# Stop the app
docker compose down

# Start it again
docker compose up -d

# Update to latest code
git pull && docker compose up -d --build app
```

---

## Backup Your Data

```bash
# Create a backup of the MySQL database
docker compose exec db mysqldump -u finwise_user -p"${DB_PASSWORD}" finwise_db > backup-$(date +%Y-%m-%d).sql

# To restore from backup
cat backup-2026-05-14.sql | docker compose exec -T db mysql -u finwise_user -p"${DB_PASSWORD}" finwise_db
```

---

## Troubleshooting

| Problem | What to check |
|---|---|
| Browser shows "can't connect" | Run `sudo ufw status` — port 3000 must show ALLOW |
| App logs show DB connection error | MySQL takes 30-60s on first launch — wait and retry |
| `NEXTAUTH_URL` mismatch error | Must be exactly `http://168.144.35.138:3000` — no trailing slash |
| App crashes on start | Run `docker compose logs app` — read the error message |
| Forgot DB password | It's in your `.env` file: `cat .env` |

---

## When You Get a Domain Later

1. Point the domain's DNS A record → `168.144.35.138`
2. Update `.env`:
   ```
   NEXTAUTH_URL=https://yourdomain.com
   ```
3. Install Nginx + free SSL:
   ```bash
   sudo apt install -y nginx certbot python3-certbot-nginx
   sudo certbot --nginx -d yourdomain.com
   ```
4. Close direct port 3000 access:
   ```bash
   sudo ufw delete allow 3000
   sudo ufw allow 'Nginx Full'
   ```
