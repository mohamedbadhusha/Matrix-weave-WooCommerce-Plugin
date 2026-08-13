# Capture WordPress.org screenshots from the local Docker site with headless Chrome.
#
# Produces exact-size PNGs straight into wporg-assets/, ready for bin\svn-assets.ps1.
# Requires the stack to be up:  docker compose -f docker/docker-compose.yml up -d
#
# Usage:  .\docker\shoot.ps1

param(
    [int]$Width  = 1440,
    [int]$Height = 900,
    [string]$Base = 'http://localhost:8088'
)

$ErrorActionPreference = 'Stop'
$root   = Split-Path $PSScriptRoot -Parent
$out    = Join-Path $root 'wporg-assets'
$chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe'

if (-not (Test-Path $chrome)) { Write-Error "Chrome not found at $chrome" }
if (-not (Test-Path $out))    { New-Item -ItemType Directory -Force $out | Out-Null }

# demo_login=1 is handled by the mu-plugin in the container: it authenticates
# as user 1 and redirects to the same URL without the parameter.
$shots = @(
    # 1. Settings page, configured state — the plugin's main screen.
    @{ n = 'screenshot-1'; u = "$Base/wp-admin/admin.php?page=matrixweave&demo_login=1" },
    # 2. Taller capture so the Connect-your-catalog card and Advanced section fit.
    # Each headless run exits before flushing cookies, so every admin shot
    # needs demo_login=1 of its own.
    # Shot tall enough to fit the whole form, then crop off the trailing blank.
    @{ n = 'screenshot-2'; u = "$Base/wp-admin/admin.php?page=matrixweave&demo_login=1"; h = 1500; crop = 1080 },
    # 3. The shop with the chat panel open (demo_chat=1 is the mu-plugin helper).
    @{ n = 'screenshot-3'; u = "$Base/shop/?demo_chat=1" }
)

$profile = Join-Path $env:TEMP ('mw-shot-' + [guid]::NewGuid().ToString('N'))

foreach ($s in $shots) {
    $file = Join-Path $out ($s.n + '.png')
    $h    = if ($s.ContainsKey('h')) { $s.h } else { $Height }
    $w    = if ($s.ContainsKey('w')) { $s.w } else { $Width }
    Write-Host "Capturing $($s.n) <- $($s.u)"
    & $chrome `
        --headless=new `
        --disable-gpu `
        --hide-scrollbars `
        --force-device-scale-factor=1 `
        --user-data-dir="$profile" `
        --window-size="$w,$h" `
        --screenshot="$file" `
        --virtual-time-budget=6000 `
        $s.u | Out-Null

    if (Test-Path $file) {
        Add-Type -AssemblyName System.Drawing

        if ($s.ContainsKey('crop')) {
            $src  = [System.Drawing.Image]::FromFile($file)
            $bmp  = New-Object System.Drawing.Bitmap($src.Width, $s.crop)
            $gfx  = [System.Drawing.Graphics]::FromImage($bmp)
            $rect = New-Object System.Drawing.Rectangle(0, 0, $src.Width, $s.crop)
            $gfx.DrawImage($src, $rect, $rect, [System.Drawing.GraphicsUnit]::Pixel)
            $gfx.Dispose(); $src.Dispose()
            $tmp = "$file.tmp"
            $bmp.Save($tmp, [System.Drawing.Imaging.ImageFormat]::Png)
            $bmp.Dispose()
            Move-Item $tmp $file -Force
        }

        $img = [System.Drawing.Image]::FromFile($file)
        Write-Host ("  -> {0}  {1}x{2}" -f (Split-Path $file -Leaf), $img.Width, $img.Height) -ForegroundColor Green
        $img.Dispose()
    } else {
        Write-Host "  -> FAILED" -ForegroundColor Red
    }
}

Remove-Item $profile -Recurse -Force -ErrorAction SilentlyContinue
Write-Host "`nDone. Files are in $out" -ForegroundColor Cyan
