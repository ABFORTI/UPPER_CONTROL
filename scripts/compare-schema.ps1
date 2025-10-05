Param(
    [string]$DbA = 'upper_control',
    [string]$DbB = 'upper_control_fresh'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$mysqldump = 'C:\xampp\mysql\bin\mysqldump.exe'

if (-not (Test-Path $mysqldump)) { throw "No se encontró mysqldump en $mysqldump" }

Write-Host "-> Generando esquema (sin datos) de '$DbA' y '$DbB'..." -ForegroundColor Cyan
$a = & $mysqldump -u root --no-data --skip-comments --skip-add-drop-table $DbA
$b = & $mysqldump -u root --no-data --skip-comments --skip-add-drop-table $DbB

# Normalizar espacios y orden para una comparación simple
$na = ($a -split "`n" | ForEach-Object { $_.Trim() }) -join "`n"
$nb = ($b -split "`n" | ForEach-Object { $_.Trim() }) -join "`n"

Write-Host '-> Diferencias (A=upper_control, B=upper_control_fresh):' -ForegroundColor Yellow
[System.Environment]::NewLine
Compare-Object -ReferenceObject ($na -split "`n") -DifferenceObject ($nb -split "`n") -IncludeEqual:$false | Format-Table -AutoSize
