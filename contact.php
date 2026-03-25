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
define('TO_EMAIL',  'contact@miraclenaturelabs.com');
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

            $user_agent = $_SERVER['HTTP_USER_AGENT']      ?? 'Unknown';
            $referrer   = $_SERVER['HTTP_REFERER']         ?? 'Direct / Unknown';
            $language   = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'Unknown';
            $timestamp  = date('D, d M Y H:i:s T');

            /* Safe-for-HTML versions */
            $h_name    = htmlspecialchars($name,       ENT_QUOTES, 'UTF-8');
            $h_email   = htmlspecialchars($email,      ENT_QUOTES, 'UTF-8');
            $h_phone   = htmlspecialchars($phone ?: 'Not provided', ENT_QUOTES, 'UTF-8');
            $h_msg     = nl2br(htmlspecialchars($message,   ENT_QUOTES, 'UTF-8'));
            $h_ua      = htmlspecialchars($user_agent, ENT_QUOTES, 'UTF-8');
            $h_ref     = htmlspecialchars($referrer,   ENT_QUOTES, 'UTF-8');
            $h_lang    = htmlspecialchars($language,   ENT_QUOTES, 'UTF-8');
            $h_ip      = htmlspecialchars($ip,         ENT_QUOTES, 'UTF-8');

            /* ── HTML email body ─────────────────────────── */
            $email_body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body    { font-family: Arial, Helvetica, sans-serif; background: #f0ede5; padding: 24px; }
  .wrap   { max-width: 620px; margin: auto; background: #ffffff; border-radius: 10px; overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12); }
  .header { background: #0d2016; padding: 28px 36px; }
  .header h1 { font-size: 20px; color: #f0c040; letter-spacing: 0.06em; font-weight: 700; }
  .header p  { font-size: 12px; color: rgba(240,235,224,0.5); margin-top: 5px; }
  .body   { padding: 32px 36px; }
  .row    { margin-bottom: 22px; }
  .label  { font-size: 10px; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase;
            color: #8b6914; margin-bottom: 5px; }
  .value  { font-size: 15px; color: #1c1c1c; line-height: 1.65; }
  .value a { color: #c9920a; text-decoration: none; }
  .msg-box { background: #faf7ee; border-left: 4px solid #c9920a; border-radius: 4px;
             padding: 14px 18px; font-size: 15px; color: #1c1c1c; line-height: 1.75; }
  .divider { border: none; border-top: 1px solid #e8e0d0; margin: 28px 0; }
  .meta-table { width: 100%; border-collapse: collapse; font-size: 12px; }
  .meta-table td { padding: 6px 0; vertical-align: top; border-bottom: 1px solid #f0ede5; }
  .meta-table td:first-child { color: #999; width: 130px; padding-right: 12px; white-space: nowrap; font-weight: 600; }
  .meta-table td:last-child  { color: #444; word-break: break-word; }
  .footer { background: #070f09; padding: 16px 36px; text-align: center; }
  .footer p { font-size: 11px; color: rgba(240,235,224,0.3); }
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

    <div class="row" style="margin-bottom:0">
      <div class="label" style="margin-bottom:10px">Request Metadata</div>
      <table class="meta-table">
        <tr><td>Submitted At</td><td>{$timestamp}</td></tr>
        <tr><td>IP Address</td><td>{$h_ip}</td></tr>
        <tr><td>Browser / UA</td><td>{$h_ua}</td></tr>
        <tr><td>Language</td><td>{$h_lang}</td></tr>
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
    <style>
        /* ── Contact page extras ─────────────────────────── */

        /* Compact page header (replaces full hero on inner pages) */
        .page-header {
            position: relative;
            z-index: 1;
            padding: 3rem 1.5rem 3.5rem;
            text-align: center;
            background:
                radial-gradient(ellipse 70% 80% at 50% 0%, rgba(45,122,58,0.12) 0%, transparent 70%),
                var(--bg-deep);
            border-bottom: 1px solid var(--gold-border);
        }
        .page-header-logo {
            height: 64px;
            width: auto;
            margin: 0 auto 1.5rem;
            filter: drop-shadow(0 0 16px rgba(201,146,10,0.3));
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            color: var(--text-subtle);
            margin-bottom: 1.5rem;
            transition: color var(--transition);
        }
        .back-link:hover { color: var(--gold-bright); }
        .back-link svg   { flex-shrink: 0; }

        /* Contact section wrapper */
        .contact-section {
            position: relative;
            z-index: 1;
            padding: 5rem 1.5rem 6rem;
            background:
                radial-gradient(ellipse 60% 50% at 50% 60%, rgba(201,146,10,0.06) 0%, transparent 70%),
                var(--bg-section);
        }

        /* Textarea */
        .field-group textarea {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(201,146,10,0.25);
            border-radius: var(--radius-input);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.95rem;
            padding: 0.85rem 1.1rem;
            width: 100%;
            outline: none;
            resize: vertical;
            min-height: 150px;
            line-height: 1.65;
            transition: border-color var(--transition), box-shadow var(--transition);
        }
        .field-group textarea::placeholder { color: var(--text-subtle); }
        .field-group textarea:focus {
            border-color: var(--gold-mid);
            box-shadow: 0 0 0 3px rgba(201,146,10,0.12);
        }
        .field-group textarea.input-error,
        .field-group input.input-error {
            border-color: #c0392b;
            box-shadow: 0 0 0 3px rgba(192,57,43,0.12);
        }

        /* Three-column row for email + phone */
        .sub-form-fields.two-col {
            grid-template-columns: 1fr 1fr;
        }
        .sub-form-fields.one-col {
            grid-template-columns: 1fr;
        }

        /* Error list */
        .error-list {
            background: rgba(192,57,43,0.15);
            border: 1px solid rgba(192,57,43,0.4);
            border-radius: var(--radius-input);
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            color: #e07a6e;
            font-size: 0.88rem;
            line-height: 1.7;
        }
        .error-list ul { list-style: disc; padding-left: 1.2rem; }

        /* Success card */
        .success-box {
            text-align: center;
            padding: 2rem;
        }
        .success-icon {
            width: 64px; height: 64px;
            background: rgba(45,122,58,0.2);
            border: 1px solid rgba(74,170,90,0.4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .success-box h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
        }
        .success-box p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        /* Char counter */
        .char-counter {
            font-size: 0.72rem;
            color: var(--text-subtle);
            text-align: right;
            margin-top: 0.3rem;
        }
        .char-counter.near-limit { color: var(--gold-mid); }
        .char-counter.at-limit   { color: #e07a6e; }

        /* Optional badge on phone label */
        .optional {
            font-size: 0.68rem;
            color: var(--text-subtle);
            font-weight: 400;
            margin-left: 0.35rem;
            letter-spacing: 0;
        }

        @media (max-width: 560px) {
            .sub-form-fields.two-col { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<canvas id="particles-canvas"></canvas>

<!-- ═══════════════ PAGE HEADER ═══════════════════════════════ -->
<header class="page-header">
    <a href="index.html" class="back-link">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
            <path d="M9 2L4 7L9 12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Back to home
    </a>
    <img src="assets/miracle_nature_labs_transparent_logo.png" alt="<?php echo SITE_NAME; ?>" class="page-header-logo">
    <p class="section-eyebrow">Get in Touch</p>
    <h1 class="section-title">Contact <span class="gold-text">Us</span></h1>
    <p class="section-sub" style="margin-bottom:0">
        Have a question, partnership enquiry, or just want to say hello?<br>
        We&rsquo;d love to hear from you.
    </p>
</header>

<!-- ═══════════════ CONTACT FORM ══════════════════════════════ -->
<section class="contact-section">
    <div class="container">
        <div class="subscribe-card" style="max-width:680px">

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
                <a href="index.html" class="hero-cta" style="font-size:0.85rem;padding:0.75rem 1.8rem;">
                    Back to Home
                </a>
            </div>

            <?php else: ?>
            <!-- ── Form state ── -->
            <p class="section-eyebrow">Send a Message</p>
            <h2 class="section-title" style="font-size:1.6rem;margin-bottom:0.5rem;">
                We&rsquo;re Here to <span class="gold-text">Help</span>
            </h2>
            <p style="font-size:0.88rem;color:var(--text-muted);margin-bottom:1.75rem;">
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

                <!-- Honeypot — hidden from real users, attracts bots -->
                <div style="display:none;visibility:hidden;position:absolute;left:-9999px;" aria-hidden="true">
                    <label for="website">Leave this empty</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <!-- Row 1: Full Name -->
                <div class="sub-form-fields one-col" style="margin-bottom:1rem;">
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
                <div class="sub-form-fields two-col" style="margin-bottom:1rem;">
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
                <div class="sub-form-fields one-col" style="margin-bottom:1.5rem;">
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
</script>

</body>
</html>
