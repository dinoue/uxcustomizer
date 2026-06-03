# Build a release tarball for the GLPI plugin catalog.
# Output: dist/glpi-uxcustomizer-<VERSION>.tar.bz2

[CmdletBinding()]
param(
    [string]$Version
)

$ErrorActionPreference = 'Stop'

if (-not $Version) {
    $setup = Get-Content "$PSScriptRoot\setup.php" -Raw
    if ($setup -match "PLUGIN_UXCUSTOMIZER_VERSION',\s*'([^']+)'") {
        $Version = $Matches[1]
    } else {
        throw "Could not read PLUGIN_UXCUSTOMIZER_VERSION from setup.php"
    }
}

Write-Host "Building uxcustomizer $Version"

$plugin = 'uxcustomizer'
$work   = Join-Path $PSScriptRoot ".build"
$stage  = Join-Path $work $plugin
$dist   = Join-Path $PSScriptRoot "dist"
$out    = Join-Path $dist "glpi-$plugin-$Version.tar.bz2"

$ignoreFile = Join-Path $PSScriptRoot ".glpiignore"
$ignore = @()
if (Test-Path $ignoreFile) {
    $ignore = Get-Content $ignoreFile | Where-Object { $_ -and -not $_.StartsWith('#') }
}

if (Test-Path $work) { Remove-Item -Recurse -Force $work }
New-Item -ItemType Directory -Path $stage | Out-Null
New-Item -ItemType Directory -Path $dist -Force | Out-Null

$xd = @()
$xf = @()
foreach ($p in $ignore) {
    if ($p -match '[\\/]') {
        $xd += $p.TrimEnd('/').Replace('/', '\')
    } elseif ($p -like '*.*' -or $p -like '*?*') {
        $xf += $p
    } else {
        $xd += $p
        $xf += $p
    }
}

# ALWAYS exclude the staging + output dirs, even if .glpiignore doesn't list
# them. The staging dir (.build) lives INSIDE $PSScriptRoot, so without this
# robocopy /E copies it into itself recursively (.build/<plugin>/.build/...)
# until it hangs/fails. Pass absolute paths so /XD matches exactly.
$xd += $work       # .build
$xd += $dist       # dist

$rcArgs = @($PSScriptRoot, $stage, '/E', '/NFL', '/NDL', '/NJH', '/NJS', '/NP') + (@('/XD') + $xd) + (@('/XF') + $xf)
& robocopy @rcArgs | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy failed with code $LASTEXITCODE" }

Push-Location $work
try {
    & tar -cjf $out $plugin
    if ($LASTEXITCODE -ne 0) { throw "tar failed with code $LASTEXITCODE" }
} finally {
    Pop-Location
}

Remove-Item -Recurse -Force $work
Write-Host "Done: $out"
