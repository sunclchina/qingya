@echo off
rem ============================================================
rem  Qingya theme one-click package script (Windows)
rem  Usage: double-click -> dist\qingya-v<ver>.zip
rem  Version is auto-read from qingya\style.css header
rem  Publish: create a GitHub Release tagged v<ver> and upload
rem  the generated zip (updater prefers release assets).
rem
rem  NOTE: zip entries MUST use forward slashes ("/"), otherwise
rem  WordPress reports "The theme is missing the style.css".
rem  .NET Framework's ZipFile.CreateFromDirectory writes "\"
rem  on Windows -> we build entries manually here.
rem ============================================================
setlocal
cd /d "%~dp0"

rem ---- read theme version (first line starting with "Version:") ----
for /f "tokens=2 delims=: " %%v in ('findstr /r /c:"^Version:" qingya\style.css') do set VER=%%v
if "%VER%"=="" (
  echo [Qingya] ERROR: cannot read Version from qingya\style.css
  pause
  exit /b 1
)
echo [Qingya] theme version: %VER%

rem ---- package (zip root dir = qingya, entries use "/") ----
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$src = Join-Path $PWD 'qingya';" ^
  "$out = Join-Path $PWD ('dist\qingya-v' + $env:VER + '.zip');" ^
  "Remove-Item $out -Force -ErrorAction SilentlyContinue;" ^
  "Add-Type -AssemblyName System.IO.Compression;" ^
  "Add-Type -AssemblyName System.IO.Compression.FileSystem;" ^
  "$fs = [System.IO.File]::Open($out, [System.IO.FileMode]::CreateNew);" ^
  "$zip = New-Object System.IO.Compression.ZipArchive($fs, [System.IO.Compression.ZipArchiveMode]::Create);" ^
  "Get-ChildItem $src -Recurse -File | Where-Object { $_.Name -ne '.gitignore' } | ForEach-Object {" ^
  "  $rel = $_.FullName.Substring($src.Length + 1).Replace('\','/');" ^
  "  $rel = 'qingya/' + $rel;" ^
  "  $entry = $zip.CreateEntry($rel, [System.IO.Compression.CompressionLevel]::Optimal);" ^
  "  $es = $entry.Open();" ^
  "  $bytes = [System.IO.File]::ReadAllBytes($_.FullName);" ^
  "  $es.Write($bytes, 0, $bytes.Length);" ^
  "  $es.Close();" ^
  "};" ^
  "$zip.Dispose(); $fs.Close();" ^
  "Write-Host ('[Qingya] output: ' + $out)"

if exist "dist\qingya-v%VER%.zip" (
  echo [Qingya] package done.
) else (
  echo [Qingya] ERROR: packaging failed, check dist dir.
)
pause
