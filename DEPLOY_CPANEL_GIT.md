# Deploy to cPanel with Git

This project is ready for cPanel's **Git Version Control** deployment flow.

## 1. Prepare the project locally

Edit `.cpanel.yml` and replace:

```text
CPANEL_USERNAME
```

with your actual cPanel username.

Example:

```yaml
- export DEPLOYPATH=/home/myuser/public_html/leads-crm
```

## 2. Create the production database

In cPanel:

1. Open **MySQL Databases**.
2. Create a database, for example `myuser_leads_crm`.
3. Create a database user, for example `myuser_leads_user`.
4. Give the user **All Privileges** on the database.
5. Open **phpMyAdmin**.
6. Select the new database.
7. Import `database/mysql-schema.sql`.

## 3. Create production `.env`

In cPanel File Manager, after the first deployment, create:

```text
/home/YOUR_CPANEL_USERNAME/public_html/leads-crm/.env
```

Use this format:

```env
LEADS_CRM_WEBHOOK_TOKEN=replace-with-a-long-secret
LEADS_CRM_DB_DRIVER=mysql
LEADS_CRM_DB_HOST=localhost
LEADS_CRM_DB_NAME=your_db_name
LEADS_CRM_DB_USER=your_db_user
LEADS_CRM_DB_PASS=your_db_password
LEADS_CRM_DB_CHARSET=utf8mb4
```

Do not commit `.env` to Git.

## 4. Create a GitHub repo

From this folder:

```powershell
git init
git add .
git commit -m "Initial Leads CRM"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/leads-crm.git
git push -u origin main
```

If Git asks you to sign in, use GitHub's browser login or a personal access token.

## 5. Connect cPanel to GitHub

In cPanel:

1. Open **Git Version Control**.
2. Click **Create**.
3. Turn **Clone a Repository** on.
4. Repository URL:

```text
https://github.com/YOUR_USERNAME/leads-crm.git
```

5. Repository Path:

```text
/home/YOUR_CPANEL_USERNAME/repositories/leads-crm
```

6. Repository Name:

```text
leads-crm
```

7. Click **Create**.

## 6. Deploy

In cPanel Git Version Control:

1. Open the `leads-crm` repository.
2. Click **Pull or Deploy**.
3. Click **Update from Remote**.
4. Click **Deploy HEAD Commit**.

The `.cpanel.yml` file copies the app into:

```text
/home/YOUR_CPANEL_USERNAME/public_html/leads-crm
```

## 7. Open the app

```text
https://yourdomain.com/leads-crm/public/index.html
```

Admin:

```text
https://yourdomain.com/leads-crm/public/admin.html
```

MacroDroid incoming call URL:

```text
https://yourdomain.com/leads-crm/api/calls.php?action=incoming
```

MacroDroid call ended URL:

```text
https://yourdomain.com/leads-crm/api/calls.php?action=ended
```
