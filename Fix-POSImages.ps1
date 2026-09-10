# ==============================================================================
#  Fix-POSImages.ps1  —  v3 (clean restore + patch)
#  Place this script in your PosandGymSystem folder and run as Administrator
# ==============================================================================

$ErrorActionPreference = "Stop"

# ── FIND PROJECT ROOT ─────────────────────────────────────────────────────────
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
if (Test-Path "$scriptDir\admin\pos.php") {
    $projectRoot = $scriptDir
} else {
    $htdocs = "C:\xampp\htdocs"
    $projectRoot = $null
    foreach ($c in (Get-ChildItem $htdocs -Directory)) {
        $inner = Join-Path $c.FullName "PosandGymSystem"
        if (Test-Path "$inner\admin\pos.php") { $projectRoot = $inner; break }
        if (Test-Path "$($c.FullName)\admin\pos.php") { $projectRoot = $c.FullName; break }
    }
}

if (-not $projectRoot -or -not (Test-Path "$projectRoot\admin\pos.php")) {
    Write-Host "Cannot find project. Enter full path to PosandGymSystem folder:" -ForegroundColor Yellow
    $projectRoot = Read-Host "Path"
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  POS Image Fix  (v3 - clean)" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Root: $projectRoot" -ForegroundColor White
Write-Host ""

# ── STEP 1: BACKUP ────────────────────────────────────────────────────────────
Write-Host "[1/4] Backing up files..." -ForegroundColor Yellow
$ts  = Get-Date -Format "yyyyMMdd_HHmmss"
$bak = "$projectRoot\backups\$ts"
New-Item -ItemType Directory -Path $bak -Force | Out-Null
Copy-Item "$projectRoot\admin\pos.php"   "$bak\admin_pos.php.bak"
Copy-Item "$projectRoot\cashier\pos.php" "$bak\cashier_pos.php.bak"
Write-Host "    Backups saved to: $bak" -ForegroundColor Green

# ── STEP 2: FIX UPLOAD FOLDER ─────────────────────────────────────────────────
Write-Host ""
Write-Host "[2/4] Ensuring uploads folder exists with correct permissions..." -ForegroundColor Yellow
$uploadDir = "$projectRoot\uploads\products"
if (-not (Test-Path $uploadDir)) {
    New-Item -ItemType Directory -Path $uploadDir -Force | Out-Null
}
try {
    $acl  = Get-Acl $uploadDir
    $rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
        "Everyone","FullControl","ContainerInherit,ObjectInherit","None","Allow")
    $acl.SetAccessRule($rule)
    Set-Acl $uploadDir $acl
    Write-Host "    $uploadDir  [FullControl set]" -ForegroundColor Green
} catch {
    Write-Host "    $uploadDir  [permissions skipped - run as Admin]" -ForegroundColor DarkYellow
}

# ── STEP 3: PATCH FILES WITH PYTHON ───────────────────────────────────────────
Write-Host ""
Write-Host "[3/4] Applying patches..." -ForegroundColor Yellow

$adminPhp   = "$projectRoot\admin\pos.php"
$cashierPhp = "$projectRoot\cashier\pos.php"

# Write the Python patcher script to a temp file
$pyScript = "$env:TEMP\pos_patcher.py"

$pyCode = @'
import sys

def patch_admin(path):
    with open(path, 'r', encoding='utf-8') as f:
        c = f.read()

    count = 0

    # FIX 1: INSERT includes image column directly
    o1 = '''        $stmt = $conn->prepare("INSERT INTO pos_items (name, category, price, stock_quantity) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssdi", $name, $category, $price, $stock_quantity);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Product added successfully']);

            // If image was uploaded, update it now with the new product id
            if ($image_path !== null) {
                $last_id = $conn->insert_id;
                $img_stmt = $conn->prepare("UPDATE pos_items SET image = ? WHERE id = ?");
                $img_stmt->bind_param("si", $image_path, $last_id);
                $img_stmt->execute();
                $img_stmt->close();
            }

        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding product: ' . $stmt->error]);
        }
        $stmt->close();'''
    n1 = '''        $stmt = $conn->prepare("INSERT INTO pos_items (name, category, price, stock_quantity, image) VALUES (?, ?, ?, ?, ?)");
        $img_val = $image_path ?? null;
        $stmt->bind_param("ssdis", $name, $category, $price, $stock_quantity, $img_val);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Product added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding product: ' . $stmt->error]);
        }
        $stmt->close();'''
    if o1 in c:
        c = c.replace(o1, n1); count += 1; print("  [A] Fixed: INSERT includes image")
    else:
        print("  [A] Skipped: INSERT already fixed or not matched")

    # FIX 2: Preserve current_image on edit
    o2 = '''    if (empty($errors)) {
        if ($image_path !== null) {
            $stmt = $conn->prepare("UPDATE pos_items SET name = ?, category = ?, price = ?, stock_quantity = ?, image = ? WHERE id = ?");
            $stmt->bind_param("ssdiis", $name, $category, $price, $stock_quantity, $image_path, $id);
        } else {
            $stmt = $conn->prepare("UPDATE pos_items SET name = ?, category = ?, price = ?, stock_quantity = ? WHERE id = ?");
            $stmt->bind_param("ssdii", $name, $category, $price, $stock_quantity, $id);
        }'''
    n2 = '''    // Preserve existing image if no new file was uploaded
    if ($image_path === null && isset($_POST['current_image']) && $_POST['current_image'] !== '' && $_POST['current_image'] !== '0') {
        $image_path = $_POST['current_image'];
    }

    if (empty($errors)) {
        if ($image_path !== null) {
            $stmt = $conn->prepare("UPDATE pos_items SET name = ?, category = ?, price = ?, stock_quantity = ?, image = ? WHERE id = ?");
            $stmt->bind_param("ssdiis", $name, $category, $price, $stock_quantity, $image_path, $id);
        } else {
            $stmt = $conn->prepare("UPDATE pos_items SET name = ?, category = ?, price = ?, stock_quantity = ? WHERE id = ?");
            $stmt->bind_param("ssdii", $name, $category, $price, $stock_quantity, $id);
        }'''
    if o2 in c:
        c = c.replace(o2, n2); count += 1; print("  [B] Fixed: preserve current_image on edit")
    else:
        print("  [B] Skipped: current_image already fixed or not matched")

    # FIX 3: Image display check excludes '0'
    o3 = "<?php if (!empty($item['image'])): ?>"
    n3 = "<?php if (!empty($item['image']) && $item['image'] !== '0'): ?>"
    if o3 in c:
        c = c.replace(o3, n3); count += 1; print("  [C] Fixed: image display check")
    else:
        print("  [C] Skipped: display check already fixed")

    # FIX 4: JS sends current_image in edit FormData
    o4 = "            // Append image file if selected\n            const imageFile = document.getElementById('editProductImage').files[0];\n            if (imageFile) {\n                formData.append('product_image', imageFile);\n            }"
    n4 = "            // Preserve existing image if no new file selected\n            const currentImg = document.getElementById('editProductImagePreview').dataset.currentImage || '';\n            formData.append('current_image', currentImg);\n\n            // Append image file if selected\n            const imageFile = document.getElementById('editProductImage').files[0];\n            if (imageFile) {\n                formData.append('product_image', imageFile);\n            }"
    if o4 in c:
        c = c.replace(o4, n4); count += 1; print("  [D] Fixed: JS sends current_image")
    else:
        print("  [D] Skipped: JS already sends current_image")

    with open(path, 'w', encoding='utf-8') as f:
        f.write(c)
    print(f"  admin/pos.php saved ({count} patches)")

def patch_cashier(path):
    with open(path, 'r', encoding='utf-8') as f:
        c = f.read()
    count = 0
    o = "<?php if (!empty($item['image'])): ?>"
    n = "<?php if (!empty($item['image']) && $item['image'] !== '0'): ?>"
    if o in c:
        c = c.replace(o, n); count += 1; print("  [A] Fixed: cashier image display check")
    else:
        print("  [A] Skipped: cashier display check already fixed")
    with open(path, 'w', encoding='utf-8') as f:
        f.write(c)
    print(f"  cashier/pos.php saved ({count} patches)")

admin_path   = sys.argv[1]
cashier_path = sys.argv[2]
print("--- Patching admin/pos.php ---")
patch_admin(admin_path)
print("--- Patching cashier/pos.php ---")
patch_cashier(cashier_path)
print("DONE")
'@

[System.IO.File]::WriteAllText($pyScript, $pyCode, [System.Text.Encoding]::UTF8)

# Run Python patcher
$pythonExe = $null
foreach ($p in @("python", "python3", "C:\Python312\python.exe", "C:\Python311\python.exe", "C:\Python310\python.exe")) {
    try {
        $v = & $p --version 2>&1
        if ($v -match "Python") { $pythonExe = $p; break }
    } catch {}
}

# XAMPP includes Python? Try that too
if (-not $pythonExe) {
    $xamppPy = "C:\xampp\python\python.exe"
    if (Test-Path $xamppPy) { $pythonExe = $xamppPy }
}

if ($pythonExe) {
    Write-Host "    Using Python: $pythonExe" -ForegroundColor Gray
    & $pythonExe $pyScript $adminPhp $cashierPhp
} else {
    Write-Host "    Python not found - applying patches via PowerShell..." -ForegroundColor DarkYellow

    # PowerShell fallback - read, patch, write
    $admin = [System.IO.File]::ReadAllText($adminPhp)
    $patched = 0

    # Fix 3 (simplest - just a PHP string comparison)
    $o3 = "<?php if (!empty(`$item['image'])): ?>"
    $n3 = "<?php if (!empty(`$item['image']) && `$item['image'] !== '0'): ?>"
    if ($admin.Contains($o3)) {
        $admin = $admin.Replace($o3, $n3); $patched++
        Write-Host "    [C] Fixed: image display check" -ForegroundColor Green
    }

    [System.IO.File]::WriteAllText($adminPhp, $admin, [System.Text.Encoding]::UTF8)
    Write-Host "    $patched PowerShell patches applied to admin/pos.php" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "    NOTE: Copy the pre-patched pos.php provided separately" -ForegroundColor Yellow
    Write-Host "    for the full fix (Python not available on this machine)" -ForegroundColor Yellow
}

Remove-Item $pyScript -ErrorAction SilentlyContinue

# ── STEP 4: GENERATE SQL ──────────────────────────────────────────────────────
Write-Host ""
Write-Host "[4/4] Scanning uploads and generating SQL..." -ForegroundColor Yellow

$imgFiles = @()
if (Test-Path $uploadDir) {
    $imgFiles = Get-ChildItem $uploadDir -File |
        Where-Object { $_.Extension -match '\.(jpg|jpeg|png|gif|webp)$' }
}

$lines = [System.Collections.Generic.List[string]]::new()
$lines.Add("-- Generated by Fix-POSImages.ps1 on $(Get-Date)")
$lines.Add("-- Run in phpMyAdmin: gym_db > SQL tab")
$lines.Add("")
$lines.Add("-- 1. Fix column default")
$lines.Add("ALTER TABLE ``pos_items`` MODIFY COLUMN ``image`` VARCHAR(255) NULL DEFAULT NULL;")
$lines.Add("UPDATE ``pos_items`` SET ``image`` = NULL WHERE ``image`` = '0' OR ``image`` = '';")
$lines.Add("")

if ($imgFiles.Count -gt 0) {
    $grouped = @{}
    foreach ($f in $imgFiles) {
        if ($f.Name -match '^product_(\d+)_(\d+)') {
            $id = $Matches[1]; $ts = [long]$Matches[2]
            if (-not $grouped.ContainsKey($id) -or $ts -gt $grouped[$id].ts) {
                $grouped[$id] = @{ name = $f.Name; ts = $ts }
            }
        }
    }
    if ($grouped.Count -gt 0) {
        $lines.Add("-- 2. Link existing uploaded images to products")
        foreach ($id in ($grouped.Keys | Sort-Object { [int]$_ })) {
            $fn = $grouped[$id].name
            $lines.Add("UPDATE ``pos_items`` SET ``image`` = 'uploads/products/$fn' WHERE id = $id AND (image IS NULL OR image = '' OR image = '0');")
        }
        $lines.Add("")
        Write-Host "    $($grouped.Count) product image(s) mapped" -ForegroundColor Green
    }
}

$lines.Add("-- 3. Verify")
$lines.Add("SELECT id, name, image FROM ``pos_items`` WHERE image IS NOT NULL ORDER BY id;")

$sqlPath = "$projectRoot\fix_orphaned_images.sql"
[System.IO.File]::WriteAllLines($sqlPath, $lines, [System.Text.Encoding]::UTF8)
Write-Host "    SQL saved: fix_orphaned_images.sql" -ForegroundColor Green

# ── SUMMARY ───────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  DONE!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "  NEXT STEPS:" -ForegroundColor White
Write-Host "  1. Open phpMyAdmin > gym_db > SQL tab" -ForegroundColor White
Write-Host "  2. Run: fix_orphaned_images.sql  (in your project folder)" -ForegroundColor Yellow
Write-Host "  3. Press Ctrl+Shift+R on the POS page to hard refresh" -ForegroundColor White
Write-Host ""
Write-Host "  Backups: $bak" -ForegroundColor Gray
Write-Host ""
Read-Host "Press Enter to exit"
