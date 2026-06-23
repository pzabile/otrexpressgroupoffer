HOSTINGER UPLOAD INSTRUCTIONS
=============================

Files in this folder:
  - index.html              (the page itself)
  - submit.php              (form handler; sends leads to Telegram + Google Sheets)
  - logo.png                (company logo)
  - .htaccess               (small Apache config)
  - google-apps-script.gs   (paste into your sheet — see step 4 below; DO NOT
                             upload this file to Hostinger, it stays in Google)

HOW TO DEPLOY ON HOSTINGER
--------------------------
1. Log in to your Hostinger hPanel.
2. Open "File Manager" for your domain (otrexpressgroup.com).
3. Go into the "public_html" folder.
4. Create a new folder named:  offer
5. Open that "offer" folder.
6. Upload ALL files from this directory into public_html/offer/
     (you can drag-and-drop, or use the Upload button)
7. Make sure file permissions look like this (Hostinger default is fine):
     - .html / .php / .png / .htaccess  ->  644
     - the "offer" folder itself        ->  755

That's it. Your page will be live at:
  https://otrexpressgroup.com/offer/

REQUIREMENTS
------------
- PHP 7.4 or newer (Hostinger has this by default)
- cURL extension enabled (Hostinger has this by default)
- Outbound HTTPS allowed to api.telegram.org (allowed by default)

TESTING THE FORM
----------------
1. Open https://otrexpressgroup.com/offer/
2. Scroll to the form, fill it out, hit "Get Drivers Now".
3. You should receive a PDF inside Telegram chat ID 325385972
   from the bot with token 8517526106:AAH3q0...

If you don't receive it:
  - Confirm cURL is enabled in hPanel -> Advanced -> PHP Configuration.
  - Confirm your Telegram bot has been started (send /start to it once
    from your own account so it can message you).

CHANGING THE TELEGRAM CREDENTIALS
---------------------------------
Edit submit.php — the first two constants near the top:
    $TELEGRAM_BOT_TOKEN = '...';
    $TELEGRAM_CHAT_ID   = '...';

GOOGLE SHEETS SAVE (one-time setup)
-----------------------------------
Leads are also appended to:
  https://docs.google.com/spreadsheets/d/1QEgUDPC_FMIEvTe6Oik9CKyhGZ9OQJCoyn5VSa3LlQY/edit
(Sheet name: Sheet1)

Step 1. Open that sheet in your browser while logged into the Google
        account that OWNS the sheet.
Step 2. Menu:  Extensions  ->  Apps Script.
Step 3. Delete the sample code. Open google-apps-script.gs from THIS folder,
        copy its entire contents, paste it into the Apps Script editor,
        and save (Ctrl+S or Cmd+S). Name the project anything you like.
Step 4. Click the blue "Deploy" button (top right)  ->  "New deployment".
          - Type:           Web app    (click the gear icon to pick it)
          - Description:    OTR offer lead webhook
          - Execute as:     Me
          - Who has access: Anyone   (REQUIRED — not "Anyone with Google account")
        Click Deploy. Google will ask you to authorize the script — accept.
        Copy the resulting "Web app URL". It looks like:
          https://script.google.com/macros/s/AKfyc.../exec
Step 5. Open submit.php on Hostinger (File Manager -> Edit) and:
          - Paste the URL into  $SHEETS_WEBHOOK_URL = '...';
          - Optional: change $SHEETS_SECRET in BOTH files to match.
        Save.
Step 6. Submit a test lead from /offer/. A new row should appear in Sheet1.

How it behaves:
  - Telegram delivery always runs first. If Google Sheets save fails for any
    reason (Apps Script down, network issue), the user still sees success
    and you get the lead in Telegram. You'll also get a Telegram heads-up
    message saying "Google Sheets append failed — add the row manually."
  - To turn the Sheets save OFF, just blank out $SHEETS_WEBHOOK_URL.
  - To change the sheet/tab, edit SHEET_ID / SHEET_NAME at the top of
    google-apps-script.gs and re-deploy a new version.
