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
# Each headless run exits before flushing cookies, so EVERY admin shot needs its
# own demo_login=1. `demo_state` (mu-demo-state.php) decides whether the stored
# keys are present, which is what selects the connected vs. fresh screens — the
# markup is the plugin's own either way.
#
# Order matters: it is the order they appear in the directory listing, so it
# should read like the setup actually goes — connect, confirm, tune, use.
$shots = @(
    # 1. The 1.2.0 headline: a fresh install, one button, nothing to paste.
    @{ n = 'screenshot-1'; u = "$Base/wp-admin/admin.php?page=matrixweave&demo_login=1&demo_state=fresh" },
    # 2. Connected — plan, usage, a real product count and a live order-lookup
    #    check. This is the "honest status" screen that replaced a stored flag.
    @{ n = 'screenshot-2'; u = "$Base/wp-admin/admin.php?page=matrixweave&demo_login=1&demo_state=connected" },
    # 3. The keys card + the "connect by hand" sidebar — the escape hatch for
    #    moving a site between workspaces, or a host that blocks the round trip.
    #    Offsets measured against the real 1440-wide layout, not guessed.
    @{ n = 'screenshot-3'; u = "$Base/wp-admin/admin.php?page=matrixweave&demo_login=1&demo_state=connected"; h = 1600; y = 460; crop = 740 },
    # 4. Behaviour: embed toggle, order lookups, agent mode, colour, greeting.
    @{ n = 'screenshot-4'; u = "$Base/wp-admin/admin.php?page=matrixweave&demo_login=1&demo_state=connected"; h = 1600; y = 800; crop = 580 },
    # 5. The point of all of it — the agent open on the storefront.
    #    ⚠️ Needs a REAL pk_ in the site's settings: the widget is loaded by the
    #    browser, so the mu-plugin's server-side stub cannot fake it. With a
    #    placeholder key the panel never renders — which is why the previous
    #    release shipped only two of its three promised screenshots.
    @{ n = 'screenshot-5'; u = "$Base/shop/?demo_chat=1" }
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

        # `crop` is the kept height; optional `y` is where it starts, so a shot
        # can frame a section further down the page rather than always the top.
        if ($s.ContainsKey('crop')) {
            $src  = [System.Drawing.Image]::FromFile($file)
            $y    = if ($s.ContainsKey('y')) { $s.y } else { 0 }
            $keep = [Math]::Min($s.crop, $src.Height - $y)
            $bmp  = New-Object System.Drawing.Bitmap($src.Width, $keep)
            $gfx  = [System.Drawing.Graphics]::FromImage($bmp)
            $dest = New-Object System.Drawing.Rectangle(0, 0, $src.Width, $keep)
            $from = New-Object System.Drawing.Rectangle(0, $y, $src.Width, $keep)
            $gfx.DrawImage($src, $dest, $from, [System.Drawing.GraphicsUnit]::Pixel)
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
