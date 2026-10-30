<?php
// Prima poruke iz kontakt forme i šalje ih na info@bookmarkstudio.rs.
// Ako pored ovog fajla postoji kontakt-config.php (samo na serveru, nikad na GitHub-u),
// šalje preko SMTP naloga iz njega; inače preko PHP funkcije mail().
// Primer kontakt-config.php:
//   <?php return ['host' => 'mail.primer.rs', 'port' => 465, 'secure' => 'ssl',
//                 'user' => 'info@bookmarkstudio.rs', 'pass' => 'lozinka'];
// 'secure' je 'ssl' (obično port 465) ili 'tls' (obično port 587).

const TO = 'info@bookmarkstudio.rs';
// Pošiljalac mora biti adresa na domenu sajta, inače mejl lako završi u spamu.
// Posetiočeva adresa ide u Reply-To, pa "Odgovori" ide direktno njemu.
const FROM = 'info@bookmarkstudio.rs';

$wantsJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
$lang = ($_POST['lang'] ?? '') === 'en' ? 'en' : 'sr';

// Odgovor: JSON za script.js, a bez JavaScript-a vraća posetioca na stranu sa porukom o ishodu
function finish(bool $ok, int $code): void {
    global $wantsJson, $lang;
    if ($wantsJson) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok]);
    } else {
        $page = $lang === 'en' ? 'en/index.html?poslato=%d#contact' : 'index.html?poslato=%d#kontakt';
        header('Location: ' . sprintf($page, $ok ? 1 : 0), true, 303);
    }
    exit;
}

function cut(string $value, int $max): string {
    return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

// Jedan red teksta, bez prelaska u novi red (sprečava ubacivanje lažnih zaglavlja u mejl)
function line(string $key, int $max): string {
    return trim(cut(str_replace(["\r", "\n"], ' ', (string)($_POST[$key] ?? '')), $max));
}

// Šalje gotov mejl preko SMTP servera, sa prijavom (AUTH LOGIN)
function smtp_send(array $c, string $headers, string $subject, string $body): bool {
    $prefix = ($c['secure'] ?? 'ssl') === 'ssl' ? 'ssl://' : 'tcp://';
    $fp = @stream_socket_client($prefix . $c['host'] . ':' . (int)($c['port'] ?? 465), $errno, $errstr, 15);
    if (!$fp) {
        return false;
    }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): int {
        $code = 0;
        while (($line = fgets($fp, 1024)) !== false) {
            $code = (int)substr($line, 0, 3);
            if (!isset($line[3]) || $line[3] === ' ') {
                break;
            }
        }
        return $code;
    };
    $say = function (string $cmd, array $ok) use ($fp, $read): bool {
        fwrite($fp, $cmd . "\r\n");
        return in_array($read(), $ok, true);
    };
    $host = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost';
    $ok = $read() === 220 && $say("EHLO $host", [250]);
    if ($ok && ($c['secure'] ?? '') === 'tls') {
        $ok = $say('STARTTLS', [220])
            && stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
            && $say("EHLO $host", [250]);
    }
    $ok = $ok
        && $say('AUTH LOGIN', [334])
        && $say(base64_encode($c['user']), [334])
        && $say(base64_encode($c['pass']), [235])
        && $say('MAIL FROM:<' . FROM . '>', [250])
        && $say('RCPT TO:<' . TO . '>', [250, 251])
        && $say('DATA', [354]);
    if ($ok) {
        $message = 'To: ' . TO . "\r\nSubject: $subject\r\nDate: " . date('r') . "\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@bookmarkstudio.rs>' . "\r\n$headers\r\n\r\n"
            . preg_replace('/^\./m', '..', $body);
        $ok = $say($message . "\r\n.", [250]);
        $say('QUIT', [221]);
    }
    fclose($fp);
    return $ok;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    finish(false, 405);
}

// Skriveno polje koje popunjavaju samo spam botovi: pravimo se da je poslato
if (!empty($_POST['website'])) {
    finish(true, 200);
}

$name = line('name', 100);
$email = line('email', 150);
$topic = line('topic', 100);
$message = trim(cut(str_replace(["\r\n", "\r"], "\n", (string)($_POST['message'] ?? '')), 5000));
$news = !empty($_POST['news']);

if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    finish(false, 422);
}

$subject = 'Poruka sa sajta: ' . ($topic !== '' ? $topic : 'Kontakt');
$body = "Ime: $name\n"
    . "Mejl: $email\n"
    . "Tema: $topic\n"
    . 'Želi mejl o novostima: ' . ($news ? 'da' : 'ne') . "\n"
    . 'Jezik sajta: ' . ($lang === 'en' ? 'engleski' : 'srpski') . "\n\n"
    . "Poruka:\n$message\n";

$headers = implode("\r\n", [
    'From: Bookmark Studio sajt <' . FROM . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
]);
$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$encodedBody = chunk_split(base64_encode($body));

$config = __DIR__ . '/kontakt-config.php';
if (is_file($config)) {
    $sent = smtp_send(require $config, $headers, $encodedSubject, rtrim($encodedBody));
} else {
    // Neki serveri traže -f (adresa pošiljaoca za povratne poruke), a neki ga ne dozvoljavaju
    $sent = @mail(TO, $encodedSubject, $encodedBody, $headers, '-f' . FROM)
        || @mail(TO, $encodedSubject, $encodedBody, $headers);
}

finish($sent, $sent ? 200 : 500);
