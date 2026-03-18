# Miracle Nature Labs — Coming Soon Website

Premium natural health and wellness brand landing page. Built for shared hosting (Apache + PHP).

---

## File Structure

```
MiracleNatureLabs/
├── index.html          ← Main coming soon page
├── contact.php         ← Contact form page (emails to contact@miraclenaturelabs.com)
├── style.css           ← All styles (shared by both pages)
├── script.js           ← Particles, countdown, subscribe form logic
├── subscribe.php       ← Subscribe form handler (writes to data/subscribers.txt)
├── assets/
│   └── logo.png        ← ★ Place your logo here
└── data/
    ├── .htaccess       ← Blocks direct browser access to this folder
    └── subscribers.txt ← Auto-created on first signup
```

---

## What's Included

**Page sections:**
- Full-screen hero with floating logo, animated gold particles, pulsing launch badge, and countdown timer
- 3-pillar "About" section (Natural / Science-Backed / Premium Quality)
- 4 product teaser cards: Natural Protection, Himalayan Shilajit, Vitality Complex, and a mystery product — all with "Launching Soon" overlays
- Subscribe form (name + email) with AJAX submission, client-side validation, and a success/error message
- Footer with social link placeholders

**Contact page (`contact.php`):**
- Form fields: Full Name, Email Address, Phone (optional), Message
- Character counter on the message field (max 5,000 chars)
- Server-side validation with field-level error messages
- Emails the full submission to `contact@miraclenaturelabs.com` via PHP `mail()`
- Email includes all form data plus visitor metadata: IP address, browser/user agent, accepted language, referrer, and timestamp
- CSRF token protection against cross-site request forgery
- Honeypot field to silently reject bot submissions
- Matches the same dark green/gold design as the main page

**Subscriber data** is stored in `data/subscribers.txt` in pipe-delimited format:
```
name|email|subscribed_at
Jane Smith|jane@example.com|2026-03-15 09:41:22
John Doe|john@example.com|2026-03-16 11:05:44
```

---

## Before Uploading

### 1. Add your logo
Save the logo file as `assets/logo.png`.

### 2. Set the launch date
Open `script.js` and update line 26:
```js
var LAUNCH_DATE = new Date('2026-09-01T00:00:00');
```
Change `2026-09-01` to your actual planned launch date.

### 3. Update social links
In `index.html`, search for `href="#"` in the footer social links section and replace with your actual profile URLs (Instagram, Facebook, Twitter/X).

### 4. Update the contact email addresses
Two places to update if your inbox address changes:
- `contact.php` line 8: `define('TO_EMAIL', 'contact@miraclenaturelabs.com');`
- `contact.php` line 9: update `SITE_URL` to your live domain

---

## Running Locally on Windows

Since the site uses PHP for form handling, you need a PHP-capable server locally. Choose one of the options below.

---

### Option A — PHP Built-in Server (Recommended, no install needed if PHP is present)

1. Open a terminal (Git Bash, PowerShell, or Command Prompt) in the project folder.
2. Run:
   ```bash
   php -S localhost:8000
   ```
3. Open `http://localhost:8000` in your browser.

> To check if PHP is installed: run `php -v`. If not installed, download from [php.net/downloads](https://www.php.net/downloads) and add it to your PATH, or install via [Scoop](https://scoop.sh): `scoop install php`.

**Limitation:** PHP's `mail()` won't actually send emails locally. The contact form will process but emails won't be delivered — that's expected.

---

### Option B — VS Code Live Server Extension

> Best for **static preview only** (HTML/CSS/JS). PHP forms will not work.

1. Install the [Live Server](https://marketplace.visualstudio.com/items?itemName=ritwickdey.LiveServer) extension in VS Code.
2. Open the project folder in VS Code.
3. Right-click `index.html` in the Explorer → **Open with Live Server**.
4. The browser opens at `http://127.0.0.1:5500`.

**Limitation:** `.php` files are served as plain text. Use Option A or C if you need to test the subscribe/contact forms.

---

### Option C — XAMPP (Full PHP + Apache stack)

1. Download and install [XAMPP](https://www.apachefriends.org/).
2. Copy the project folder into `C:\xampp\htdocs\MiracleNatureLabs\`.
3. Open the XAMPP Control Panel and start **Apache**.
4. Open `http://localhost/MiracleNatureLabs/` in your browser.

**Advantage:** Closest to the live Namecheap shared hosting environment. PHP forms work fully.

---

## Deploying to Namecheap Shared Hosting
Using SCP from gitbash:  
go to files dir and then run (shared IP Address from CPanel right side):
scp -r -P 21098 * miranaou@162.0.232.35:/home/miranaou/miraclenaturelabs.com/

Using UI:
1. Log in to your Namecheap cPanel.
2. Open **File Manager** and navigate to `public_html/` (or your domain's root folder).
3. Upload all files and folders, preserving the directory structure above.
4. PHP is enabled by default on all Namecheap shared plans — no extra configuration needed.
5. The `data/` folder and `subscribers.txt` file will be created automatically when the first person subscribes.

> **FTP alternative:** Use FileZilla or any FTP client with the credentials found under cPanel → FTP Accounts.

---

## Accessing Subscriber Data

Download `data/subscribers.txt` via cPanel File Manager or FTP at any time. The file is pipe-delimited and can be opened directly in Excel or Google Sheets (choose `|` as the delimiter on import).

The file is protected by `data/.htaccess` — it cannot be accessed via a browser URL, only via FTP/cPanel or PHP on the server.
