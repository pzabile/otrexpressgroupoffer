<?php
// OTR Express Group — Google Sheets webhook diagnostic
//
// Upload this file next to submit.php, then open in a browser:
//   https://yourdomain.com/offer/test-sheets.php
//
// It does ONE thing: sends a fake lead to your $SHEETS_WEBHOOK_URL
// and prints the raw response, so you can tell exactly what's wrong.
//
// DELETE THIS FILE once everything works.

header('Content-Type: text/plain; charset=utf-8');

// --- Paste the same two values you have in submit.php --------------------
$SHEETS_WEBHOOK_URL = '';          //  <--- paste your /exec URL here
$SHEETS_SECRET      = 'otr-offer-2026';
// -------------------------------------------------------------------------

echo "OTR Sheets webhook diagnostic\n";
echo str_repeat('=', 50) . "\n\n";

if (!function_exists('curl_init')) {
    echo "FAIL: cURL is not enabled in PHP on this host.\n";
    exit;
}
echo "cURL: OK\n";

if (empty($SHEETS_WEBHOOK_URL)) {
    echo "FAIL: \$SHEETS_WEBHOOK_URL is empty in this file.\n";
    echo "Paste your Apps Script Web App URL (the one ending in /exec) above.\n";
    exit;
}
echo "Webhook URL set: " . substr($SHEETS_WEBHOOK_URL, 0, 60) . "...\n";

if (strpos($SHEETS_WEBHOOK_URL, 'script.google.com') === false) {
    echo "WARNING: that URL does not look like an Apps Script URL.\n";
    echo "It should look like https://script.google.com/macros/s/.../exec\n\n";
}
if (substr($SHEETS_WEBHOOK_URL, -5) !== '/exec') {
    echo "WARNING: URL does not end with /exec — Apps Script Web Apps end in /exec.\n\n";
}

$payload = json_encode([
    'secret'       => $SHEETS_SECRET,
    'submitted_at' => date('Y-m-d H:i:s'),
    'name'         => 'TEST LEAD (delete me)',
    'company'      => 'Diagnostic Co.',
    'trucks'       => 1,
    'phone'        => '+1 555 000 0000',
    'email'        => 'test@example.com',
    'ip'           => $_SERVER['REMOTE_ADDR'] ?? 'cli',
    'user_agent'   => 'OTR diagnostic script',
]);

echo "\nSending POST to webhook...\n";
echo "Payload: $payload\n\n";

$ch = curl_init($SHEETS_WEBHOOK_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_VERBOSE        => false,
]);
$body   = curl_exec($ch);
$err    = curl_error($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$final  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

echo "HTTP status: $status\n";
echo "Final URL:   $final\n";
if ($err) echo "cURL error:  $err\n";
echo "Response body:\n";
echo str_repeat('-', 50) . "\n";
echo ($body === false ? '(no body)' : $body) . "\n";
echo str_repeat('-', 50) . "\n\n";

if ($status === 200 && strpos((string)$body, '"ok":true') !== false) {
    echo "RESULT: SUCCESS.\n";
    echo "Check your Google Sheet — a row called 'TEST LEAD (delete me)' should be there.\n";
} elseif ($status === 401 || strpos((string)$body, 'unauthorized') !== false) {
    echo "RESULT: 401 unauthorized — the SECRET in this file does NOT match\n";
    echo "the SECRET constant inside google-apps-script.gs.\n";
} elseif ($status === 302 || $status === 0) {
    echo "RESULT: Looks like a redirect that wasn't followed, or the URL is wrong.\n";
    echo "Make sure your deployment is set to 'Who has access: Anyone' (NOT 'Anyone with Google account').\n";
} elseif ($status >= 400) {
    echo "RESULT: HTTP $status from Apps Script. Common causes:\n";
    echo "  - The deployed version is OLD. After editing the script you MUST\n";
    echo "    Deploy -> Manage deployments -> Edit (pencil) -> Version: New version -> Deploy.\n";
    echo "  - 'Who has access' is not 'Anyone'. Re-deploy with that setting.\n";
    echo "  - SHEET_ID inside the script doesn't match the sheet you opened.\n";
} else {
    echo "RESULT: Unexpected response — read the body above for clues.\n";
}
