param(
    [string]$Message = ""
)

$repo = "C:\Users\Public\Documents\ResultatsBridge"

Write-Host ""
Write-Host "=== Synchronisation ResultatsBridge ===" -ForegroundColor Yellow

# Message du commit
if ($Message -eq "") {
    $Message = Read-Host "Message du commit (ex: Ajout ecran Neuberg)"
}
if ($Message -eq "") {
    Write-Host "Message vide — abandon." -ForegroundColor Red
    exit 1
}

# 1. Copie Android (sans build/, .gradle/, .idea/, *.apk)
Write-Host ""
Write-Host "1. Android..." -ForegroundColor Cyan
robocopy "C:\Users\Public\Documents\oldProjects_android_studioold\TournoiBridgeOnline" `
         "$repo\android" `
         /MIR /XD build .gradle .idea /XF "*.apk" /NFL /NDL /NJH /NJS
Write-Host "   OK" -ForegroundColor Green

# 2. Copie PHP (sans config.php)
Write-Host "2. PHP / HTML..." -ForegroundColor Cyan
robocopy "C:\Users\Public\Documents\save_tournoi_bridge_online_www_asso\asso avec mitchell" `
         "$repo\web\asso avec mitchell" `
         /MIR /XF config.php /NFL /NDL /NJH /NJS
Write-Host "   OK" -ForegroundColor Green

# 3. Copie fichiers racine du site
Write-Host "3. Fichiers racine site..." -ForegroundColor Cyan
foreach ($f in @("index.html", "style.css", "script.js", "favicon.ico")) {
    $src = "C:\Users\Public\Documents\save_tournoi_bridge_online_www_asso\$f"
    if (Test-Path $src) {
        Copy-Item $src "$repo\web\$f" -Force
        Write-Host "   $f" -ForegroundColor Green
    }
}

# 4. Git : commit + push
Write-Host ""
Write-Host "4. Envoi vers GitHub..." -ForegroundColor Cyan
git -C "$repo" add --all

$status = git -C "$repo" status --porcelain
if (-not $status) {
    Write-Host "   Aucun changement detecte — rien a commiter." -ForegroundColor DarkYellow
} else {
    git -C "$repo" commit -m "$Message"
    if ($LASTEXITCODE -eq 0) {
        git -C "$repo" push
        Write-Host "   Push OK !" -ForegroundColor Green
    } else {
        Write-Host "   Echec du commit." -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "=== Termine ! ===" -ForegroundColor Yellow
