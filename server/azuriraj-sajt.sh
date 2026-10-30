#!/bin/sh
# Preuzima najnoviju verziju sajta sa GitHub-a i kopira je u folder koji web server prikazuje.
# Server ga pokreće sam svakih 5 minuta (uputstvo: server/POSTAVLJANJE.md).
set -eu

REPO_DIR=${REPO_DIR:-/opt/bookmarkstudio}
WEB_DIR=${WEB_DIR:-/var/www/bookmarkstudio}

# Zaštita: rsync --delete briše iz WEB_DIR sve što nije na GitHub-u, pa ne sme da gađa sistemske foldere
case "$WEB_DIR" in
  ""|/|/var|/var/www|/home|/root|/etc|/opt|/usr|/srv) echo "Pogrešan WEB_DIR: '$WEB_DIR'" >&2; exit 1 ;;
esac
[ -d "$WEB_DIR" ] || { echo "Folder $WEB_DIR ne postoji" >&2; exit 1; }

# Ako je prethodno pokretanje još u toku, ne pokreći ponovo
exec 9>"${TMPDIR:-/tmp}/bookmarkstudio-azuriranje.lock"
flock -n 9 || exit 0

cd "$REPO_DIR"
git fetch -q origin main
git reset -q --hard origin/main

# Na sajt ne idu: git podaci, ovaj folder, README i podešavanja koja postoje samo na serveru
rsync -a --delete --chmod=D755,F644 \
  --exclude .git --exclude .gitignore --exclude server/ --exclude README.md --exclude kontakt-config.php \
  "$REPO_DIR"/ "$WEB_DIR"/
