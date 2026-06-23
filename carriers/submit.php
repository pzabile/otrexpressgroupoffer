<?php
// OTR Express Group — Carrier Lead Handler
// Receives the lead form, generates a PDF, sends it to Telegram,
// and appends a row to Google Sheets via an Apps Script webhook.

header('Content-Type: application/json; charset=utf-8');

// --- Config ---------------------------------------------------------------
$TELEGRAM_BOT_TOKEN = '8517526106:AAH3q0IdULxrUzUhffJfoq0cjX4GBtkdU4g';
$TELEGRAM_CHAT_ID   = '325385972';

// Google Sheets webhook (Apps Script Web App URL).
// Paste the URL after you deploy google-apps-script.gs — see UPLOAD-README.txt.
// Leave empty ('') to disable the Google Sheets save.
$SHEETS_WEBHOOK_URL = '';
$SHEETS_SECRET      = 'otr-offer-2026'; // must match the SECRET in your Apps Script

// --- Helpers --------------------------------------------------------------
function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function clean($s, $max = 500) {
    $s = is_string($s) ? trim($s) : '';
    $s = preg_replace('/[\r\n\t]+/u', ' ', $s);
    if (mb_strlen($s) > $max) $s = mb_substr($s, 0, $max);
    return $s;
}

// --- Method guard ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

// --- Honeypot -------------------------------------------------------------
if (!empty($_POST['website'])) {
    // Pretend success so bots don't retry.
    echo json_encode(['success' => true]);
    exit;
}

// --- Read & validate ------------------------------------------------------
$name    = clean($_POST['name']    ?? '', 80);
$company = clean($_POST['company'] ?? '', 120);
$trucks  = clean($_POST['trucks']  ?? '', 10);
$phone   = clean($_POST['phone']   ?? '', 30);
$email   = clean($_POST['email']   ?? '', 120);

if ($name === '' || $company === '' || $trucks === '' || $phone === '' || $email === '') {
    fail('Missing required fields.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('Invalid email address.');
}
if (!ctype_digit($trucks)) {
    fail('Number of trucks must be a whole number.');
}

$submittedAt = (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d H:i:s T');
$ip          = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua          = clean($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 200);

// --- Minimal PDF builder --------------------------------------------------
// Dependency-free. Single page, Helvetica, plain text.
function pdf_escape($s) {
    // Latin-1 friendly. Strip what won't render in WinAnsi.
    $s = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
    if ($s === false) $s = '';
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}

function build_pdf(array $lines) {
    $pageW = 612; $pageH = 792;
    $marginL = 54; $marginTop = 740;
    $leading = 18;

    $stream  = "BT\n";
    $stream .= "/F1 11 Tf\n";
    $stream .= "{$leading} TL\n";
    $stream .= "{$marginL} {$marginTop} Td\n";

    foreach ($lines as $i => $line) {
        // Style hints: line entries can be ['style' => 'h1'|'h2'|'body', 'text' => ...]
        $text = is_array($line) ? ($line['text'] ?? '') : $line;
        $style = is_array($line) ? ($line['style'] ?? 'body') : 'body';

        $size = 11;
        if ($style === 'h1') $size = 20;
        if ($style === 'h2') $size = 14;
        if ($style === 'spacer') { $text = ''; }

        $stream .= "/F1 {$size} Tf\n";
        if ($i > 0) $stream .= "T*\n";
        $stream .= '(' . pdf_escape($text) . ") Tj\n";
    }
    $stream .= "ET";

    // Object table
    $objects = [];
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageW} {$pageH}] "
                . "/Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>";
    $objects[4] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
    }

    $xrefStart = strlen($pdf);
    $count = count($objects) + 1;
    $pdf .= "xref\n0 {$count}\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefStart}\n%%EOF";

    return $pdf;
}

// --- Compose PDF content --------------------------------------------------
$lines = [
    ['style' => 'h1', 'text' => 'OTR EXPRESS GROUP'],
    ['style' => 'h2', 'text' => 'New Carrier Lead'],
    ['style' => 'body', 'text' => 'Submitted: ' . $submittedAt],
    ['style' => 'spacer', 'text' => ''],
    ['style' => 'h2', 'text' => 'Contact'],
    ['style' => 'body', 'text' => 'Name:           ' . $name],
    ['style' => 'body', 'text' => 'Company:        ' . $company],
    ['style' => 'body', 'text' => 'Trucks in Fleet: ' . $trucks],
    ['style' => 'body', 'text' => 'Phone:          ' . $phone],
    ['style' => 'body', 'text' => 'Email:          ' . $email],
    ['style' => 'spacer', 'text' => ''],
    ['style' => 'h2', 'text' => 'Source'],
    ['style' => 'body', 'text' => 'Page:    /carriers'],
    ['style' => 'body', 'text' => 'IP:      ' . $ip],
    ['style' => 'body', 'text' => 'Agent:   ' . substr($ua, 0, 80)],
    ['style' => 'spacer', 'text' => ''],
    ['style' => 'h2', 'text' => 'Next Step'],
    ['style' => 'body', 'text' => 'Reach out within 24 hours.'],
];

$pdf = build_pdf($lines);
$pdfFilename = 'OTR-Lead_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $company) . '_' . date('Ymd-His') . '.pdf';

// --- Save PDF to temp file -----------------------------------------------
$tmp = tempnam(sys_get_temp_dir(), 'otrlead_') . '.pdf';
if (file_put_contents($tmp, $pdf) === false) {
    fail('Could not generate PDF.', 500);
}

// --- Build Telegram caption ----------------------------------------------
$caption  = "New Carrier Lead\n";
$caption .= "Name: {$name}\n";
$caption .= "Company: {$company}\n";
$caption .= "Trucks: {$trucks}\n";
$caption .= "Phone: {$phone}\n";
$caption .= "Email: {$email}\n";
$caption .= "Submitted: {$submittedAt}";

// --- Send to Telegram via sendDocument -----------------------------------
$url = "https://api.telegram.org/bot{$TELEGRAM_BOT_TOKEN}/sendDocument";

$cfile = new CURLFile($tmp, 'application/pdf', $pdfFilename);
$post = [
    'chat_id'  => $TELEGRAM_CHAT_ID,
    'document' => $cfile,
    'caption'  => $caption,
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $post,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($ch);
$err      = curl_error($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

@unlink($tmp);

if ($response === false || $status >= 400) {
    // Fallback: try sending as plain text so the lead is never lost.
    $fallbackUrl = "https://api.telegram.org/bot{$TELEGRAM_BOT_TOKEN}/sendMessage";
    $ch2 = curl_init($fallbackUrl);
    curl_setopt_array($ch2, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'chat_id' => $TELEGRAM_CHAT_ID,
            'text'    => "PDF delivery failed. Lead details:\n\n" . $caption,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    curl_exec($ch2);
    curl_close($ch2);
    fail('Delivery problem. Our team has been notified — please also email us if you do not hear back within 24 hours.', 502);
}

$decoded = json_decode($response, true);
if (!is_array($decoded) || empty($decoded['ok'])) {
    fail('Telegram rejected the message.', 502);
}

// --- Append to Google Sheets (best effort, never blocks the response) ----
if (!empty($SHEETS_WEBHOOK_URL)) {
    $sheetPayload = json_encode([
        'secret'     => $SHEETS_SECRET,
        'sheet_name' => 'carriers',
        'headers'    => [
            'Submitted At', 'Name', 'Company', 'Trucks',
            'Phone', 'Email', 'IP', 'User Agent'
        ],
        'values'     => [
            $submittedAt, $name, $company, (int)$trucks,
            $phone, $email, $ip, $ua
        ],
    ]);

    $chs = curl_init($SHEETS_WEBHOOK_URL);
    curl_setopt_array($chs, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $sheetPayload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $sheetsResponse = curl_exec($chs);
    $sheetsStatus   = curl_getinfo($chs, CURLINFO_HTTP_CODE);
    curl_close($chs);

    // If Sheets failed, notify Telegram so the row can be added manually.
    // We do NOT fail the user request — Telegram already has the lead.
    if ($sheetsResponse === false || $sheetsStatus >= 400) {
        $warnUrl = "https://api.telegram.org/bot{$TELEGRAM_BOT_TOKEN}/sendMessage";
        $chw = curl_init($warnUrl);
        curl_setopt_array($chw, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'chat_id' => $TELEGRAM_CHAT_ID,
                'text'    => "Heads up: Google Sheets append failed for the lead above (HTTP {$sheetsStatus}). Add the row manually if needed.",
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
        ]);
        curl_exec($chw);
        curl_close($chw);
    }
}

echo json_encode(['success' => true]);
