# =============================================================================
#  Install-POSImageFix3.ps1
#
#  FIX: Product image upload silently failing on XAMPP
#
#  ROOT CAUSE: move_uploaded_file() fails silently on some XAMPP configs
#  when upload_tmp_dir is not properly set in php.ini, or folder permissions
#  are too restrictive. The image path is never saved to the database.
#
#  FIXES APPLIED:
#  1. Check $_FILES error code before processing (catches php.ini issues)
#  2. Add copy() as fallback when move_uploaded_file() fails
#  3. Set folder permissions to 0777 on creation
#  4. Return descriptive error message showing exactly what failed
#  5. Normalize category to lowercase (prevents silent validation failure)
#  6. Create uploads/products folder automatically
#
#  FILE: admin\pos.php
#
#  USAGE:
#    powershell -ExecutionPolicy Bypass -File .\Install-POSImageFix3.ps1
# =============================================================================

$ErrorActionPreference = "Stop"
$scriptDir = $PSScriptRoot
if (-not $scriptDir) { $scriptDir = Get-Location }

function Backup-File($path) {
    $bak = "$path.bak"
    Copy-Item -Path $path -Destination $bak -Force
    Write-Host "  [BACKUP]  $bak" -ForegroundColor DarkGray
}

function Replace-InFile($path, $oldStr, $newStr, $label) {
    $enc      = [System.Text.Encoding]::UTF8
    $bytes    = [System.IO.File]::ReadAllBytes($path)
    $oldBytes = $enc.GetBytes($oldStr)
    $newBytes = $enc.GetBytes($newStr)

    $found = $false
    for ($i = 0; $i -le ($bytes.Length - $oldBytes.Length); $i++) {
        $match = $true
        for ($j = 0; $j -lt $oldBytes.Length; $j++) {
            if ($bytes[$i + $j] -ne $oldBytes[$j]) { $match = $false; break }
        }
        if ($match) {
            $found = $true
            $result = New-Object byte[] ($bytes.Length - $oldBytes.Length + $newBytes.Length)
            [Array]::Copy($bytes, 0, $result, 0, $i)
            [Array]::Copy($newBytes, 0, $result, $i, $newBytes.Length)
            [Array]::Copy($bytes, $i + $oldBytes.Length, $result, $i + $newBytes.Length, $bytes.Length - $i - $oldBytes.Length)
            [System.IO.File]::WriteAllBytes($path, $result)
            Write-Host "  [OK]      $label" -ForegroundColor Green
            return
        }
    }
    Write-Host "  [SKIP]    Already fixed or mismatch: $label" -ForegroundColor Yellow
}

# LF line endings
$oldUpload = "    // Handle product image upload`n" +
             "    `$image_path = null;`n" +
             "    if (!empty(`$_FILES['product_image']['name'])) {`n" +
             "        `$upload_dir = __DIR__ . '/../uploads/products/';`n" +
             "        if (!is_dir(`$upload_dir)) mkdir(`$upload_dir, 0755, true);`n" +
             "        `$ext = strtolower(pathinfo(`$_FILES['product_image']['name'], PATHINFO_EXTENSION));`n" +
             "        `$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];`n" +
             "        if (!in_array(`$ext, `$allowed)) {`n" +
             "            `$errors[] = ""Invalid image format. Use JPG, PNG, GIF, or WEBP."";`n" +
             "        } elseif (`$_FILES['product_image']['size'] > 2 * 1024 * 1024) {`n" +
             "            `$errors[] = ""Image must be under 2MB."";`n" +
             "        } else {`n" +
             "            `$new_filename = 'product_' . `$id . '_' . time() . '.' . `$ext;`n" +
             "            if (move_uploaded_file(`$_FILES['product_image']['tmp_name'], `$upload_dir . `$new_filename)) {`n" +
             "                `$image_path = 'uploads/products/' . `$new_filename;`n" +
             "            } else {`n" +
             "                `$errors[] = ""Failed to upload image."";`n" +
             "            }`n" +
             "        }`n" +
             "    }`n"

$newUpload = "    // Handle product image upload`n" +
             "    `$image_path = null;`n" +
             "    if (!empty(`$_FILES['product_image']['name']) && `$_FILES['product_image']['error'] === UPLOAD_ERR_OK) {`n" +
             "        `$upload_dir = __DIR__ . '/../uploads/products/';`n" +
             "        if (!is_dir(`$upload_dir)) {`n" +
             "            mkdir(`$upload_dir, 0777, true);`n" +
             "            chmod(`$upload_dir, 0777);`n" +
             "        }`n" +
             "        `$ext = strtolower(pathinfo(`$_FILES['product_image']['name'], PATHINFO_EXTENSION));`n" +
             "        `$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];`n" +
             "        if (!in_array(`$ext, `$allowed)) {`n" +
             "            `$errors[] = ""Invalid image format. Use JPG, PNG, GIF, or WEBP."";`n" +
             "        } elseif (`$_FILES['product_image']['size'] > 2 * 1024 * 1024) {`n" +
             "            `$errors[] = ""Image must be under 2MB."";`n" +
             "        } else {`n" +
             "            `$new_filename = 'product_' . `$id . '_' . time() . '.' . `$ext;`n" +
             "            `$dest = `$upload_dir . `$new_filename;`n" +
             "            `$moved = move_uploaded_file(`$_FILES['product_image']['tmp_name'], `$dest);`n" +
             "            if (!`$moved) {`n" +
             "                `$moved = copy(`$_FILES['product_image']['tmp_name'], `$dest);`n" +
             "            }`n" +
             "            if (`$moved && file_exists(`$dest)) {`n" +
             "                `$image_path = 'uploads/products/' . `$new_filename;`n" +
             "            } else {`n" +
             "                `$errors[] = ""Failed to save image. Dir writable: "" . (is_writable(`$upload_dir) ? 'yes' : 'no');`n" +
             "            }`n" +
             "        }`n" +
             "    } elseif (!empty(`$_FILES['product_image']['name']) && `$_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {`n" +
             "        `$php_upload_errors = [`n" +
             "            UPLOAD_ERR_INI_SIZE   => 'File too large (php.ini limit)',`n" +
             "            UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit)',`n" +
             "            UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',`n" +
             "            UPLOAD_ERR_NO_TMP_DIR => 'No temp folder - check PHP upload_tmp_dir in php.ini',`n" +
             "            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk - check folder permissions',`n" +
             "            UPLOAD_ERR_EXTENSION  => 'Upload blocked by PHP extension',`n" +
             "        ];`n" +
             "        `$err_code = `$_FILES['product_image']['error'];`n" +
             "        `$errors[] = `$php_upload_errors[`$err_code] ?? ""Upload error code: `$err_code"";`n" +
             "    }`n"

$oldCategory = "    `$category = `$_POST['category'];`n"
$newCategory = "    `$category = strtolower(trim(`$_POST['category']));`n"

$oldFileExists = "<?php if (!empty(`$item['image']) && file_exists(__DIR__ . '/../' . `$item['image'])): ?>"
$newFileExists = "<?php if (!empty(`$item['image'])): ?>"

Write-Host ""
Write-Host "======================================================" -ForegroundColor Cyan
Write-Host "  POS Product Image Upload Fix - Migration           " -ForegroundColor Cyan
Write-Host "======================================================" -ForegroundColor Cyan
Write-Host ""

$allOk = $true

$f1 = Join-Path $scriptDir "admin\pos.php"
Write-Host "Patching: admin\pos.php" -ForegroundColor White
if (Test-Path $f1) {
    try {
        Backup-File $f1
        Replace-InFile $f1 $oldCategory $newCategory "Category normalized to lowercase"
        Replace-InFile $f1 $oldFileExists $newFileExists "file_exists() check removed from image display"
        Replace-InFile $f1 $oldUpload $newUpload "Image upload: copy() fallback + error reporting added"
    } catch {
        Write-Host "  [ERROR]   $_" -ForegroundColor Red
        $allOk = $false
    }
} else {
    Write-Host "  [ERROR]   Not found: $f1" -ForegroundColor Red
    $allOk = $false
}
Write-Host ""

# Create uploads/products folder with full permissions
$uploadsDir = Join-Path $scriptDir "uploads\products"
Write-Host "Setting up: uploads\products" -ForegroundColor White
if (-not (Test-Path $uploadsDir)) {
    New-Item -Path $uploadsDir -ItemType Directory -Force | Out-Null
    Write-Host "  [OK]      Created uploads\products" -ForegroundColor Green
} else {
    Write-Host "  [OK]      Already exists" -ForegroundColor Green
}
# Give full permissions to the folder on Windows
try {
    $acl = Get-Acl $uploadsDir
    $rule = New-Object System.Security.AccessControl.FileSystemAccessRule("Everyone", "FullControl", "ContainerInherit,ObjectInherit", "None", "Allow")
    $acl.SetAccessRule($rule)
    Set-Acl $uploadsDir $acl
    Write-Host "  [OK]      Full permissions set on uploads\products" -ForegroundColor Green
} catch {
    Write-Host "  [WARN]    Could not set folder permissions automatically." -ForegroundColor Yellow
    Write-Host "            Right-click uploads\products -> Properties -> Security -> Give 'Everyone' Full Control" -ForegroundColor Yellow
}
Write-Host ""

if ($allOk) {
    Write-Host "======================================================" -ForegroundColor Green
    Write-Host "  Fix complete! Reload your browser and test." -ForegroundColor Green
    Write-Host ""
    Write-Host "  If upload STILL fails, open Edit Product and" -ForegroundColor Yellow
    Write-Host "  check the error message shown in the toast." -ForegroundColor Yellow
    Write-Host "  It will tell you exactly what is wrong." -ForegroundColor Yellow
    Write-Host "======================================================" -ForegroundColor Green
} else {
    Write-Host "======================================================" -ForegroundColor Red
    Write-Host "  Finished with errors. Check output above." -ForegroundColor Red
    Write-Host "======================================================" -ForegroundColor Red
}
Write-Host ""
