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
    $t = $p.Trim()
    if ($t -eq '') { continue }
    if ($t -match '[\\/]') {
        # Path-bearing entry → directory path exclude only.
        $xd += $t.TrimEnd('/').Replace('/', '\')
    } else {
        # A bare name can be a directory (.git, dist, node_modules) OR a file
        # / glob (*.swp, CLAUDE.md, RELEASE_NOTES*.md). The old heuristic keyed
        # off a dot, which misfiled DOT-DIRECTORIES like `.git` as files — so
        # robocopy /XF never matched the directory and the whole .git tree
        # shipped. Add every bare entry to BOTH lists; robocopy silently
        # ignores the one that doesn't match.
        $xd += $t
        $xf += $t
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

# Authoritative prune pass. robocopy's bare-name /XD and mid-name glob /XF
# matching proved unreliable (the whole .git tree shipped), and so did
# Get-ChildItem -Filter for dot-directories like `.git`. So we match on the
# entry NAME with PowerShell's -like, which is predictable. This is the real
# exclusion guarantee — robocopy above is just the first cheap pass.
$nameGlobs = @()
foreach ($p in $ignore) {
    $t = $p.Trim()
    if ($t -eq '') { continue }
    if ($t -match '[\\/]') {
        # Path-relative entry — remove that exact stage path.
        $target = Join-Path $stage ($t.TrimEnd('/').Replace('/', '\'))
        if (Test-Path -LiteralPath $target) {
            Remove-Item -LiteralPath $target -Recurse -Force -ErrorAction SilentlyContinue
        }
    } else {
        $nameGlobs += $t
    }
}
# Every .glpiignore target (.git, dist, CLAUDE.md, RELEASE_NOTES*.md, dot-files
# …) lives at the repo TOP LEVEL, so match top-level stage children by name
# with -like (predictable, unlike robocopy/Get-ChildItem -Filter) and remove
# them. Top-level only — avoids deep-recursion path-length errors under the
# long OneDrive base path that silently aborted the earlier attempts.
foreach ($child in @(Get-ChildItem -LiteralPath $stage -Force -ErrorAction SilentlyContinue)) {
    $drop = $false
    foreach ($g in $nameGlobs) {
        if ($child.Name -like $g) { $drop = $true; break }
    }
    if ($drop -and (Test-Path -LiteralPath $child.FullName)) {
        Remove-Item -LiteralPath $child.FullName -Recurse -Force -ErrorAction SilentlyContinue
    }
}

Push-Location $work
try {
    & tar -cjf $out $plugin
    if ($LASTEXITCODE -ne 0) { throw "tar failed with code $LASTEXITCODE" }
} finally {
    Pop-Location
}

Remove-Item -Recurse -Force $work
Write-Host "Done: $out"
