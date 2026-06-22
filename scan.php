<?php
/**
 * Daily gitleaks scan with SMTP email alert (pure PHP, no dependencies)
 *
 * Usage: php scan.php
 * See README.md for setup instructions.
 */

$configPath = __DIR__ . '/config.php';

if (!file_exists($configPath)) {
    fwrite(STDERR, "Missing config.php — copy config.example.php to config.php and fill in your values.\n");
    exit(2);
}

$config = require $configPath;

$scanDir     = $config['scanDir'];
$gitleaksBin = $config['gitleaksBin'];
$configFile  = $config['configFile'];
$reportFile  = rtrim($config['reportDir'], '/') . '/gitleaks-report-' . date('Ymd') . '.json';
$smtp        = $config['smtp'];

// ---- Run gitleaks ----
$cmd = sprintf(
    '%s detect --source=%s --no-git --report-format=json --report-path=%s --config=%s --redact -v 2>&1',
    escapeshellarg($gitleaksBin),
    escapeshellarg($scanDir),
    escapeshellarg($reportFile),
    escapeshellarg($configFile)
);

exec($cmd, $output, $exitCode);

// ---- Decide whether to email ----
if ($exitCode === 1) {
    $subject = "⚠️ Gitleaks: secrets found in {$scanDir} (" . gethostname() . ", " . date('Y-m-d') . ")";
    $body = file_exists($reportFile) ? file_get_contents($reportFile) : "No report file generated.";
    sendSmtpMail($smtp, $subject, $body);
    // Keep the report on disk for review - don't delete it
} elseif ($exitCode > 1) {
    $subject = "Gitleaks scan FAILED on " . gethostname();
    $body = "Gitleaks exited with code {$exitCode}\n\n" . implode("\n", $output);
    sendSmtpMail($smtp, $subject, $body);
} else {
    // exitCode 0 = no leaks - cleanup the report
    if (file_exists($reportFile)) {
        unlink($reportFile);
    }
}

exit($exitCode);

// ---- Minimal SMTP client (STARTTLS, AUTH LOGIN) ----
function sendSmtpMail(array $smtp, string $subject, string $body): void
{
    $socket = fsockopen($smtp['host'], $smtp['port'], $errno, $errstr, 15);
    if (!$socket) {
        error_log("SMTP connect failed: $errstr ($errno)");
        return;
    }

    $read = function () use ($socket) {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $data;
    };

    $write = function (string $cmd) use ($socket) {
        fwrite($socket, $cmd . "\r\n");
    };

    $read(); // greeting
    $write("EHLO " . gethostname());
    $read();

    $write("STARTTLS");
    $read();
    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

    $write("EHLO " . gethostname());
    $read();

    $write("AUTH LOGIN");
    $read();
    $write(base64_encode($smtp['user']));
    $read();
    $write(base64_encode($smtp['pass']));
    $resp = $read();

    if (strpos($resp, '235') !== 0) {
        error_log("SMTP auth failed: $resp");
        fclose($socket);
        return;
    }

    $write("MAIL FROM:<{$smtp['from']}>");
    $read();
    $write("RCPT TO:<{$smtp['to']}>");
    $read();
    $write("DATA");
    $read();

    $headers = "From: {$smtp['fromName']} <{$smtp['from']}>\r\n";
    $headers .= "To: <{$smtp['to']}>\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
    $headers .= "\r\n";

    // Escape lines starting with a lone "."
    $escapedBody = preg_replace('/^\./m', '..', $body);

    $write($headers . $escapedBody . "\r\n.");
    $read();

    $write("QUIT");
    fclose($socket);
}
