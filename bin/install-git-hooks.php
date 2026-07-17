<?php

// bin/install-git-hooks.php

$repoRoot = dirname(__DIR__);

// 1) Environment guards
if (getenv('CI')) {
    echo "CI environment detected. Skipping git hooks installation.\n";
    exit(0);
}

$appEnv = getenv('APP_ENV') ?: getenv('LARAVEL_ENV');
if ($appEnv === 'production') {
    echo "APP_ENV=production. Skipping git hooks installation.\n";
    exit(0);
}

// 2) Ensure we're in a git repo
$gitDir = trim(shell_exec('git rev-parse --git-dir 2>/dev/null') ?? '');
if ($gitDir === '') {
    echo "Not a git repository. Skipping git hooks installation.\n";
    exit(0);
}

// Use git's idea of the hooks dir (respects core.hooksPath)
$gitHooksDir = rtrim($gitDir, '/').'/hooks';
if (! is_dir($gitHooksDir)) {
    echo "Git hooks directory not found at {$gitHooksDir}. Skipping hook install.\n";
    exit(0);
}

// 3) Source dir for our canonical hooks
$hooksSourceDir = $repoRoot.'/bin/git-hooks';
if (! is_dir($hooksSourceDir)) {
    echo "No hooks directory found at {$hooksSourceDir}. Skipping hook install.\n";
    exit(0);
}

$hookFiles = scandir($hooksSourceDir);

foreach ($hookFiles as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }

    $src = $hooksSourceDir.'/'.$file;
    $dest = $gitHooksDir.'/'.$file;

    if (! is_file($src)) {
        continue;
    }

    if (! copy($src, $dest)) {
        echo "Failed to copy hook {$file}.\n";

        continue;
    }

    if (function_exists('chmod')) {
        @chmod($dest, 0755);
    }

    echo "Installed git hook: {$file}\n";
}

echo "Git hooks installation finished.\n";
