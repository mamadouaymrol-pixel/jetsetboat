# JetSetBoat — Developer Setup Guide (XAMPP + Astro on Windows)

## Architecture Overview

| Service | URL | Purpose |
|---------|-----|---------|
| Astro dev server | `http://localhost:4321` | Frontend (pages, styles, JS) |
| XAMPP Apache | `http://localhost:8080` | PHP backend (contact form, admin) |
| Shared file | `public/events.json` | Events data (read by both) |

The Astro dev server serves the static frontend AND `public/events.json`.
XAMPP serves PHP files (`contact.php`, `admin/`).
The contact form posts cross-origin to `http://localhost:8080/contact.php`; CORS is handled automatically.

---

## Prerequisites

- **Node.js v22+** — https://nodejs.org
- **XAMPP** — https://www.apachefriends.org (Apache + PHP 7.4+)
- **Git** (optional but recommended)

---

## Step 1 — Install & Configure XAMPP

### 1a. Change Apache port to 8080

1. Open **XAMPP Control Panel** → Apache → **Config** → `httpd.conf`
2. Find `Listen 80` and change it to `Listen 8080`
3. Find `ServerName localhost:80` and change it to `ServerName localhost:8080`
4. Save and close.

### 1b. Point DocumentRoot to this project's `public/` folder

1. In `httpd.conf`, find `DocumentRoot` and change it:
   ```apache
   DocumentRoot "C:/Users/Aymeric/jetsetboat/public"
   <Directory "C:/Users/Aymeric/jetsetboat/public">
       Options Indexes FollowSymLinks
       AllowOverride All
       Require all granted
   </Directory>
   ```
2. Also update the default `<Directory "C:/xampp/htdocs">` block — change the path to match above.
3. Save `httpd.conf`.

> **Tip:** Alternatively, add a Virtual Host in `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
> ```apache
> <VirtualHost *:8080>
>     DocumentRoot "C:/Users/Aymeric/jetsetboat/public"
>     ServerName localhost
>     <Directory "C:/Users/Aymeric/jetsetboat/public">
>         AllowOverride All
>         Require all granted
>     </Directory>
> </VirtualHost>
> ```
> Then enable vhosts in `httpd.conf` by uncommenting: `Include conf/extra/httpd-vhosts.conf`

### 1c. Enable mod_rewrite

In `httpd.conf`, make sure this line is uncommented:
```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

---

## Step 2 — Configure SMTP for the Contact Form

Edit `public/contact.php` lines 6–9:

```php
define('SMTP_USER', 'votre-adresse@gmail.com'); // Gmail address used to SEND
define('SMTP_PASS', 'xxxx xxxx xxxx xxxx');     // Gmail App Password (not your login password)
```

**To get a Gmail App Password:**
1. Go to https://myaccount.google.com/security
2. Enable 2-Step Verification (required)
3. Search for "App passwords" → create one for "Mail"
4. Paste the 16-character password into `SMTP_PASS`

Emails will be delivered to `Mayeulg@yahoo.fr`.

---

## Step 3 — Set File Permissions

Make sure PHP can write to these files/directories:

```
public/events.json          ← must be writable
public/images/events/       ← must be writable (image uploads)
```

On Windows with XAMPP, PHP runs as a local user and typically has full access. If you get permission errors, right-click the folder → Properties → Security → give your user write access.

---

## Step 4 — Install Node Dependencies

```bash
cd C:\Users\Aymeric\jetsetboat
npm install
```

---

## Step 5 — Start the Development Environment

**Terminal 1 — Astro dev server:**
```bash
npm run dev
```

**XAMPP Control Panel — start Apache** (must be on port 8080 as configured above)

**Access points:**
| What | URL |
|------|-----|
| Public website | http://localhost:4321 |
| Admin panel | http://localhost:8080/admin/ |
| Contact PHP (direct test) | http://localhost:8080/contact.php |
| Events JSON | http://localhost:4321/events.json |

---

## Step 6 — Build & Deploy to OVH

```bash
npm run build
```

This generates a `dist/` folder. Upload its contents to OVH via FTP.

### What to upload via FTP:

| Local path | Upload to OVH |
|------------|---------------|
| `dist/` (entire contents) | `/public_html/` (or your root) |

The `dist/` already contains copies of everything in `public/` including:
- `contact.php`
- `phpmailer/`
- `events.json`
- `admin/`
- `images/`
- `.htaccess`

### Post-upload checklist on OVH:
- [ ] Verify PHP 7.4+ is active (OVH Manager → Hosting → PHP version)
- [ ] Set `events.json` permissions to **664** via FTP (right-click → Properties)
- [ ] Set `images/events/` permissions to **755** via FTP
- [ ] Delete `admin/index.html` from OVH (old Netlify CMS file, superseded by `admin/index.php`)
- [ ] Test the contact form
- [ ] Test the admin panel at `/admin/`

---

## Project Structure (Quick Reference)

```
jetsetboat/
├── src/
│   ├── components/
│   │   ├── Events.astro       # Fetches events.json client-side
│   │   ├── Contact.astro      # Form → contact.php
│   │   └── ...
│   └── pages/index.astro
├── public/
│   ├── events.json            # ← Events data (edited by admin panel)
│   ├── contact.php            # PHP mailer
│   ├── phpmailer/             # PHPMailer library
│   ├── images/events/         # Uploaded event images
│   ├── admin/
│   │   ├── index.php          # Admin panel (login + CRUD)
│   │   └── config.php         # Admin credentials
│   └── .htaccess
└── dist/                      # Build output → FTP to OVH
```
