/**
 * OTR Express Group — Lead Webhook for Google Sheets
 *
 * Receives leads from offer/submit.php and appends a row to Sheet1.
 *
 * SETUP (one time):
 *   1. Open the target spreadsheet in your browser:
 *      https://docs.google.com/spreadsheets/d/1QEgUDPC_FMIEvTe6Oik9CKyhGZ9OQJCoyn5VSa3LlQY/edit
 *   2. Extensions  ->  Apps Script.
 *   3. Delete any sample code, paste THIS entire file, save (Ctrl+S / Cmd+S).
 *   4. Click "Deploy"  ->  "New deployment".
 *        Type:           Web app
 *        Description:    OTR offer lead webhook
 *        Execute as:     Me
 *        Who has access: Anyone   (must be Anyone, not "Anyone with Google account")
 *   5. Click "Deploy", grant the requested permissions to your account.
 *   6. Copy the resulting Web app URL (looks like
 *        https://script.google.com/macros/s/AKfyc.../exec  ).
 *   7. Paste that URL into submit.php as the value of $SHEETS_WEBHOOK_URL.
 *   8. (Optional) Change the SECRET below and put the same value in submit.php
 *      as $SHEETS_SECRET. Default is 'otr-offer-2026'.
 *
 * To update the script later, repeat Deploy -> Manage deployments and
 * publish a new version, or use Deploy -> Test deployments while editing.
 */

const SHEET_ID   = '1QEgUDPC_FMIEvTe6Oik9CKyhGZ9OQJCoyn5VSa3LlQY';
const SHEET_NAME = 'Sheet1';
const SECRET     = 'otr-offer-2026';

function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents || '{}');

    if (body.secret !== SECRET) {
      return _json({ ok: false, error: 'unauthorized' }, 401);
    }

    const ss    = SpreadsheetApp.openById(SHEET_ID);
    let sheet   = ss.getSheetByName(SHEET_NAME);
    if (!sheet) sheet = ss.insertSheet(SHEET_NAME);

    // Add a header row if the sheet is empty.
    if (sheet.getLastRow() === 0) {
      sheet.appendRow([
        'Submitted At', 'Name', 'Company', 'Trucks',
        'Phone', 'Email', 'IP', 'User Agent'
      ]);
      sheet.setFrozenRows(1);
    }

    sheet.appendRow([
      body.submitted_at || new Date().toISOString(),
      body.name         || '',
      body.company      || '',
      body.trucks       || '',
      body.phone        || '',
      body.email        || '',
      body.ip           || '',
      body.user_agent   || ''
    ]);

    return _json({ ok: true });
  } catch (err) {
    return _json({ ok: false, error: String(err) }, 500);
  }
}

function doGet() {
  return _json({ ok: true, info: 'OTR lead webhook alive' });
}

function _json(obj /*, status (unused — Apps Script returns 200) */) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
