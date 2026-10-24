<?php
// Prima poruke iz kontakt forme i šalje ih na info@bookmarkstudio.rs.
// Radi samo na hostingu koji ima PHP i funkciju mail(). GitHub Pages ne pokreće PHP,
// pa tamo forma umesto ovoga otvara program za poštu (vidi script.js).

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

// Neki hostinzi traže -f (adresa pošiljaoca za povratne poruke), a neki ga ne dozvoljavaju
$sent = @mail(TO, $encodedSubject, $encodedBody, $headers, '-f' . FROM)
    || @mail(TO, $encodedSubject, $encodedBody, $headers);

finish($sent, $sent ? 200 : 500);
