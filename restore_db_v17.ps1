$ErrorActionPreference = "Stop"

# URLs for Portable PostgreSQL Binaries (EDB) - Trying v17.2
$zipUrl = "https://get.enterprisedb.com/postgresql/postgresql-17.2-1-windows-x64-binaries.zip"
$zipFile = "pgsql17.zip"
$extractPath = "pgsql17_tools"
$pgRestore = "D:\Codes\alertdavao\pgsql17_tools\pgsql\bin\pg_restore.exe"
$targetDir = "D:\Codes\alertdavao\db_temp\2026-02-09T16_26Z\alertdavao_pi0t"

# 1. Download
if (-not (Test-Path $zipFile)) {
    Write-Host "Downloading PostgreSQL 17 Binaries..."
    Invoke-WebRequest -Uri $zipUrl -OutFile $zipFile
} else {
    Write-Host "Zip already downloaded."
}

# 2. Extract
if (-not (Test-Path $pgRestore)) {
    Write-Host "Extracting..."
    Expand-Archive -Path $zipFile -DestinationPath $extractPath -Force
} else {
    Write-Host "Already extracted."
}

# 3. Restore
Write-Host "Running Database Restore with PG 17..."
$env:PGPASSWORD='HZrEOYtxn3E4BTeYMEmQ1Tw2ZUWjxtjG'

& $pgRestore --format=d --verbose --clean --no-acl --no-owner `
    -h dpg-d6989315pdvs738deie0-a.singapore-postgres.render.com `
    -U alertdavao_9sip_user `
    -d alertdavao_9sip `
    -p 5432 `
    "$targetDir"
