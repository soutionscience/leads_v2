$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $root

$env:LEADS_CRM_SQLITE_PATH = "C:\tmp\leads-crm.sqlite"
php -c "$root\php-local.ini" -S 127.0.0.1:8088
