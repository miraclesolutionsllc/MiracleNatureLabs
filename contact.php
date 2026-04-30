<?php
/**
 * Miracle Nature Labs — contact.php
 *
 * Self-posting contact page.
 * GET  → show the form
 * POST → validate, collect metadata, send HTML email, show result
 *
 * Email is sent via PHP mail() — works on Namecheap shared hosting.
 */

session_start();

/* ── Configuration ───────────────────────────────────────────── */
define('TO_EMAIL',  'info@miraclenaturelabs.com');
define('SITE_NAME', 'Miracle Nature Labs');
define('SITE_URL',  'https://www.miraclenaturelabs.com');   // update to your domain

/* ── CSRF token ──────────────────────────────────────────────── */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/* ── State variables ─────────────────────────────────────────── */
$success = false;
$errors  = [];

// Preserved field values (re-populated on validation failure)
$f_name    = '';
$f_email   = '';
$f_phone   = '';
$f_message = '';

// Safe UTF-8 string length helper; avoids fatal errors when mbstring is unavailable.
if (!function_exists('safe_strlen')) {
    function safe_strlen($value) {
        if (is_string($value) && function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }
        return is_string($value) ? strlen($value) : 0;
    }
}

/* ══════════════════════════════════════════════════════════════
   POST HANDLER
   ══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* — CSRF check — */
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors[] = 'Invalid request token. Please refresh the page and try again.';
    }

    /* — Honeypot: bots fill this hidden field; humans don't — */
    if (!empty($_POST['website'])) {
        // Silent reject: pretend success so bots don't know they were caught
        $success = true;
    }

    if (empty($errors) && !$success) {

        /* — Read & sanitize raw inputs — */
        $name    = trim($_POST['name']    ?? '');
        $email   = trim($_POST['email']   ?? '');
        $phone   = trim($_POST['phone']   ?? '');
        $message = trim($_POST['message'] ?? '');

        /* — Validate — */
        if ($name === '' || safe_strlen($name) > 100) {
            $errors[] = 'Please enter your full name (max 100 characters).';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || safe_strlen($email) > 254) {
            $errors[] = 'Please enter a valid email address.';
        }

        // Phone is optional, but if provided must look like a phone number
        if ($phone !== '' && !preg_match('/^[+\d\s()\-\.]{5,25}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number, or leave the field empty.';
        }

        if ($message === '' || safe_strlen($message) > 5000) {
            $errors[] = 'Please enter a message (max 5,000 characters).';
        }

        /* — Preserve values for re-display on error — */
        $f_name    = htmlspecialchars($name,    ENT_QUOTES, 'UTF-8');
        $f_email   = htmlspecialchars($email,   ENT_QUOTES, 'UTF-8');
        $f_phone   = htmlspecialchars($phone,   ENT_QUOTES, 'UTF-8');
        $f_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        /* — Send email if no errors — */
        if (empty($errors)) {

            /* Collect visitor metadata */
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            // Respect forwarding headers (Cloudflare, load balancers)
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $hdr) {
                if (!empty($_SERVER[$hdr])) {
                    $candidate = trim(explode(',', $_SERVER[$hdr])[0]);
                    if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                        $ip = $candidate;
                        break;
                    }
                }
            }

            $user_agent      = $_SERVER['HTTP_USER_AGENT']      ?? 'Unknown';
            $referrer        = $_SERVER['HTTP_REFERER']         ?? 'Direct / Unknown';
            $language        = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'Unknown';
            $timestamp       = date('D, d M Y H:i:s T');
            $server_timezone = date_default_timezone_get();
            $country         = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'Unknown';
            $host            = $_SERVER['HTTP_HOST'] ?? 'Unknown';
            $request_uri     = $_SERVER['REQUEST_URI'] ?? 'Unknown';
            $client_timezone = trim($_POST['browser_timezone'] ?? '');
            $timezone_offset = trim($_POST['timezone_offset'] ?? '');
            $client_language = trim($_POST['browser_language'] ?? '');
            $client_platform = trim($_POST['platform'] ?? '');
            $screen_size     = trim($_POST['screen_size'] ?? '');

            /* Safe-for-HTML versions */
            $h_name    = htmlspecialchars($name,       ENT_QUOTES, 'UTF-8');
            $h_email   = htmlspecialchars($email,      ENT_QUOTES, 'UTF-8');
            $h_phone   = htmlspecialchars($phone ?: 'Not provided', ENT_QUOTES, 'UTF-8');
            $h_msg     = nl2br(htmlspecialchars($message,   ENT_QUOTES, 'UTF-8'));
            $h_ua      = htmlspecialchars($user_agent, ENT_QUOTES, 'UTF-8');
            $h_ref     = htmlspecialchars($referrer,   ENT_QUOTES, 'UTF-8');
            $h_lang    = htmlspecialchars($language,   ENT_QUOTES, 'UTF-8');
            $h_ip      = htmlspecialchars($ip,         ENT_QUOTES, 'UTF-8');
            $h_country = htmlspecialchars($country,    ENT_QUOTES, 'UTF-8');
            $h_host    = htmlspecialchars($host,       ENT_QUOTES, 'UTF-8');
            $h_uri     = htmlspecialchars($request_uri, ENT_QUOTES, 'UTF-8');
            $h_server_tz = htmlspecialchars($server_timezone, ENT_QUOTES, 'UTF-8');
            $h_client_tz = htmlspecialchars($client_timezone ?: 'Unknown', ENT_QUOTES, 'UTF-8');
            $h_tz_offset = htmlspecialchars($timezone_offset ?: 'Unknown', ENT_QUOTES, 'UTF-8');
            $h_client_lang = htmlspecialchars($client_language ?: 'Unknown', ENT_QUOTES, 'UTF-8');
            $h_platform = htmlspecialchars($client_platform ?: 'Unknown', ENT_QUOTES, 'UTF-8');
            $h_screen = htmlspecialchars($screen_size ?: 'Unknown', ENT_QUOTES, 'UTF-8');
            $email_css_path = __DIR__ . '/email-contact.css';
            $email_css = is_readable($email_css_path) ? file_get_contents($email_css_path) : '';

            /* ── HTML email body ─────────────────────────── */
            $email_body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
{$email_css}
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <h1>&#x2709; New Contact Message</h1>
    <p>Submitted via the contact form on miraclenaturelabs.com</p>
  </div>

  <div class="body">

    <div class="row">
      <div class="label">Full Name</div>
      <div class="value">{$h_name}</div>
    </div>

    <div class="row">
      <div class="label">Email Address</div>
      <div class="value"><a href="mailto:{$h_email}">{$h_email}</a></div>
    </div>

    <div class="row">
      <div class="label">Phone Number</div>
      <div class="value">{$h_phone}</div>
    </div>

    <div class="row">
      <div class="label">Message</div>
      <div class="msg-box">{$h_msg}</div>
    </div>

    <hr class="divider">

    <div class="row row--flush">
      <div class="label label--spaced">Request Metadata</div>
      <table class="meta-table">
        <tr><td>Submitted At</td><td>{$timestamp}</td></tr>
        <tr><td>IP Address</td><td>{$h_ip}</td></tr>
        <tr><td>Location</td><td>{$h_country}</td></tr>
        <tr><td>Client Timezone</td><td>{$h_client_tz}</td></tr>
        <tr><td>Timezone Offset</td><td>{$h_tz_offset}</td></tr>
        <tr><td>Server Timezone</td><td>{$h_server_tz}</td></tr>
        <tr><td>Browser / UA</td><td>{$h_ua}</td></tr>
        <tr><td>Language</td><td>{$h_lang}</td></tr>
        <tr><td>Browser Language</td><td>{$h_client_lang}</td></tr>
        <tr><td>Platform</td><td>{$h_platform}</td></tr>
        <tr><td>Screen Size</td><td>{$h_screen}</td></tr>
        <tr><td>Host</td><td>{$h_host}</td></tr>
        <tr><td>Request URI</td><td>{$h_uri}</td></tr>
        <tr><td>Referred From</td><td>{$h_ref}</td></tr>
      </table>
    </div>

  </div>

  <div class="footer">
    <p>This message was sent via the contact form at miraclenaturelabs.com &mdash; do not reply to this automated email; use Reply-To instead.</p>
  </div>

</div>
</body>
</html>
HTML;

            /* ── Mail headers ────────────────────────────── */
            // From must be on the same domain for Namecheap mail() to avoid spam filters
            $subject = '=?UTF-8?B?' . base64_encode('New Contact Message from ' . $name) . '?=';
            $headers  = 'From: ' . SITE_NAME . ' <noreply@miraclenaturelabs.com>' . "\r\n";
            $headers .= 'Reply-To: ' . $name . ' <' . $email . '>' . "\r\n";
            $headers .= 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
            $headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";
            $headers .= 'X-Priority: 3' . "\r\n";

            $sent = mail(TO_EMAIL, $subject, $email_body, $headers);

            if ($sent) {
                $success = true;
                // Rotate CSRF token so the same form can't be resubmitted
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrf_token = $_SESSION['csrf_token'];
                // Clear preserved values
                $f_name = $f_email = $f_phone = $f_message = '';
            } else {
                $errors[] = 'Your message could not be sent at this time. Please try again, or email us directly at <a href="mailto:' . TO_EMAIL . '">' . TO_EMAIL . '</a>.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contact Miracle Nature Labs — send questions, wholesale inquiries, and partnership opportunities.">
    <meta name="keywords" content="contact, customer support, Miracle Nature Labs, partnership, inquiries">
    <meta name="robots" content="index, follow">
    <meta name="author" content="Miracle Nature Labs">
    <link rel="canonical" href="https://miraclenaturelabs.com/contact.php">

    <meta property="og:site_name" content="Miracle Nature Labs">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Contact Us — Miracle Nature Labs">
    <meta property="og:description" content="Contact Miracle Nature Labs — send questions, wholesale inquiries, and partnership opportunities.">
    <meta property="og:url" content="https://miraclenaturelabs.com/contact.php">
    <meta property="og:image" content="https://miraclenaturelabs.com/assets/miracle_nature_labs_transparent_logo.png">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Contact Us — Miracle Nature Labs">
    <meta name="twitter:description" content="Contact Miracle Nature Labs — send questions, wholesale inquiries, and partnership opportunities.">
    <meta name="twitter:image" content="https://miraclenaturelabs.com/assets/miracle_nature_labs_transparent_logo.png">

    <title>Contact Us — <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/jpeg" href="assets/logo_no_text_full.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="contact.css">
</head>
<body>

<canvas id="particles-canvas"></canvas>
<div id="header-container"></div>

<!-- ═══════════════ PAGE HEADER ═══════════════════════════════ -->
<header class="page-header">
    <a href="index.html">
        <img src="assets/miracle_nature_labs_transparent_logo.png" alt="<?php echo SITE_NAME; ?>" class="page-header-logo">
    </a>
    <p class="section-eyebrow">Get in Touch</p>
    <h1 class="section-title">Contact <span class="gold-text">Us</span></h1>
    <p class="section-sub section-sub--flush">
        Have a question, partnership enquiry, or just want to say hello?<br>
        We&rsquo;d love to hear from you.
    </p>
</header>

<!-- ═══════════════ CONTACT FORM ══════════════════════════════ -->
<section class="contact-section">
    <div class="container">
        <div class="subscribe-card contact-card">

            <?php if ($success): ?>
            <!-- ── Success state ── -->
            <div class="success-box">
                <div class="success-icon">
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                        <path d="M6 14L11.5 19.5L22 9" stroke="#4aaa5a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h3>Message <span class="gold-text">Sent!</span></h3>
                <p>Thank you for reaching out. We&rsquo;ve received your message and will get back to you as soon as possible.</p>
            </div>

            <?php else: ?>
            <!-- ── Form state ── -->
            <p class="section-eyebrow">Send a Message</p>
            <h2 class="section-title contact-form-title">
                We&rsquo;re Here to <span class="gold-text">Help</span>
            </h2>
            <p class="form-intro">
                All fields marked are required except phone.
            </p>

            <?php if (!empty($errors)): ?>
            <div class="error-list" role="alert">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo $err; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="contact.php" novalidate>

                <!-- CSRF token — hidden security field -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" id="browser-timezone" name="browser_timezone">
                <input type="hidden" id="timezone-offset" name="timezone_offset">
                <input type="hidden" id="browser-language" name="browser_language">
                <input type="hidden" id="platform" name="platform">
                <input type="hidden" id="screen-size" name="screen_size">

                <!-- Honeypot — hidden from real users, attracts bots -->
                <div class="honeypot" aria-hidden="true">
                    <label for="website">Leave this empty</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <!-- Row 1: Full Name -->
                <div class="sub-form-fields one-col form-row">
                    <div class="field-group">
                        <label for="c-name">Full Name</label>
                        <input
                            type="text"
                            id="c-name"
                            name="name"
                            placeholder="Your full name"
                            value="<?php echo $f_name; ?>"
                            maxlength="100"
                            autocomplete="name"
                            required
                        >
                    </div>
                </div>

                <!-- Row 2: Email + Phone -->
                <div class="sub-form-fields two-col form-row">
                    <div class="field-group">
                        <label for="c-email">Email Address</label>
                        <input
                            type="email"
                            id="c-email"
                            name="email"
                            placeholder="your@email.com"
                            value="<?php echo $f_email; ?>"
                            maxlength="254"
                            autocomplete="email"
                            required
                        >
                    </div>
                    <div class="field-group">
                        <label for="c-phone">
                            Phone Number
                            <span class="optional">(optional)</span>
                        </label>
                        <input
                            type="tel"
                            id="c-phone"
                            name="phone"
                            placeholder="+1 555 000 0000"
                            value="<?php echo $f_phone; ?>"
                            maxlength="25"
                            autocomplete="tel"
                        >
                    </div>
                </div>

                <!-- Row 3: Message -->
                <div class="sub-form-fields one-col form-row form-row--message">
                    <div class="field-group">
                        <label for="c-message">Message</label>
                        <textarea
                            id="c-message"
                            name="message"
                            placeholder="Tell us how we can help..."
                            maxlength="5000"
                            required
                        ><?php echo $f_message; ?></textarea>
                        <div class="char-counter" id="char-counter">0 / 5000</div>
                    </div>
                </div>

                <!-- Submit row -->
                <div class="sub-form-bottom">
                    <p class="privacy-note">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                            <path d="M7 1L2 3.5V7.5C2 10.26 4.24 12.87 7 13.5C9.76 12.87 12 10.26 12 7.5V3.5L7 1Z" stroke="#c9920a" stroke-width="1.2" fill="none"/>
                        </svg>
                        Your details are kept private and never shared.
                    </p>
                    <button type="submit" class="btn-subscribe">Send Message</button>
                </div>

            </form>
            <?php endif; ?>

        </div>
    </div>
</section>

<section class="office-section">
    <div class="container">
        <p class="section-eyebrow">Office Locations</p>
        <h2 class="section-title">Our <span class="gold-text">Locations</span></h2>
        <div class="office-grid">
            <div class="office-card">
                <img src="assets/new_delhi.png" alt="New Delhi office location" class="office-image">
                <h3>New Delhi</h3>
                <address>D7/60, Sector 6, Rohini<br>New Delhi - 110085<br>India</address>
            </div>
            <div class="office-card">
                <img src="assets/varanasi.jpeg" alt="Varanasi office location" class="office-image">
                <h3>Varanasi</h3>
                <address>N10/79, C-12, BLW <br>Varanasi - 221004<br>India</address>
            </div>
            <div class="office-card">
                <img src="assets/usa.png" alt="USA office location in North Carolina" class="office-image">
                <h3>USA</h3>
                <address>11 Union St S, Ste 205<br>Concord, NC 28025<br>USA</address>
            </div>
        </div>
    </div>
</section>

<div id="footer-container"></div>

<script src="script.js"></script>
<script>
/* Character counter for the message textarea */
(function () {
    var ta      = document.getElementById('c-message');
    var counter = document.getElementById('char-counter');
    if (!ta || !counter) return;

    function update() {
        var len = ta.value.length;
        var max = parseInt(ta.getAttribute('maxlength'), 10) || 5000;
        counter.textContent = len + ' / ' + max;
        counter.className = 'char-counter';
        if (len >= max)           counter.classList.add('at-limit');
        else if (len >= max * 0.9) counter.classList.add('near-limit');
    }

    ta.addEventListener('input', update);
    update(); /* init with pre-filled value on validation failure */
}());

/* Browser-provided metadata for contact email context */
(function () {
    var tz = document.getElementById('browser-timezone');
    var offset = document.getElementById('timezone-offset');
    var lang = document.getElementById('browser-language');
    var platform = document.getElementById('platform');
    var screenSize = document.getElementById('screen-size');

    if (tz && window.Intl && Intl.DateTimeFormat) {
        tz.value = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    }
    if (offset) offset.value = String(new Date().getTimezoneOffset());
    if (lang) lang.value = navigator.language || '';
    if (platform) platform.value = navigator.platform || '';
    if (screenSize && window.screen) {
        screenSize.value = window.screen.width + 'x' + window.screen.height;
    }
}());
</script>

</body>
</html>
