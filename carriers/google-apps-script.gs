/**
 * OTR Express Group — Lead Webhook for Google Sheets
 *
 * Accepts leads from ANY form (/carriers page, driver application page, etc.)
 * and appends a row to whichever sheet/tab the caller names.
 *
 * Expected JSON payload:
 *   {
 *     "secret":     "otr-offer-2026",         // must match SECRET below
 *     "sheet_name": "carriers",               // tab name; created if missing
 *     "headers":    ["Submitted At", "Name", ...],   // written once, on first row
 *     "values":     ["2026-06-23 10:00", "Jane", ...] // appended as a new row
 *   }
 *
 * SETUP (one time):
 *   1. Open the target spreadsheet:
 *      https://docs.google.com/spreadsheets/d/1QEgUDPC_FMIEvTe6Oik9CKyhGZ9OQJCoyn5VSa3LlQY/edit
 *   2. Extensions  ->  Apps Script. Paste THIS file. Save.
 *   3. Deploy  ->  Manage deployments  ->  Edit (pencil)  ->
 *        Version: New version  ->  Deploy.
 *      (If you've never deployed: Deploy -> New deployment -> Web app,
 *       Execute as: Me, Who has access: Anyone.)
 *   4. Copy the /exec URL into submit.php as $SHEETS_WEBHOOK_URL.
 *      The SAME URL is reused by every PHP form on every site.
 */

const SHEET_ID = '1QEgUDPC_FMIEvTe6Oik9CKyhGZ9OQJCoyn5VSa3LlQY';
const SECRET   = 'otr-offer-2026';

function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents || '{}');

    if (body.secret !== SECRET) {
      return _json({ ok: false, error: 'unauthorized' });
    }

    const sheetName = String(body.sheet_name || 'carriers');
    const headers   = Array.isArray(body.headers) ? body.headers : null;
    const values    = Array.isArray(body.values)  ? body.values  : null;

    if (!values) {
      return _json({ ok: false, error: 'missing values array' });
    }

    const ss = SpreadsheetApp.openById(SHEET_ID);
    let sheet = ss.getSheetByName(sheetName);
    if (!sheet) {
      sheet = ss.insertSheet(sheetName);
    }

    // Write header row on first use of this sheet, if caller supplied one.
    if (sheet.getLastRow() === 0 && headers && headers.length) {
      sheet.appendRow(headers);
      sheet.setFrozenRows(1);
    }

    sheet.appendRow(values);

    return _json({ ok: true, sheet: sheetName, row: sheet.getLastRow() });
  } catch (err) {
    return _json({ ok: false, error: String(err) });
  }
}

function doGet() {
  return _json({ ok: true, info: 'OTR lead webhook alive' });
}

function _json(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
