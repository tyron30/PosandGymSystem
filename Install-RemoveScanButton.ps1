# =============================================================================
#  Install-RemoveScanButton.ps1
#
#  Removes the redundant "Scan QR Code" button and its modal scanner from
#  both admin\attendance.php and cashier\attendance.php.
#
#  WHY: The "Open Always-On Scanner" (qr_scanner_bg.php) is already a
#  purpose-built, full-screen, always-running QR scanner. The modal scanner
#  is strictly inferior and redundant. Removing it:
#    - Cleans up the UI (one fewer button to confuse staff)
#    - Removes the html5-qrcode library load (~200KB) from the page
#    - Eliminates camera lifecycle bugs that occur inside Bootstrap modals
#    - "Check In Member" (manual dropdown) is kept for edge cases
#
#  FILES:
#    admin\attendance.php   - Scan QR button + modal + JS removed
#    cashier\attendance.php - Scan QR button + modal + JS removed
#
#  USAGE:
#    powershell -ExecutionPolicy Bypass -File .\Install-RemoveScanButton.ps1
# =============================================================================

$ErrorActionPreference = "Stop"
$scriptDir = $PSScriptRoot
if (-not $scriptDir) { $scriptDir = Get-Location }

function Backup-File($path) {
    $bak = "$path.bak"
    Copy-Item -Path $path -Destination $bak -Force
    Write-Host "  [BACKUP]  $bak" -ForegroundColor DarkGray
}

function Remove-Pattern($content, $pattern, $label) {
    $result = [regex]::Replace($content, $pattern, '', [System.Text.RegularExpressions.RegexOptions]::Singleline)
    if ($result -ne $content) {
        Write-Host "  [OK]      Removed: $label" -ForegroundColor Green
        return $result
    } else {
        Write-Host "  [SKIP]    Already removed or not found: $label" -ForegroundColor Yellow
        return $content
    }
}

Write-Host ""
Write-Host "======================================================" -ForegroundColor Cyan
Write-Host "  Remove Redundant Scan QR Button - Migration        " -ForegroundColor Cyan
Write-Host "======================================================" -ForegroundColor Cyan
Write-Host ""

$allOk = $true

foreach ($file in @("admin\attendance.php", "cashier\attendance.php")) {
    $fp = Join-Path $scriptDir $file
    Write-Host "Patching: $file" -ForegroundColor White

    if (-not (Test-Path $fp)) {
        Write-Host "  [ERROR]   Not found: $fp" -ForegroundColor Red
        $allOk = $false
        Write-Host ""
        continue
    }

    try {
        Backup-File $fp
        $content = [System.IO.File]::ReadAllText($fp, [System.Text.Encoding]::UTF8)

        # 1. Remove the Scan QR Code button
        $content = Remove-Pattern $content `
            '\s*<button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#qrScannerModal">\s*<i class="fas fa-qrcode me-1"></i>Scan QR Code\s*</button>' `
            "Scan QR Code button"

        # 2. Remove the html5-qrcode library <script> tag
        $content = Remove-Pattern $content `
            '\s*<script src="https://unpkg\.com/html5-qrcode[^"]*"></script>' `
            "html5-qrcode library script tag"

        # 3. Remove the QR Scanner Modal HTML
        $content = Remove-Pattern $content `
            '\s*<!-- QR Scanner Modal -->.*?</div>\s*\r?\n\s*</div>\s*\r?\n\s*</div>\s*\r?\n\s*</div>\s*\r?\n(?=\s*<script)' `
            "QR Scanner modal HTML"

        # 4. Remove all QR scanner JS (scanner init + startScanner + hidden listener)
        $content = Remove-Pattern $content `
            '\s*// QR Scanner functionality.*?document\.getElementById\(''qrScannerModal''\)\.addEventListener\(''hidden\.bs\.modal''.*?\}\);(\s*)' `
            "QR Scanner JavaScript block"

        [System.IO.File]::WriteAllText($fp, $content, [System.Text.Encoding]::UTF8)

    } catch {
        Write-Host "  [ERROR]   $_" -ForegroundColor Red
        $allOk = $false
    }

    Write-Host ""
}

if ($allOk) {
    Write-Host "======================================================" -ForegroundColor Green
    Write-Host "  Done! Reload your browser to see the changes." -ForegroundColor Green
    Write-Host ""
    Write-Host "  What was removed:" -ForegroundColor Green
    Write-Host "    - 'Scan QR Code' button (admin + cashier)" -ForegroundColor Green
    Write-Host "    - QR scanner modal and its camera view" -ForegroundColor Green
    Write-Host "    - html5-qrcode library (~200KB page weight saved)" -ForegroundColor Green
    Write-Host "    - All scanner JS event listeners" -ForegroundColor Green
    Write-Host ""
    Write-Host "  What remains:" -ForegroundColor Green
    Write-Host "    - 'Check In Member' button (manual fallback)" -ForegroundColor Green
    Write-Host "    - 'Open Always-On Scanner' button (qr_scanner_bg.php)" -ForegroundColor Green
    Write-Host "======================================================" -ForegroundColor Green
} else {
    Write-Host "======================================================" -ForegroundColor Red
    Write-Host "  Finished with errors. Check output above." -ForegroundColor Red
    Write-Host "======================================================" -ForegroundColor Red
}
Write-Host ""
