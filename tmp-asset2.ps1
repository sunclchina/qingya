$ErrorActionPreference = "Stop"
$cred = "protocol=https`nhost=github.com`n" | git credential fill 2>$null
$token = (($cred -split "`n") | Where-Object { $_ -like "password=*" }) -replace "password=",""
if (-not $token) { throw "no token" }
$headers = @{ Authorization = "***"; Accept = "application/vnd.github+json" }

# 1) 删除旧 asset（重试 5 次）
$deleted = $false
foreach ($i in 1..5) {
    try {
        Invoke-RestMethod -Uri "https://api.github.com/repos/sunclchina/qingya/releases/assets/514221714" -Method Delete -Headers $headers -TimeoutSec 30 | Out-Null
        $deleted = $true
        break
    } catch {
        Write-Output "delete try$i failed: $($_.Exception.Message)"
        Start-Sleep -Seconds 5
    }
}
if (-not $deleted) { throw "delete failed after retries" }
Write-Output "old asset deleted"

# 2) 上传新 zip（重试 5 次）
$uploadHeaders = @{ Authorization = "***"; "Content-Type" = "application/zip" }
$asset = $null
foreach ($i in 1..5) {
    try {
        $asset = Invoke-RestMethod -Uri "https://uploads.github.com/repos/sunclchina/qingya/releases/370475463/assets?name=qingya-v1.10.12.zip" -Method Post -Headers $uploadHeaders -InFile "E:\my-project\qingya\qingya-v1.10.12.zip" -TimeoutSec 60
        break
    } catch {
        Write-Output "upload try$i failed: $($_.Exception.Message)"
        Start-Sleep -Seconds 5
    }
}
if (-not $asset) { throw "upload failed after retries" }
Write-Output "new asset_id=$($asset.id) size=$($asset.size)"

# 3) 验证 latest release 的 asset
$rel = Invoke-RestMethod -Uri "https://api.github.com/repos/sunclchina/qingya/releases/latest" -Headers $headers -TimeoutSec 30
Write-Output "latest=$($rel.tag_name) assets=$($rel.assets | ForEach-Object { $_.name + ':' + $_.size })"
