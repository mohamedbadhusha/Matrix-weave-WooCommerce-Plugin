# Prepare the WordPress.org SVN working copy for a release.
#
# Checks out the plugin's SVN repo (anonymous — no credentials needed), syncs the
# runtime files into trunk/, and stages them with `svn add`. It deliberately STOPS
# before committing: the commit and the tag are the two steps that need your
# WordPress.org SVN username and password, so you run those yourself.
#
# Usage:  .\bin\svn-prepare.ps1            (uses the version in the plugin header)
#         .\bin\svn-prepare.ps1 -Version 1.1.3

param(
    [string]$Version  = '',
    [string]$Slug     = 'matrixweave-for-woocommerce',
    [string]$Checkout = 'D:\Matrixweave\Plugins\svn-matrixweave-for-woocommerce'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$sep  = [string][IO.Path]::DirectorySeparatorChar

if (-not (Get-Command svn -ErrorAction SilentlyContinue)) {
    Write-Error "No svn client found. Install one first (see bin/README-svn.md), then reopen the terminal."
}

# Read the version straight out of the plugin header so it can never drift.
if (-not $Version) {
    $header = Get-Content (Join-Path $root "$Slug.php") -TotalCount 30
    $match  = $header | Select-String -Pattern '^\s*\*\s*Version:\s*(.+?)\s*$'
    if (-not $match) { Write-Error "Could not read Version from the plugin header." }
    $Version = $match.Matches[0].Groups[1].Value
}

# The readme Stable tag must match the tag we publish, or the directory serves nothing.
$stable = (Get-Content (Join-Path $root 'readme.txt') -TotalCount 20 |
           Select-String -Pattern '^Stable tag:\s*(.+?)\s*$').Matches[0].Groups[1].Value
if ($stable -ne $Version) {
    Write-Error "readme.txt Stable tag ($stable) does not match the plugin version ($Version). Fix that before releasing."
}

Write-Host "Releasing $Slug $Version" -ForegroundColor Cyan

if (-not (Test-Path $Checkout)) {
    Write-Host "Checking out https://plugins.svn.wordpress.org/$Slug ..."
    svn checkout "https://plugins.svn.wordpress.org/$Slug" $Checkout
} else {
    Write-Host "Updating existing checkout at $Checkout ..."
    svn update $Checkout
}

$trunk = Join-Path $Checkout 'trunk'
if (-not (Test-Path $trunk)) { New-Item -ItemType Directory -Force $trunk | Out-Null }

# Clear trunk of tracked files so a removed file doesn't linger in the release,
# but leave .svn metadata alone.
Get-ChildItem $trunk -Force | Where-Object { $_.Name -ne '.svn' } | ForEach-Object {
    Remove-Item -LiteralPath $_.FullName -Recurse -Force
}

# Same exclusion list as the zip build: runtime files only.
$exclude = @('dist', 'bin', 'docker', 'wporg-assets', '.git', '.github', '.gitignore', 'README.md', 'CHANGELOG.md')
Get-ChildItem $root -Force | Where-Object { $exclude -notcontains $_.Name } | ForEach-Object {
    Copy-Item $_.FullName -Destination $trunk -Recurse -Force
}

Push-Location $Checkout
try {
    # Stage adds and deletes. `svn add --force` is a no-op on already-tracked files.
    svn add trunk --force --no-ignore | Out-Null
    $missing = svn status | Where-Object { $_ -match '^!' } | ForEach-Object { ($_ -split '\s+', 2)[1] }
    foreach ($m in $missing) { svn delete $m | Out-Null }

    Write-Host "`nStaged changes:" -ForegroundColor Cyan
    svn status
} finally {
    Pop-Location
}

Write-Host @"

------------------------------------------------------------------
Ready. Run these two yourself — they will prompt for your SVN
password (set it at profiles.wordpress.org, it is NOT your
wordpress.org login password):

  cd "$Checkout"
  svn commit -m "Release $Version" --username mugamathubathusha

  svn copy "https://plugins.svn.wordpress.org/$Slug/trunk" "https://plugins.svn.wordpress.org/$Slug/tags/$Version" -m "Tag $Version" --username mugamathubathusha

The tag is required: readme.txt says Stable tag: $Version, so the
directory serves /tags/$Version/ — without it your page stays empty.
------------------------------------------------------------------
"@ -ForegroundColor Green
