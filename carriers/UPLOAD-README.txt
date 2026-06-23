HOSTINGER UPLOAD INSTRUCTIONS — /carriers PAGE
================================================

Files in this folder:
  - index.html              (the page itself)
  - submit.php              (form handler -> Telegram + Google Sheets tab "carriers")
  - logo.png                (company logo)
  - .htaccess               (small Apache config)
  - test-sheets.php         (one-time diagnostic; delete after Sheets is verified)
  - google-apps-script.gs   (paste into your Google Sheet's Apps Script editor;
                             DO NOT upload to Hostinger, it lives in Google)
  - DRIVER-APP-SHEETS-SNIPPET.txt  (reference for the OTHER site's
                                    driver-application PHP — see below)

WHERE THIS PAGE GOES LIVE
-------------------------
End URL:  https://otrexpressgroup.com/carriers/

HOW TO DEPLOY ON HOSTINGER
--------------------------
1. Log in to hPanel for the otrexpressgroup.com site.
2. File Manager -> public_html
3. Create a folder named:  carriers
4. Open the "carriers" folder.
5. Upload these files into public_html/carriers/ :
        index.html, submit.php, logo.png, .htaccess, test-sheets.php
   (the .gs and .txt files stay on your computer — they are reference only)
6. Default permissions are fine (folders 755, files 644).

That's it. Visit https://otrexpressgroup.com/carriers/ to confirm.

REQUIREMENTS
------------
- PHP 7.4+ (Hostinger default)
- cURL extension (Hostinger default)
- Outbound HTTPS to api.telegram.org and script.google.com (default)

TELEGRAM
--------
Already configured inside submit.php. To change later, edit the
two constants near the top:
    $TELEGRAM_BOT_TOKEN = '...';
    $TELEGRAM_CHAT_ID   = '...';

GOOGLE SHEETS (one-time setup)
------------------------------
Both this /carriers form AND the separate driver-application form on
otrexpressgroup.com write to the SAME spreadsheet:
  https://docs.google.com/spreadsheets/d/1QEgUDPC_FMIEvTe6Oik9CKyhGZ9OQJCoyn5VSa3LlQY/edit

  - /carriers form           -> tab "carriers"
  - driver-application form  -> tab "website"

Both tabs are auto-created on the first submission.

Step 1. Open the spreadsheet logged in as the OWNER account.
Step 2. Extensions -> Apps Script.
Step 3. Delete the sample code. Paste the contents of
        google-apps-script.gs from this folder. Save.
Step 4. Deploy -> New deployment -> Web app
          - Execute as:     Me
          - Who has access: Anyone  (NOT "Anyone with Google account")
        Deploy, authorize, copy the resulting /exec URL.
Step 5. On Hostinger, edit submit.php (File Manager -> Edit) and paste
        the /exec URL into:
            $SHEETS_WEBHOOK_URL = '...';
        Save.
Step 6. Submit a test lead from /carriers/. A new row should appear in
        the "carriers" tab (the tab is created automatically on the
        first submission).
Step 7. Use the same /exec URL inside the driver-application PHP on the
        otrexpressgroup.com main site — see DRIVER-APP-SHEETS-SNIPPET.txt.
Step 8. Delete test-sheets.php from Hostinger once everything is verified.

CHANGING THINGS LATER
---------------------
- Change a tab name: edit the 'sheet_name' value in the PHP file
  ('carriers' in this submit.php; 'website' in the driver-app PHP).
  No Apps Script change needed.
- Change the shared secret: update SECRET in google-apps-script.gs,
  redeploy, then update $SHEETS_SECRET in every PHP file to match.
- Re-deploy after editing the Apps Script:
  Deploy -> Manage deployments -> pencil icon -> Version: New version
  -> Deploy. The /exec URL stays the same; PHP needs no changes.

FAILURE BEHAVIOR
----------------
Telegram delivery runs first. If Google Sheets is ever unreachable
(network issue, script not deployed, etc.), the user still sees
"success" and you still get the lead in Telegram. A second Telegram
message will say "Sheets append failed — add the row manually."
