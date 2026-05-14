# FinWise — CI/CD Setup Guide
### Automated Deploy on Every Git Push

---

## How It Works

```
You push code to GitHub (main branch)
         │
         ▼
┌─────────────────────────────────┐
│   GitHub Actions (cloud)        │
│                                 │
│  1. Builds Docker image         │
│  2. Pushes to ghcr.io registry  │
│  3. SSHs into your server       │
│  4. Pulls new image             │
│  5. Restarts app container      │
└─────────────────────────────────┘
         │
         ▼
  App updated on your server
  (database stays running)
```

Total time from push to live: **~3-5 minutes**

---

## One-Time Setup (do this once)

### Step 1 — Generate an SSH key pair for GitHub Actions

Run this on your **Mac** (not the server):

```bash
ssh-keygen -t ed25519 -C "github-actions-finwise" -f ~/.ssh/finwise_deploy
```

This creates two files:
- `~/.ssh/finwise_deploy` → **private key** (goes into GitHub secrets)
- `~/.ssh/finwise_deploy.pub` → **public key** (goes onto the server)

### Step 2 — Add the public key to your server

```bash
# Copy the public key to your server
ssh-copy-id -i ~/.ssh/finwise_deploy.pub root@168.144.35.138
```

Or manually:
```bash
# Print the public key
cat ~/.ssh/finwise_deploy.pub

# On your server, add it to authorized_keys
echo "PASTE_PUBLIC_KEY_HERE" >> /root/.ssh/authorized_keys
chmod 600 /root/.ssh/authorized_keys
```

### Step 3 — Create a GitHub Personal Access Token (GHCR_TOKEN)

This lets your server pull the Docker image from GitHub's registry.

1. Go to **GitHub → Settings → Developer Settings → Personal access tokens → Tokens (classic)**
2. Click **Generate new token (classic)**
3. Give it a name: `finwise-server-pull`
4. Select scope: ✅ **`read:packages`** only
5. Click **Generate token**
6. **Copy the token immediately** (you won't see it again)

### Step 4 — Add secrets to GitHub

Go to your repo: **Settings → Secrets and variables → Actions → New repository secret**

Add these 5 secrets:

| Secret Name | Value |
|---|---|
| `SSH_HOST` | `168.144.35.138` |
| `SSH_USERNAME` | `root` |
| `SSH_PRIVATE_KEY` | Contents of `~/.ssh/finwise_deploy` (the private key file) |
| `SSH_PORT` | `22` |
| `GHCR_TOKEN` | The token you generated in Step 3 |

**To get the private key contents:**
```bash
cat ~/.ssh/finwise_deploy
```
Copy everything including `-----BEGIN OPENSSH PRIVATE KEY-----` and `-----END OPENSSH PRIVATE KEY-----`.

### Step 5 — Make the GitHub package public (optional but simpler)

Instead of managing a GHCR_TOKEN, you can make the Docker image public:

1. After first deploy, go to **GitHub → Your Profile → Packages → finwise**
2. Click **Package settings**
3. Scroll to **Danger Zone → Change visibility**
4. Set to **Public**

If public, remove the login step from the deploy workflow and skip the GHCR_TOKEN secret.

---

## Deploying Changes (Every Day Usage)

Once set up, deploying is just:

```bash
# Make your changes locally
git add .
git commit -m "feat: your change description"
git push
```

That's it. GitHub Actions handles the rest automatically.

**Watch the deployment:**
Go to your repo → **Actions** tab → click the latest workflow run

---

## Manual Deploy (without pushing code)

You can trigger a deploy manually from GitHub:

1. Go to **Actions** tab in your repo
2. Click **🚀 Build & Deploy FinWise**
3. Click **Run workflow** → **Run workflow**

---

## Rollback to Previous Version

If something breaks after a deploy:

```bash
# On your server — rollback to the previous image
docker compose down
docker pull ghcr.io/themalikfaheem/finwise:sha-PREVIOUS_COMMIT_SHA
docker tag ghcr.io/themalikfaheem/finwise:sha-PREVIOUS_COMMIT_SHA ghcr.io/themalikfaheem/finwise:latest
docker compose up -d
```

Find the previous SHA in the **Packages** section of your GitHub repo.

---

## Troubleshooting CI/CD

| Problem | Fix |
|---|---|
| Workflow never runs | Check you pushed to `main` branch |
| Build fails | Check Actions tab for error → usually a code issue |
| SSH connection refused | Verify public key is in server's `authorized_keys` |
| `ghcr.io` pull fails | Check `GHCR_TOKEN` secret has `read:packages` scope |
| App doesn't update | Run `docker compose logs app` on server — migration may have failed |
| Old version still showing | Hard refresh browser (Ctrl+Shift+R) — may be cached |
