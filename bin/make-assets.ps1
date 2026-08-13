# Generate the WordPress.org icon and banner files from the company logo assets.
#
#   icons   <- "App Icon.png"        (already a finished square app icon)
#   banners <- "logo-icon-1024.png"  (bare mark on transparency) + brand background
#
# Icons are resampled with System.Drawing; banners are composed in HTML and
# rendered by headless Chrome at their exact final size, so text stays crisp
# rather than being upscaled.
#
# Usage:  .\bin\make-assets.ps1

param(
    [string]$LogoDir = 'D:\Matrixweave\Company Data\logo'
)

$ErrorActionPreference = 'Stop'
$root   = Split-Path $PSScriptRoot -Parent
$out    = Join-Path $root 'wporg-assets'
$chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe'

if (-not (Test-Path $out)) { New-Item -ItemType Directory -Force $out | Out-Null }
Add-Type -AssemblyName System.Drawing

# ---------------------------------------------------------------- icons -----
# The mascot, not the MW monogram: at icon size the directory shows a grid of
# flat logos, and a face reads as "AI agent" where a monogram reads as nothing.
# Cropped to the head — the full body loses all detail below ~200px.
$iconSrc  = Join-Path (Split-Path $LogoDir -Parent) 'Ai agent avator.png'
$cropX    = 238
$cropY    = 0
$cropSize = 752

foreach ($size in @(256, 128)) {
    $src = [System.Drawing.Image]::FromFile($iconSrc)
    # 24bpp on purpose: the source icon is fully opaque, so an alpha channel adds
    # nothing but writes the PNG as colortype 6 instead of 2. Keeping it flat
    # matches the banners and screenshots the directory ingested without trouble.
    $bmp = New-Object System.Drawing.Bitmap($size, $size, [System.Drawing.Imaging.PixelFormat]::Format24bppRgb)
    $bmp.SetResolution(72, 72)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.InterpolationMode  = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.PixelOffsetMode    = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $g.SmoothingMode      = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
    $g.DrawImage(
        $src,
        (New-Object System.Drawing.Rectangle(0, 0, $size, $size)),
        (New-Object System.Drawing.Rectangle($cropX, $cropY, $cropSize, $cropSize)),
        [System.Drawing.GraphicsUnit]::Pixel
    )
    $g.Dispose(); $src.Dispose()
    $file = Join-Path $out "icon-${size}x${size}.png"
    $bmp.Save($file, [System.Drawing.Imaging.ImageFormat]::Png)
    $bmp.Dispose()
    Write-Host ("  icon-{0}x{0}.png" -f $size) -ForegroundColor Green
}

# --------------------------------------------------------------- banners ----
$markBytes = [System.IO.File]::ReadAllBytes((Join-Path $LogoDir 'logo-icon-1024.png'))
$mark      = 'data:image/png;base64,' + [Convert]::ToBase64String($markBytes)

# Two sizes, same layout, scaled by a single root font-size multiplier so the
# 1544x500 file is a true render rather than an upscale of the small one.
$banners = @(
    @{ w = 772;  h = 250; k = 1 },
    @{ w = 1544; h = 500; k = 2 }
)

foreach ($b in $banners) {
    $k = $b.k
    $html = @"
<!doctype html><html><head><meta charset="utf-8"><style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    width:$($b.w)px; height:$($b.h)px; overflow:hidden;
    background:
      repeating-linear-gradient(0deg,  rgba(255,255,255,.045) 0 1px, transparent 1px $([int](38*$k))px),
      repeating-linear-gradient(90deg, rgba(255,255,255,.045) 0 1px, transparent 1px $([int](38*$k))px),
      linear-gradient(115deg, #00007F 0%, #14208f 55%, #1c2fb4 100%);
    font-family: 'Segoe UI Semibold','Segoe UI',system-ui,sans-serif;
    color:#fff; display:flex; align-items:center;
    padding-left:$([int](46*$k))px; gap:$([int](26*$k))px;
  }
  /* The mark is blue on transparency, so it disappears against a blue
     background. A white card lifts it out — the same treatment the brand
     deck uses for content on blue. */
  .badge { background:#fff; border-radius:$([int](20*$k))px; padding:$([int](12*$k))px;
           display:flex; box-shadow:0 $([int](3*$k))px $([int](14*$k))px rgba(0,0,0,.28); }
  img { width:$([int](84*$k))px; height:auto; display:block; }
  .name { font-size:$([int](40*$k))px; font-weight:700; letter-spacing:$([int](2*$k))px; line-height:1; }
  .for  { font-size:$([int](20*$k))px; font-weight:400; letter-spacing:$([int](1*$k))px;
          line-height:1; margin-top:$([int](9*$k))px; color:#c7d2fe; }
  .tag  { font-size:$([int](13*$k))px; font-weight:400; letter-spacing:$([int](1*$k))px;
          margin-top:$([int](12*$k))px; color:#93a4f4; }
</style></head><body>
  <div class="badge"><img src="$mark" alt=""></div>
  <div>
    <div class="name">MATRIXWEAVE</div>
    <div class="for">AI Sales &amp; Support Agent for WooCommerce</div>
    <div class="tag">Structure. Connect. Automate.</div>
  </div>
</body></html>
"@

    $tmpHtml = Join-Path $env:TEMP ("mw-banner-$($b.w).html")
    [System.IO.File]::WriteAllText($tmpHtml, $html, [System.Text.UTF8Encoding]::new($false))

    $file    = Join-Path $out "banner-$($b.w)x$($b.h).png"
    $profile = Join-Path $env:TEMP ('mw-banner-' + [guid]::NewGuid().ToString('N'))

    & $chrome --headless=new --disable-gpu --hide-scrollbars --force-device-scale-factor=1 `
              --user-data-dir="$profile" --window-size="$($b.w),$($b.h)" `
              --screenshot="$file" --virtual-time-budget=3000 "file:///$($tmpHtml -replace '\\','/')" | Out-Null

    Remove-Item $profile -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item $tmpHtml -Force -ErrorAction SilentlyContinue

    if (Test-Path $file) {
        $img = [System.Drawing.Image]::FromFile($file)
        Write-Host ("  banner-{0}x{1}.png  -> {2}x{3}" -f $b.w, $b.h, $img.Width, $img.Height) -ForegroundColor Green
        $img.Dispose()
    } else {
        Write-Host ("  banner-{0}x{1}.png FAILED" -f $b.w, $b.h) -ForegroundColor Red
    }
}

Write-Host "`nDone. Files are in $out" -ForegroundColor Cyan
