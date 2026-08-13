# Publish WordPress.org directory assets (icon, banner, screenshots).
#
# Drop your images in wporg-assets/ using the exact filenames below, then run this.
# It validates every image's real pixel dimensions BEFORE staging — wrong-sized
# files are the number one reason assets silently fail to appear on the plugin page.
#
# These live in assets/ at the SVN ROOT, not in trunk. They are not versioned with
# releases: the newest commit wins, and the page updates within minutes.
#
# Usage:  .\bin\svn-assets.ps1

param(
    [string]$Slug     = 'matrixweave-for-woocommerce',
    [string]$Checkout = 'D:\Matrixweave\Plugins\svn-matrixweave-for-woocommerce'
)

$ErrorActionPreference = 'Stop'
$root   = Split-Path $PSScriptRoot -Parent
$source = Join-Path $root 'wporg-assets'
$svn    = 'C:\Program Files\TortoiseSVN\bin\svn.exe'

if (-not (Test-Path $svn)) { $svn = 'svn' }
if (-not (Test-Path $source)) { Write-Error "No wporg-assets folder at $source" }
if (-not (Test-Path $Checkout)) { Write-Error "No SVN checkout at $Checkout - run bin\svn-prepare.ps1 first" }

Add-Type -AssemblyName System.Drawing

# Required exact dimensions. Screenshots are any size (16:9-ish reads best).
$spec = @{
    'icon-128x128.png'   = @(128, 128)
    'icon-256x256.png'   = @(256, 256)
    'banner-772x250.png' = @(772, 250)
    'banner-1544x500.png'= @(1544, 500)
}

$assets = Join-Path $Checkout 'assets'
if (-not (Test-Path $assets)) { New-Item -ItemType Directory -Force $assets | Out-Null }

$found = Get-ChildItem $source -File | Where-Object { $_.Extension -match '\.(png|jpg|jpeg|svg)$' }
if (-not $found) { Write-Error "No images found in $source" }

$problems = @()
foreach ($f in $found) {
    $name = $f.Name

    if ($name -match '^(icon|banner|screenshot-\d+)') {
        # ok
    } else {
        $problems += "$name - not a recognised asset name (icon-*, banner-*, screenshot-N)"
        continue
    }

    if ($f.Extension -ne '.svg') {
        $img = [System.Drawing.Image]::FromFile($f.FullName)
        $dim = @($img.Width, $img.Height)
        $img.Dispose()

        if ($spec.ContainsKey($name)) {
            $want = $spec[$name]
            if ($dim[0] -ne $want[0] -or $dim[1] -ne $want[1]) {
                $problems += "$name - is $($dim[0])x$($dim[1]), must be exactly $($want[0])x$($want[1])"
                continue
            }
        }
        Write-Host ("  OK  {0,-22} {1}x{2}" -f $name, $dim[0], $dim[1]) -ForegroundColor Green
    } else {
        Write-Host ("  OK  {0,-22} (svg)" -f $name) -ForegroundColor Green
    }

    Copy-Item $f.FullName -Destination $assets -Force
}

if ($problems) {
    Write-Host "`nProblems - these were NOT staged:" -ForegroundColor Yellow
    $problems | ForEach-Object { Write-Host "  $_" -ForegroundColor Yellow }
}

Push-Location $Checkout
try {
    & $svn add assets --force --no-ignore | Out-Null
    Write-Host "`nStaged:" -ForegroundColor Cyan
    & $svn status assets
} finally {
    Pop-Location
}

Write-Host @"

------------------------------------------------------------------
Run this yourself to publish the assets:

  cd "$Checkout"
  & "$svn" commit assets -m "Update directory assets" --username mugamathubathusha

The plugin page picks them up within a few minutes. Assets are NOT
tied to a release - no version bump needed.
------------------------------------------------------------------
"@ -ForegroundColor Green
