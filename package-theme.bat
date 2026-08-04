@echo off
rem ============================================================
rem  Qingya theme one-click package script (Windows)
rem  Usage: double-click -> dist\qingya-v<ver>.zip
rem  Version is auto-read from qingya\style.css header
rem  Publish: create a GitHub Release tagged v<ver> and upload
rem  the generated zip (updater prefers release assets).
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

rem ---- package (zip root dir = qingya) ----
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$tmp = Join-Path $PWD 'dist\_pkg_tmp'; Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue;" ^
  "New-Item -ItemType Directory -Path (Join-Path $tmp 'qingya') -Force | Out-Null;" ^
  "Copy-Item (Join-Path $PWD 'qingya\*') (Join-Path $tmp 'qingya\') -Recurse -Force;" ^
  "$out = Join-Path $PWD ('dist\qingya-v%VER%.zip');" ^
  "Remove-Item $out -Force -ErrorAction SilentlyContinue;" ^
  "Add-Type -AssemblyName System.IO.Compression.FileSystem;" ^
  "[System.IO.Compression.ZipFile]::CreateFromDirectory($tmp, $out);" ^
  "Remove-Item $tmp -Recurse -Force;" ^
  "Write-Host ('[Qingya] output: ' + $out)"

if exist "dist\qingya-v%VER%.zip" (
  echo [Qingya] package done.
) else (
  echo [Qingya] ERROR: packaging failed, check dist dir.
)
pause
