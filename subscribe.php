<?php
/**
 * Miracle Nature Labs — subscribe.php
 *
 * Accepts:  POST { name, email }
 * Stores:   name|email|timestamp  (one record per line, pipe-delimited)
 * Returns:  JSON { success: bool, message: string }
 *
 * File is stored in  data/subscribers.txt
 * That directory is protected by data/.htaccess
 */

/* ── Output & error settings ───────────────────────────────── */
header('Content-Type: application/json; charset=utf-8');
// Only allow requests from the same origin (same domain)
header('X-Content-Type-Options: nosniff');

/* ── Only accept POST ───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

/* ── Read & validate inputs ─────────────────────────────────── */
$raw_name  = isset($_POST['name'])  ? trim($_POST['name'])  : '';
$raw_email = isset($_POST['email']) ? trim($_POST['email']) : '';

// Name: required, 1–100 chars, strip HTML
if ($raw_name === '' || mb_strlen($raw_name) > 100) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid name (max 100 characters).']);
    exit;
}

// Email: valid format, max 254 chars (RFC 5321)
if (!filter_var($raw_email, FILTER_VALIDATE_EMAIL) || mb_strlen($raw_email) > 254) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// Sanitize
$name  = htmlspecialchars($raw_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$email = strtolower(filter_var($raw_email, FILTER_SANITIZE_EMAIL));

// Strip pipe characters from name to keep the format clean
$name = str_replace(['|', "\n", "\r"], ['', '', ''], $name);

/* ── File location ───────────────────────────────────────────── */
$data_dir = __DIR__ . '/data/';
$file     = $data_dir . 'subscribers.txt';
$header   = "name|email|subscribed_at\n";

// Create data directory if it doesn't exist
if (!is_dir($data_dir)) {
    if (!mkdir($data_dir, 0750, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server configuration error. Please try again later.']);
        exit;
    }
}

/* ── Duplicate-email check ───────────────────────────────────── */
if (file_exists($file)) {
    $existing = @file_get_contents($file);
    if ($existing !== false) {
        // Search for this email in the file (case-insensitive, bounded by pipes / line endings)
        foreach (explode("\n", $existing) as $line) {
            $parts = explode('|', $line);
            if (isset($parts[1]) && strtolower(trim($parts[1])) === $email) {
                echo json_encode(['success' => false, 'message' => 'This email is already on our list — we\'ll be in touch!']);
                exit;
            }
        }
    }
}

/* ── Append record ────────────────────────────────────────────── */
$timestamp = date('Y-m-d H:i:s');   // e.g.  2026-02-27 14:30:00
$record    = $name . '|' . $email . '|' . $timestamp . "\n";

// Write header on first record
$needs_header = !file_exists($file) || filesize($file) === 0;

$fp = @fopen($file, 'a');

if (!$fp) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save your details. Please try again later.']);
    exit;
}

// Exclusive lock so concurrent requests don't corrupt the file
if (flock($fp, LOCK_EX)) {
    if ($needs_header) {
        fwrite($fp, $header);
    }
    fwrite($fp, $record);
    flock($fp, LOCK_UN);
}

fclose($fp);

/* ── Success ─────────────────────────────────────────────────── */
echo json_encode([
    'success' => true,
    'message' => 'You\'re on the list! We\'ll notify you the moment we launch. Thank you, ' . htmlspecialchars($raw_name, ENT_QUOTES, 'UTF-8') . '!'
]);
