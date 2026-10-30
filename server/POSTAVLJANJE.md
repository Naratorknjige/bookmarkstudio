# Automatsko ažuriranje sajta na serveru

Uputstvo za osobu koja ima pristup serveru (Debian, preko SSH-a). Radi se **jednom**.
Posle toga server svakih 5 minuta sam preuzme najnoviju verziju sajta sa GitHub-a
(https://github.com/Naratorknjige/bookmarkstudio), pa se svaka izmena poslata na granu `main`
pojavi na https://bookmarkstudio.rs za najviše 5 minuta.

Sve komande se kucaju kao `root` (ili sa `sudo` ispred).

## 1. Pronađi folder iz kog se prikazuje sajt

```sh
grep -R "root " /etc/nginx/sites-enabled/ 2>/dev/null
grep -R "DocumentRoot" /etc/apache2/sites-enabled/ 2>/dev/null
```

Prikazaće se putanja, na primer `/var/www/bookmarkstudio` ili `/var/www/html`.
U koracima ispod ta putanja je označena kao `PUTANJA` — zameni je pravom.

## 2. Napravi rezervnu kopiju trenutnog sajta

```sh
tar czf /root/sajt-rezerva-$(date +%F).tgz -C PUTANJA .
```

## 3. Instaliraj alate i preuzmi sajt sa GitHub-a

```sh
apt update && apt install -y git rsync
git clone https://github.com/Naratorknjige/bookmarkstudio.git /opt/bookmarkstudio
```

## 4. Pokreni ažuriranje jednom ručno

```sh
WEB_DIR=PUTANJA sh /opt/bookmarkstudio/server/azuriraj-sajt.sh
```

**Pažnja:** ovo iz foldera `PUTANJA` briše sve što nije deo sajta na GitHub-u.
Ako u tom folderu ima još nešto što treba da ostane (drugi sajt, fajlovi koji nisu sa GitHub-a),
prvo to premesti ili javi pre nego što pokreneš.

Otvori https://bookmarkstudio.rs i proveri da sajt radi.

## 5. Uključi automatsko ažuriranje na svakih 5 minuta

```sh
echo '*/5 * * * * root WEB_DIR=PUTANJA /bin/sh /opt/bookmarkstudio/server/azuriraj-sajt.sh >/dev/null 2>&1' > /etc/cron.d/bookmarkstudio
```

## Važno posle ovoga

- Fajlove sajta **ne menjati direktno na serveru** — sledeće ažuriranje će ih pregaziti.
  Sve izmene idu preko GitHub-a.
- Isključivanje automatskog ažuriranja: `rm /etc/cron.d/bookmarkstudio`

## Kontakt forma

Forma šalje mejl preko `kontakt.php`, za šta server mora da ima PHP i program za slanje pošte.
Provera:

```sh
php -v
ls /usr/sbin/sendmail
```

Ako nešto od ovoga ne postoji, ili poruke sa forme ne stižu na info@bookmarkstudio.rs,
forma treba da šalje preko SMTP naloga za info@bookmarkstudio.rs (to se podešava u fajlu
`kontakt-config.php` koji postoji samo na serveru i nikad ne ide na GitHub).
