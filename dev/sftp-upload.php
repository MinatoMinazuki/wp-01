<?php

declare(strict_types=1);

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;

const EXIT_OK = 0;
const EXIT_ERROR = 1;

main($argv);

function main(array $argv): void
{
    if (PHP_SAPI !== 'cli') {
        fail('This script can only be run from the command line.', 'USAGE_ERROR');
    }

    $root = normalizeLocalPath(__DIR__);
    $options = parseArguments($argv);
    setVerbose($options['verbose']);

    if ($options['help']) {
        printUsage();
        exit(EXIT_OK);
    }

    if (!$options['check_connection'] && $options['source'] === null) {
        printUsage();
        fail('Source path is required.', 'USAGE_ERROR');
    }

    if ($options['check_connection'] && $options['source'] !== null) {
        fail('--check-connection does not take a source path.', 'USAGE_ERROR');
    }

    $env = loadConfig($root . DIRECTORY_SEPARATOR . '.env');
    $remoteBase = requireConfig($env, 'SFTP_REMOTE_DEV_DIR');

    if ($options['check_connection']) {
        $sftp = connectSftp($env);
        ensureRemoteBaseExists($sftp, normalizeRemoteBase($remoteBase));
        logEvent('connection_check_completed', ['remote_base' => normalizeRemoteBase($remoteBase)]);
        echo PHP_EOL . 'Connection check completed. Remote dev directory is accessible: ' . normalizeRemoteBase($remoteBase) . PHP_EOL;
        exit(EXIT_OK);
    }

    $source = resolveSourcePath($options['source'], $root);
    $remoteOverride = $options['remote'] !== null ? normalizeRelativeRemotePath($options['remote']) : null;
    $files = collectUploadFiles($source, $root, $remoteOverride);

    if ($files === []) {
        fail('No uploadable files found.', 'SOURCE_EMPTY');
    }

    foreach ($files as $index => $file) {
        $files[$index]['remote'] = joinRemotePath($remoteBase, $file['remote_relative']);
    }

    writeSummary($files, $options);
    logEvent('prepared', [
        'dry_run' => $options['dry_run'],
        'force' => $options['force'],
        'verbose' => $options['verbose'],
        'source' => $source,
        'file_count' => count($files),
    ]);

    if ($options['dry_run']) {
        echo PHP_EOL . 'Dry-run only. Remote existence was not checked and no files were uploaded.' . PHP_EOL;
        exit(EXIT_OK);
    }

    $sftp = connectSftp($env);
    ensureRemoteBaseExists($sftp, normalizeRemoteBase($remoteBase));
    guardRemoteConflicts($sftp, $files, $options['force']);
    uploadFiles($sftp, $files, $options['force']);

    logEvent('completed', [
        'force' => $options['force'],
        'file_count' => count($files),
    ]);

    echo PHP_EOL . 'Upload completed: ' . count($files) . ' file(s).' . PHP_EOL;
    exit(EXIT_OK);
}

function parseArguments(array $argv): array
{
    $options = [
        'source' => null,
        'dry_run' => false,
        'force' => false,
        'help' => false,
        'remote' => null,
        'verbose' => false,
        'check_connection' => false,
    ];

    $positionals = [];
    $count = count($argv);

    for ($i = 1; $i < $count; $i++) {
        $arg = $argv[$i];

        if ($arg === '--dry-run') {
            $options['dry_run'] = true;
            continue;
        }

        if ($arg === '--force') {
            $options['force'] = true;
            continue;
        }

        if ($arg === '--verbose' || $arg === '-v') {
            $options['verbose'] = true;
            continue;
        }

        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
            continue;
        }

        if ($arg === '--check-connection') {
            $options['check_connection'] = true;
            continue;
        }

        if ($arg === '--remote') {
            if (!isset($argv[$i + 1])) {
                fail('--remote requires a relative remote path.', 'USAGE_ERROR');
            }
            $options['remote'] = $argv[++$i];
            continue;
        }

        if (str_starts_with($arg, '--remote=')) {
            $options['remote'] = substr($arg, strlen('--remote='));
            continue;
        }

        if (str_starts_with($arg, '--')) {
            fail('Unknown option: ' . $arg, 'USAGE_ERROR');
        }

        $positionals[] = $arg;
    }

    if (count($positionals) > 1) {
        fail('Only one source path can be specified.', 'USAGE_ERROR');
    }

    $options['source'] = $positionals[0] ?? null;

    return $options;
}

function printUsage(): void
{
    echo <<<'USAGE'
Usage:
  php sftp-upload.php <source> [--dry-run] [--force] [--verbose] [--remote <relative-remote-path>]
  php sftp-upload.php --check-connection [--verbose]

Examples:
  php sftp-upload.php wp-content/themes/theme-name --dry-run
  php sftp-upload.php wp-content/themes/theme-name --force
  php sftp-upload.php wp-content/themes/theme-name --force --verbose
  php sftp-upload.php index.php --remote public/index.php
  php sftp-upload.php --check-connection --verbose

Rules:
  - Source must resolve inside this dev directory.
  - Remote target must stay inside SFTP_REMOTE_DEV_DIR.
  - Directories are uploaded recursively.
  - Existing remote files require --force.
  - Symlinks and excluded files/directories are skipped.

USAGE;
}

function loadConfig(string $path): array
{
    $env = [];

    if (is_file($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            fail('Unable to read .env file.', 'CONFIG_ERROR', ['path' => $path]);
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $equalsAt = strpos($line, '=');

            if ($equalsAt === false) {
                continue;
            }

            $key = trim(substr($line, 0, $equalsAt));
            $value = trim(substr($line, $equalsAt + 1));

            if ($key === '') {
                continue;
            }

            if (
                strlen($value) >= 2
                && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $env[$key] = $value;
        }
    }

    foreach (configKeys() as $key) {
        $value = getenv($key);

        if ($value !== false) {
            $env[$key] = $value;
        }
    }

    return $env;
}

function configKeys(): array
{
    return [
        'SFTP_HOST',
        'SFTP_PORT',
        'SFTP_USER',
        'SFTP_PRIVATE_KEY',
        'SFTP_PASSPHRASE',
        'SFTP_PASSWORD',
        'SFTP_REMOTE_DEV_DIR',
        'SFTP_HOST_FINGERPRINT',
    ];
}

function requireConfig(array $env, string $key): string
{
    $value = trim((string)($env[$key] ?? ''));

    if ($value === '') {
        fail('Missing required config value: ' . $key, 'CONFIG_ERROR', ['key' => $key]);
    }

    return $value;
}

function resolveSourcePath(string $sourceArg, string $root): string
{
    $sourceArg = trim($sourceArg);

    if ($sourceArg === '') {
        fail('Source path is empty.', 'USAGE_ERROR');
    }

    $candidate = isAbsoluteLocalPath($sourceArg)
        ? $sourceArg
        : $root . DIRECTORY_SEPARATOR . $sourceArg;

    $real = realpath($candidate);

    if ($real === false) {
        fail('Source path does not exist: ' . $sourceArg, 'SOURCE_NOT_FOUND', ['source' => $sourceArg]);
    }

    $real = normalizeLocalPath($real);

    if (!isPathInside($real, $root)) {
        fail('Source path must be inside the dev directory: ' . $sourceArg, 'SOURCE_OUTSIDE_DEV', [
            'source' => $sourceArg,
            'resolved' => $real,
            'dev_root' => $root,
        ]);
    }

    if (is_link($real)) {
        fail('Source path is a symlink and cannot be uploaded.', 'SOURCE_SYMLINK', ['source' => $real]);
    }

    return $real;
}

function collectUploadFiles(string $source, string $root, ?string $remoteOverride): array
{
    $sourceRelative = localRelativePath($source, $root);
    $files = [];

    if (is_file($source)) {
        if (isExcludedPath($sourceRelative)) {
            fail('Source path is excluded by policy: ' . $sourceRelative, 'SOURCE_EXCLUDED', ['source' => $sourceRelative]);
        }

        $remoteRelative = $remoteOverride !== null
            ? remoteTargetForFile($remoteOverride, basename($source))
            : localRelativeToRemote($sourceRelative);

        return [[
            'local' => $source,
            'relative' => $sourceRelative,
            'remote_relative' => $remoteRelative,
        ]];
    }

    if (!is_dir($source)) {
        fail('Source path must be a file or directory.', 'SOURCE_INVALID', ['source' => $source]);
    }

    if (isExcludedPath($sourceRelative)) {
        fail('Source path is excluded by policy: ' . $sourceRelative, 'SOURCE_EXCLUDED', ['source' => $sourceRelative]);
    }

    $directory = new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS);
    $filter = new RecursiveCallbackFilterIterator(
        $directory,
        static function (SplFileInfo $item) use ($root): bool {
            $path = normalizeLocalPath($item->getPathname());
            $relative = localRelativePath($path, $root);

            if ($item->isLink()) {
                logEvent('skipped_symlink', ['path' => $relative]);
                return false;
            }

            if (isExcludedPath($relative)) {
                logEvent('skipped_excluded', ['path' => $relative]);
                return false;
            }

            return true;
        }
    );
    $iterator = new RecursiveIteratorIterator(
        $filter,
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $path = normalizeLocalPath($item->getPathname());
        $relative = localRelativePath($path, $root);

        if (!$item->isFile()) {
            continue;
        }

        $innerRelative = localRelativePath($path, $source);
        $remoteRelative = $remoteOverride !== null
            ? joinRelativeRemotePath($remoteOverride, localRelativeToRemote($innerRelative))
            : localRelativeToRemote($relative);

        $files[] = [
            'local' => $path,
            'relative' => $relative,
            'remote_relative' => $remoteRelative,
        ];
    }

    usort($files, static fn (array $a, array $b): int => strcmp($a['relative'], $b['relative']));

    return $files;
}

function isExcludedPath(string $relativePath): bool
{
    $relativePath = str_replace('\\', '/', $relativePath);
    $segments = array_values(array_filter(explode('/', $relativePath), static fn (string $part): bool => $part !== ''));
    $blockedSegments = ['.git', '.svn', '.hg', 'node_modules', 'vendor', 'logs', 'cache', 'tmp'];

    foreach ($segments as $segment) {
        if (in_array($segment, $blockedSegments, true)) {
            return true;
        }
    }

    $basename = basename($relativePath);

    if ($basename === '.env' || str_starts_with($basename, '.env.')) {
        return true;
    }

    return in_array($basename, ['sftp-upload.php', 'composer.json', 'composer.lock', 'composer.phar'], true);
}

function remoteTargetForFile(string $remoteOverride, string $basename): string
{
    $remoteOverride = normalizeRelativeRemotePath($remoteOverride);

    if (str_ends_with($remoteOverride, '/')) {
        return joinRelativeRemotePath(rtrim($remoteOverride, '/'), $basename);
    }

    return $remoteOverride;
}

function connectSftp(array $env): SFTP
{
    $autoload = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    if (!is_file($autoload)) {
        fail('Missing vendor/autoload.php. Run: composer install', 'DEPENDENCY_ERROR', ['autoload' => $autoload]);
    }

    require_once $autoload;

    $host = requireConfig($env, 'SFTP_HOST');
    $port = (int)($env['SFTP_PORT'] ?? 22);
    $user = requireConfig($env, 'SFTP_USER');

    if ($port <= 0 || $port > 65535) {
        fail('SFTP_PORT must be between 1 and 65535.', 'CONFIG_ERROR', ['port' => $port]);
    }

    echo PHP_EOL . 'Connecting to ' . $host . ':' . $port . ' ...' . PHP_EOL;
    logEvent('sftp_connect_start', ['host' => $host, 'port' => $port]);

    try {
        $sftp = new SFTP($host, $port, 15);
    } catch (Throwable $exception) {
        fail('Unable to initialize SFTP connection: ' . $exception->getMessage(), 'CONNECT_ERROR', [
            'host' => $host,
            'port' => $port,
            'exception' => get_class($exception),
        ]);
    }

    verifyHostFingerprint($sftp, trim((string)($env['SFTP_HOST_FINGERPRINT'] ?? '')));

    $privateKeyPath = trim((string)($env['SFTP_PRIVATE_KEY'] ?? ''));
    $password = (string)($env['SFTP_PASSWORD'] ?? '');

    if ($privateKeyPath !== '') {
        $keyPath = resolvePrivateKeyPath($privateKeyPath);
        $passphrase = (string)($env['SFTP_PASSPHRASE'] ?? '');
        $keyMaterial = file_get_contents($keyPath);

        if ($keyMaterial === false) {
            fail('Unable to read private key: ' . $privateKeyPath, 'CONFIG_ERROR', ['private_key' => $privateKeyPath]);
        }

        try {
            $key = PublicKeyLoader::loadPrivateKey($keyMaterial, $passphrase !== '' ? $passphrase : false);
        } catch (Throwable $exception) {
            fail('Unable to load private key: ' . $exception->getMessage(), 'AUTH_KEY_ERROR', [
                'private_key' => $privateKeyPath,
                'exception' => get_class($exception),
            ]);
        }

        logEvent('sftp_auth_start', ['method' => 'private_key', 'user' => $user]);

        try {
            $loggedIn = $sftp->login($user, $key);
        } catch (Throwable $exception) {
            failSftp('SFTP connection or private-key login failed: ' . $exception->getMessage(), 'CONNECT_ERROR', $sftp, [
                'user' => $user,
                'method' => 'private_key',
                'private_key' => $privateKeyPath,
                'exception' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);
        }

        if (!$loggedIn) {
            failSftp('SFTP login failed using private key.', 'AUTH_ERROR', $sftp, [
                'user' => $user,
                'method' => 'private_key',
                'private_key' => $privateKeyPath,
            ]);
        }
    } elseif ($password !== '') {
        logEvent('sftp_auth_start', ['method' => 'password', 'user' => $user]);

        try {
            $loggedIn = $sftp->login($user, $password);
        } catch (Throwable $exception) {
            failSftp('SFTP connection or password login failed: ' . $exception->getMessage(), 'CONNECT_ERROR', $sftp, [
                'user' => $user,
                'method' => 'password',
                'exception' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);
        }

        if (!$loggedIn) {
            failSftp('SFTP login failed using password.', 'AUTH_ERROR', $sftp, [
                'user' => $user,
                'method' => 'password',
            ]);
        }
    } else {
        fail('Set SFTP_PRIVATE_KEY or SFTP_PASSWORD in .env.', 'CONFIG_ERROR');
    }

    logEvent('sftp_auth_success', ['user' => $user]);

    return $sftp;
}

function verifyHostFingerprint(SFTP $sftp, string $expected): void
{
    if ($expected === '') {
        echo 'Warning: SFTP_HOST_FINGERPRINT is not set. Host key pinning is skipped.' . PHP_EOL;
        return;
    }

    try {
        $hostKey = $sftp->getServerPublicHostKey();
    } catch (Throwable $exception) {
        failSftp('Unable to read SFTP server host key: ' . $exception->getMessage(), 'HOST_KEY_ERROR', $sftp, [
            'exception' => get_class($exception),
        ]);
    }

    if (!is_string($hostKey) || $hostKey === '') {
        failSftp('Unable to read SFTP server host key.', 'HOST_KEY_ERROR', $sftp);
    }

    try {
        $key = PublicKeyLoader::loadPublicKey($hostKey);
        $sha256 = $key->getFingerprint('sha256');
        $md5 = $key->getFingerprint('md5');
    } catch (Throwable $exception) {
        fail('Unable to calculate SFTP server host fingerprint: ' . $exception->getMessage(), 'HOST_KEY_ERROR', [
            'exception' => get_class($exception),
        ]);
    }

    if ($sha256 === false || $md5 === false) {
        fail('Unable to calculate SFTP server host fingerprint.', 'HOST_KEY_ERROR');
    }

    $actualSha256 = 'SHA256:' . $sha256;
    $actualMd5 = $md5;
    $expected = trim($expected);
    $accepted = [
        strtolower($actualSha256),
        strtolower($sha256),
        strtolower($actualMd5),
        strtolower('MD5:' . $actualMd5),
    ];

    if (!in_array(strtolower($expected), $accepted, true)) {
        fail('SFTP host fingerprint mismatch. Actual: ' . $actualSha256 . ' / ' . $actualMd5, 'HOST_KEY_MISMATCH', [
            'expected' => $expected,
            'actual_sha256' => $actualSha256,
            'actual_md5' => $actualMd5,
        ]);
    }

    logEvent('sftp_host_key_verified', ['fingerprint' => $actualSha256]);
}

function ensureRemoteBaseExists(SFTP $sftp, string $remoteBase): void
{
    try {
        $isRemoteBaseDir = $sftp->is_dir($remoteBase);
    } catch (Throwable $exception) {
        failSftp('Unable to inspect remote dev directory: ' . $remoteBase, 'REMOTE_BASE_ERROR', $sftp, [
            'remote_base' => $remoteBase,
            'exception' => get_class($exception),
            'exception_message' => $exception->getMessage(),
        ]);
    }

    if (!$isRemoteBaseDir) {
        failSftp('Remote dev directory does not exist or is not a directory: ' . $remoteBase, 'REMOTE_BASE_ERROR', $sftp, [
            'remote_base' => $remoteBase,
        ]);
    }
}

function guardRemoteConflicts(SFTP $sftp, array $files, bool $force): void
{
    $conflicts = [];

    foreach ($files as $file) {
        try {
            $remoteIsDir = $sftp->is_dir($file['remote']);
            $remoteExists = $sftp->file_exists($file['remote']);
        } catch (Throwable $exception) {
            failSftp('Unable to inspect remote path: ' . $file['remote'], 'REMOTE_STAT_ERROR', $sftp, [
                'local' => $file['local'],
                'remote' => $file['remote'],
                'exception' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);
        }

        if ($remoteIsDir) {
            $conflicts[] = $file['remote'] . ' exists as a directory';
            continue;
        }

        if (!$force && $remoteExists) {
            $conflicts[] = $file['remote'];
        }
    }

    if ($conflicts === []) {
        return;
    }

    $message = $force
        ? 'Remote path conflicts found.'
        : 'Remote file(s) already exist. Re-run with --force to overwrite.';

    echo PHP_EOL . $message . PHP_EOL;

    foreach (array_slice($conflicts, 0, 20) as $conflict) {
        echo '  - ' . $conflict . PHP_EOL;
    }

    if (count($conflicts) > 20) {
        echo '  ... and ' . (count($conflicts) - 20) . ' more' . PHP_EOL;
    }

    logEvent('conflict', ['code' => 'REMOTE_CONFLICT', 'count' => count($conflicts), 'examples' => array_slice($conflicts, 0, 20)]);
    exit(EXIT_ERROR);
}

function uploadFiles(SFTP $sftp, array $files, bool $force): void
{
    $total = count($files);

    foreach ($files as $index => $file) {
        $number = $index + 1;
        echo '[' . $number . '/' . $total . '] ' . $file['relative'] . ' -> ' . $file['remote'] . PHP_EOL;

        $remoteDir = dirname($file['remote']);

        try {
            $remoteDirExists = $sftp->is_dir($remoteDir);
            $remoteDirReady = $remoteDirExists || $sftp->mkdir($remoteDir, -1, true);
        } catch (Throwable $exception) {
            failSftp('Unable to create remote directory: ' . $remoteDir, 'REMOTE_MKDIR_ERROR', $sftp, [
                'remote_dir' => $remoteDir,
                'local' => $file['local'],
                'remote' => $file['remote'],
                'exception' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);
        }

        if (!$remoteDirReady) {
            failSftp('Unable to create remote directory: ' . $remoteDir, 'REMOTE_MKDIR_ERROR', $sftp, [
                'remote_dir' => $remoteDir,
                'local' => $file['local'],
                'remote' => $file['remote'],
            ]);
        }

        $tmpRemote = $remoteDir . '/.' . basename($file['remote']) . '.uploading-' . getmypid() . '-' . bin2hex(random_bytes(4));

        try {
            $uploaded = $sftp->put($tmpRemote, $file['local'], SFTP::SOURCE_LOCAL_FILE);
        } catch (Throwable $exception) {
            cleanupRemoteTemp($sftp, $tmpRemote);
            failSftp('Upload failed: ' . $file['relative'], 'UPLOAD_ERROR', $sftp, [
                'local' => $file['local'],
                'remote_tmp' => $tmpRemote,
                'remote' => $file['remote'],
                'local_size' => filesize($file['local']),
                'exception' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);
        }

        if (!$uploaded) {
            cleanupRemoteTemp($sftp, $tmpRemote);
            failSftp('Upload failed: ' . $file['relative'], 'UPLOAD_ERROR', $sftp, [
                'local' => $file['local'],
                'remote_tmp' => $tmpRemote,
                'remote' => $file['remote'],
                'local_size' => filesize($file['local']),
            ]);
        }

        try {
            $remoteExists = $sftp->file_exists($file['remote']);
            $deletedForOverwrite = !$force || !$remoteExists || $sftp->delete($file['remote']);
        } catch (Throwable $exception) {
            cleanupRemoteTemp($sftp, $tmpRemote);
            failSftp('Unable to overwrite remote file: ' . $file['remote'], 'OVERWRITE_ERROR', $sftp, [
                'local' => $file['local'],
                'remote_tmp' => $tmpRemote,
                'remote' => $file['remote'],
                'exception' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);
        }

        if (!$deletedForOverwrite) {
            cleanupRemoteTemp($sftp, $tmpRemote);
            failSftp('Unable to overwrite remote file: ' . $file['remote'], 'OVERWRITE_ERROR', $sftp, [
                'local' => $file['local'],
                'remote_tmp' => $tmpRemote,
                'remote' => $file['remote'],
            ]);
        }

        try {
            $renamed = $sftp->rename($tmpRemote, $file['remote']);
        } catch (Throwable $exception) {
            cleanupRemoteTemp($sftp, $tmpRemote);
            failSftp('Unable to move uploaded file into place: ' . $file['remote'], 'RENAME_ERROR', $sftp, [
                'local' => $file['local'],
                'remote_tmp' => $tmpRemote,
                'remote' => $file['remote'],
                'exception' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);
        }

        if (!$renamed) {
            cleanupRemoteTemp($sftp, $tmpRemote);
            failSftp('Unable to move uploaded file into place: ' . $file['remote'], 'RENAME_ERROR', $sftp, [
                'local' => $file['local'],
                'remote_tmp' => $tmpRemote,
                'remote' => $file['remote'],
            ]);
        }

        logEvent('uploaded_file', [
            'local' => $file['relative'],
            'remote' => $file['remote'],
            'size' => filesize($file['local']),
        ]);
    }
}

function cleanupRemoteTemp(SFTP $sftp, string $tmpRemote): void
{
    try {
        if ($sftp->file_exists($tmpRemote)) {
            $sftp->delete($tmpRemote);
        }
    } catch (Throwable $exception) {
        logEvent('cleanup_failed', [
            'remote_tmp' => $tmpRemote,
            'exception' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'sftp_diagnostics' => safeSftpDiagnostics($sftp),
        ]);
    }
}

function writeSummary(array $files, array $options): void
{
    echo 'Mode: ' . ($options['dry_run'] ? 'dry-run' : 'upload') . PHP_EOL;
    echo 'Overwrite: ' . ($options['force'] ? 'enabled' : 'disabled') . PHP_EOL;
    echo 'Files: ' . count($files) . PHP_EOL . PHP_EOL;

    foreach (array_slice($files, 0, 50) as $file) {
        $remote = $file['remote'] ?? $file['remote_relative'];
        echo '  ' . $file['relative'] . ' -> ' . $remote . PHP_EOL;
    }

    if (count($files) > 50) {
        echo '  ... and ' . (count($files) - 50) . ' more' . PHP_EOL;
    }
}

function logEvent(string $event, array $context = []): void
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return;
    }

    $record = [
        'time' => date('c'),
        'event' => $event,
        'context' => $context,
    ];

    file_put_contents(
        $dir . DIRECTORY_SEPARATOR . 'sftp-upload.log',
        json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function normalizeLocalPath(string $path): string
{
    return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

function isAbsoluteLocalPath(string $path): bool
{
    return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, '\\\\') || str_starts_with($path, '/');
}

function isPathInside(string $path, string $root): bool
{
    $path = normalizeLocalPath($path);
    $root = normalizeLocalPath($root);

    return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
}

function localRelativePath(string $path, string $root): string
{
    $path = normalizeLocalPath($path);
    $root = normalizeLocalPath($root);

    if ($path === $root) {
        return '.';
    }

    if (!str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
        fail('Internal path error: path is outside root.', 'INTERNAL_PATH_ERROR', [
            'path' => $path,
            'root' => $root,
        ]);
    }

    return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));
}

function localRelativeToRemote(string $relative): string
{
    return normalizeRelativeRemotePath($relative);
}

function normalizeRemoteBase(string $path): string
{
    $path = str_replace('\\', '/', trim($path));

    if ($path === '' || $path[0] !== '/') {
        fail('SFTP_REMOTE_DEV_DIR must be an absolute remote path.', 'CONFIG_ERROR', ['remote_base' => $path]);
    }

    $parts = [];

    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }

        if ($part === '..') {
            array_pop($parts);
            continue;
        }

        $parts[] = $part;
    }

    return '/' . implode('/', $parts);
}

function normalizeRelativeRemotePath(string $path): string
{
    $original = $path;
    $path = str_replace('\\', '/', trim($path));

    if ($path === '' || $path === '.') {
        return '';
    }

    if (preg_match('/^[A-Za-z]:\//', $path) === 1 || str_starts_with($path, '/')) {
        fail('Remote path must be relative: ' . $original, 'REMOTE_PATH_ERROR', ['remote' => $original]);
    }

    $parts = [];

    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }

        if ($part === '..') {
            fail('Remote path cannot contain .. segments: ' . $original, 'REMOTE_PATH_ERROR', ['remote' => $original]);
        }

        $parts[] = $part;
    }

    return implode('/', $parts);
}

function joinRemotePath(string $base, string $relative): string
{
    $base = normalizeRemoteBase($base);
    $relative = normalizeRelativeRemotePath($relative);

    if ($relative === '') {
        return $base;
    }

    $joined = $base . '/' . $relative;

    if ($joined !== $base && !str_starts_with($joined, $base . '/')) {
        fail('Remote path escaped the configured dev directory.', 'REMOTE_PATH_ERROR', [
            'base' => $base,
            'joined' => $joined,
        ]);
    }

    return $joined;
}

function joinRelativeRemotePath(string $base, string $relative): string
{
    $base = normalizeRelativeRemotePath($base);
    $relative = normalizeRelativeRemotePath($relative);

    if ($base === '') {
        return $relative;
    }

    if ($relative === '') {
        return $base;
    }

    return $base . '/' . $relative;
}

function resolvePrivateKeyPath(string $path): string
{
    $path = trim($path);

    if (!isAbsoluteLocalPath($path)) {
        $path = __DIR__ . DIRECTORY_SEPARATOR . $path;
    }

    $real = realpath($path);

    if ($real === false || !is_file($real)) {
        fail('Private key file does not exist: ' . $path, 'CONFIG_ERROR', ['private_key' => $path]);
    }

    return $real;
}

function failSftp(string $message, string $code, SFTP $sftp, array $context = []): never
{
    $sftpDiagnostics = safeSftpDiagnostics($sftp);

    if ($sftpDiagnostics !== []) {
        $context['sftp_diagnostics'] = $sftpDiagnostics;
    }

    fail($message, $code, $context);
}

function safeSftpDiagnostics(SFTP $sftp): array
{
    try {
        return sftpDiagnostics($sftp);
    } catch (Throwable $exception) {
        return [
            'diagnostic_error' => $exception->getMessage(),
            'diagnostic_exception' => get_class($exception),
        ];
    }
}

function sftpDiagnostics(SFTP $sftp): array
{
    $diagnostics = [];

    $lastSftpError = $sftp->getLastSFTPError();

    if ($lastSftpError !== false && $lastSftpError !== null && $lastSftpError !== '') {
        $diagnostics['last_sftp_error'] = $lastSftpError;
    }

    $sftpErrors = $sftp->getSFTPErrors();

    if ($sftpErrors !== []) {
        $diagnostics['sftp_errors'] = $sftpErrors;
    }

    $lastSshError = $sftp->getLastError();

    if ($lastSshError !== false && $lastSshError !== null && $lastSshError !== '') {
        $diagnostics['last_ssh_error'] = $lastSshError;
    }

    $sshErrors = $sftp->getErrors();

    if ($sshErrors !== []) {
        $diagnostics['ssh_errors'] = $sshErrors;
    }

    return $diagnostics;
}

function setVerbose(bool $verbose): void
{
    $GLOBALS['SFTP_UPLOAD_VERBOSE'] = $verbose;
}

function isVerbose(): bool
{
    return (bool)($GLOBALS['SFTP_UPLOAD_VERBOSE'] ?? false);
}

function diagnosticHints(string $code): array
{
    return match ($code) {
        'CONFIG_ERROR' => [
            'Check .env values and local file paths.',
            'Confirm required values are not empty.',
        ],
        'DEPENDENCY_ERROR' => [
            'Run composer install from the dev directory.',
        ],
        'USAGE_ERROR' => [
            'Check the command syntax with --help.',
        ],
        'SOURCE_NOT_FOUND' => [
            'Check that the local source exists under the dev directory.',
        ],
        'SOURCE_OUTSIDE_DEV' => [
            'Only files and directories inside the dev directory are allowed.',
        ],
        'SOURCE_EXCLUDED' => [
            'The path is intentionally blocked by the upload policy.',
        ],
        'SOURCE_SYMLINK' => [
            'Symlinks are blocked so uploads cannot escape the dev directory.',
        ],
        'SOURCE_INVALID' => [
            'The source must be a regular file or directory.',
        ],
        'SOURCE_EMPTY' => [
            'The directory may be empty or all files may be excluded by policy.',
        ],
        'REMOTE_PATH_ERROR' => [
            'Use a relative remote path without absolute paths or .. segments.',
        ],
        'CONNECT_ERROR' => [
            'Check SFTP_HOST, SFTP_PORT, network reachability, and whether the server accepts SSH/SFTP.',
        ],
        'HOST_KEY_ERROR' => [
            'Check server reachability and supported SSH host key algorithms.',
        ],
        'HOST_KEY_MISMATCH' => [
            'Do not bypass this blindly. Confirm the server host key fingerprint with the hosting provider.',
        ],
        'AUTH_KEY_ERROR' => [
            'Check the private key file format and passphrase.',
        ],
        'AUTH_ERROR' => [
            'Check SFTP_USER, private key/passphrase or password, and whether the public key is registered on the server.',
        ],
        'REMOTE_BASE_ERROR' => [
            'Check SFTP_REMOTE_DEV_DIR and the user permissions on the remote server.',
        ],
        'REMOTE_MKDIR_ERROR' => [
            'Check write permission on the parent remote directory.',
        ],
        'UPLOAD_ERROR' => [
            'Check remote write permission, disk space, quota, and whether the remote path allows file creation.',
        ],
        'OVERWRITE_ERROR' => [
            'Check permission to delete or replace the existing remote file.',
        ],
        'RENAME_ERROR' => [
            'Check permission to rename files in the remote directory.',
        ],
        default => [],
    };
}

function redactContext(array $context): array
{
    $redacted = [];

    foreach ($context as $key => $value) {
        $keyString = (string)$key;

        if (preg_match('/password|passphrase|token|secret/i', $keyString) === 1) {
            $redacted[$key] = '[redacted]';
            continue;
        }

        if (is_array($value)) {
            $redacted[$key] = redactContext($value);
            continue;
        }

        $redacted[$key] = $value;
    }

    return $redacted;
}

function printVerboseDiagnostics(string $code, array $context, array $hints): void
{
    if (!isVerbose()) {
        return;
    }

    if ($hints !== []) {
        fwrite(STDERR, PHP_EOL . 'Possible causes:' . PHP_EOL);

        foreach ($hints as $hint) {
            fwrite(STDERR, '  - ' . $hint . PHP_EOL);
        }
    }

    if ($context !== []) {
        fwrite(STDERR, PHP_EOL . 'Diagnostics:' . PHP_EOL);
        fwrite(STDERR, json_encode([
            'code' => $code,
            'context' => $context,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    }
}

function fail(string $message, string $code = 'ERROR', array $context = []): never
{
    $context = redactContext($context);
    $hints = diagnosticHints($code);

    fwrite(STDERR, 'Error [' . $code . ']: ' . $message . PHP_EOL);
    printVerboseDiagnostics($code, $context, $hints);
    logEvent('error', [
        'code' => $code,
        'message' => $message,
        'context' => $context,
        'hints' => $hints,
    ]);
    exit(EXIT_ERROR);
}
