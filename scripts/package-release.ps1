param(
    [string]$Version = '4.2.0',
    [string]$Revision = 'HEAD',
    [string]$OutputDirectory = (Join-Path $PSScriptRoot '../dist')
)
$ErrorActionPreference = 'Stop'
if ($Version -notmatch '^\d+\.\d+\.\d+$') { throw 'Version must be x.y.z' }
Push-Location (Split-Path $PSScriptRoot -Parent)
try {
    New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
    $output = (Resolve-Path -LiteralPath $OutputDirectory).Path
    $name = 'BlueLotus_XSSReceiver_PRO-v' + $Version
    $zip = Join-Path $output ($name + '.zip')
    if (Test-Path -LiteralPath $zip) { throw 'Refusing to overwrite an existing package' }
    $phpFiles = @(git ls-tree --name-only $Revision | Where-Object { $_ -match '^[^/]+\.php$' -and $_ -ne 'config.php' })
    if ($LASTEXITCODE -ne 0) { throw 'Cannot inspect revision' }
    $paths = @('app','PHPMailer','static','template','myjs','docs','README.md','LICENSE','.htaccess','robots.txt','favicon.ico','data/.htaccess','data/index.html') + $phpFiles
    git archive --format=zip ('--prefix=' + $name + '/') -o $zip $Revision -- @paths
    if ($LASTEXITCODE -ne 0) { throw 'git archive failed' }
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive = [IO.Compression.ZipFile]::OpenRead($zip)
    try {
        foreach ($entry in $archive.Entries) {
            $relative = $entry.FullName.Substring(($name + '/').Length)
            if ($relative -match '^(config\.php|\.git/|\.claude/|tests/|scripts/|sessions/|\.playwright-mcp/)' -or
                ($relative -like 'data/*' -and $relative -notin @('data/','data/.htaccess','data/index.html'))) {
                throw ('Unexpected package entry: ' + $relative)
            }
        }
        if (!$archive.GetEntry($name + '/config-sample.php') -or !$archive.GetEntry($name + '/app/Storage/RecordId.php')) { throw 'Required runtime file missing' }
        Write-Output ('Verified deployment archive entries: ' + $archive.Entries.Count)
    } finally { $archive.Dispose() }
    $checksum = (Get-FileHash -LiteralPath $zip -Algorithm SHA256).Hash.ToLowerInvariant() + '  ' + $name + '.zip'
    [IO.File]::WriteAllText((Join-Path $output 'SHA256SUMS.txt'), $checksum + "`n", [Text.UTF8Encoding]::new($false))
    Write-Output $zip
} finally { Pop-Location }
