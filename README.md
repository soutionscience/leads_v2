# Leads CRM

A cPanel-friendly PHP leads CRM for call-first sales workflows.

## What is included

- Today-first leads dashboard.
- Manual lead creation.
- MacroDroid webhook endpoints for incoming and ended calls.
- Live call timer for active calls.
- Product quick search from `data/products.json`.
- Delivery area quick search from `data/delivery_areas.json`.
- Resolution and follow-up tracking.
- Contact classification for customers, suppliers, delivery riders, staff, and spam.
- Ignored contact list so supplier/delivery/staff calls do not create leads.
- Admin stats for average daily leads, conversion rate, follow-up rate, ignored calls, and top products.
- Local SQLite by default, with MySQL schema for cPanel.

## Run locally

From this folder:

```powershell
.\start-local.ps1
```

The script stores the local SQLite database at `C:\tmp\leads-crm.sqlite` to avoid OneDrive file-locking issues.

Open:

```text
http://127.0.0.1:8088/public/index.html
```

Admin:

```text
http://127.0.0.1:8088/public/admin.html
```

If you prefer not to use the script, run PHP with the local config file:

```powershell
$env:LEADS_CRM_SQLITE_PATH="C:\tmp\leads-crm.sqlite"
php -c .\php-local.ini -S 127.0.0.1:8088
```

## MacroDroid webhook setup

Create one macro for incoming/ringing calls:

- Trigger: Incoming Call or Call Started.
- Action: HTTP Request.
- Method: POST.
- URL while local: `http://YOUR-PC-IP:8088/api/calls.php?action=incoming`
- Content type: JSON.
- Body:

```json
{
  "token": "change-me",
  "phone": "[call_number]",
  "action": "incoming"
}
```

Create another macro for ended calls:

- Trigger: Call Ended.
- Action: HTTP Request.
- Method: POST.
- URL while local: `http://YOUR-PC-IP:8088/api/calls.php?action=ended`
- Content type: JSON.
- Body:

```json
{
  "token": "change-me",
  "phone": "[call_number]",
  "duration_seconds": "[call_duration]",
  "action": "ended"
}
```

MacroDroid variable names can differ by device/version. Use MacroDroid's variable picker for the caller number and duration fields.

## cPanel deployment path

For Git deployment, follow `DEPLOY_CPANEL_GIT.md`.

1. Create a MySQL database and user in cPanel.
2. Import `database/mysql-schema.sql` into phpMyAdmin.
3. Upload this folder to your hosting account.
4. Edit `.env` or set environment variables:
   - `LEADS_CRM_DB_DRIVER=mysql`
   - `LEADS_CRM_DB_HOST=localhost`
   - `LEADS_CRM_DB_NAME=your_db`
   - `LEADS_CRM_DB_USER=your_user`
   - `LEADS_CRM_DB_PASS=your_password`
   - `LEADS_CRM_WEBHOOK_TOKEN=your_secret`
5. Point MacroDroid to:

```text
https://yourdomain.com/leads-crm/api/calls.php?action=incoming
https://yourdomain.com/leads-crm/api/calls.php?action=ended
```

For your subdomain deployment, use:

```text
https://leads.solutionscience.co.ke/api/calls.php?action=incoming
https://leads.solutionscience.co.ke/api/calls.php?action=ended
```

## Zoho later

Contacts already include:

- `external_source`
- `external_contact_id`
- `last_synced_at`

Those fields are reserved for linking a local caller to Zoho contact data later.
