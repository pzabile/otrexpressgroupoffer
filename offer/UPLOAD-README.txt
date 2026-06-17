HOSTINGER UPLOAD INSTRUCTIONS
=============================

Files in this folder:
  - index.html        (the page itself)
  - submit.php        (form handler; sends leads as PDF to Telegram)
  - logo.png          (company logo)
  - .htaccess         (small Apache config)

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
