#!/usr/bin/env bash
#
# NetFree CMS — установщик одной командой (VPS + Caddy).
#
# Использование (запускать от root или через sudo):
#   bash <(curl -fsSL https://raw.githubusercontent.com/bi333on/CMS_NetFree/master/install.sh)
#
# или, если скрипт уже скачан:
#   sudo bash install.sh
#
# Что делает:
#   1. Ставит PHP + расширения, Caddy, Git, Composer (если нет).
#   2. Клонирует репозиторий в /var/www/netfree.
#   3. Настраивает Caddy (веб-корень public/).
#   4. Создаёт конфиг БД и запускает веб-установку.
#

set -euo pipefail

# ---------------------------------------------------------------------------
# Параметры (можно задать через переменные окружения)
# ---------------------------------------------------------------------------
APP_DIR="${APP_DIR:-/var/www/netfree}"
REPO_URL="${REPO_URL:-https://github.com/bi333on/CMS_NetFree.git}"
BRANCH="${BRANCH:-master}"
SITE_DOMAIN="${SITE_DOMAIN:-}"
APP_USER="${APP_USER:-www-data}"
PHP_VERSION="${PHP_VERSION:-8.2}"
DB_NAME="${DB_NAME:-netfree}"
DB_USER="${DB_USER:-netfree}"
# Пароли без спецсимволов (буквы+цифры) — чтобы не ломались в bash.
DB_PASS="${DB_PASS:-$(openssl rand -hex 12 2>/dev/null || echo "netfree$(date +%s)")}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-$(openssl rand -hex 10 2>/dev/null || echo "admin$(date +%s)")}"
SITE_NAME="${SITE_NAME:-NetFree}"

# Автоопределение домена: первый аргумент скрипта.
if [ -z "$SITE_DOMAIN" ] && [ $# -ge 1 ]; then
    SITE_DOMAIN="$1"
fi

if [ -z "$SITE_DOMAIN" ]; then
    echo "Укажите домен:"
    echo "  bash install.sh example.com"
    echo "  или SITE_DOMAIN=example.com bash install.sh"
    exit 1
fi

if [ "$(id -u)" -ne 0 ]; then
    echo "Запустите от root: sudo bash install.sh $SITE_DOMAIN"
    exit 1
fi

echo "==> NetFree установка на: $SITE_DOMAIN (PHP $PHP_VERSION)"

# ---------------------------------------------------------------------------
# 1. Пакеты (PHP, MariaDB, git, curl)
# ---------------------------------------------------------------------------
apt-get update -y

PHP_PKG="php${PHP_VERSION}-fpm php${PHP_VERSION}-mysql php${PHP_VERSION}-mbstring \
php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-xml php${PHP_VERSION}-gd"

apt-get install -y git curl unzip ca-certificates "$PHP_PKG" 2>/dev/null || \
apt-get install -y git curl unzip ca-certificates php-fpm php-mysql php-mbstring php-curl php-zip php-xml php-gd

# ---------------------------------------------------------------------------
# 1b. MariaDB
# ---------------------------------------------------------------------------
if ! command -v mysql >/dev/null 2>&1; then
    echo "==> Устанавливаю MariaDB"
    apt-get install -y mariadb-server
fi
systemctl enable mariadb 2>/dev/null || true
systemctl start mariadb 2>/dev/null || service mariadb start 2>/dev/null || true

# ---------------------------------------------------------------------------
# 2. Caddy
# ---------------------------------------------------------------------------
if ! command -v caddy >/dev/null 2>&1; then
    echo "==> Устанавливаю Caddy"
    apt-get install -y debian-keyring debian-archive-keyring apt-transport-https
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
        | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
        | tee /etc/apt/sources.list.d/caddy-stable.list
    apt-get update -y
    apt-get install -y caddy
fi

# ---------------------------------------------------------------------------
# 3. Клонирование
# ---------------------------------------------------------------------------
echo "==> Клонирую репозиторий в $APP_DIR"

# Доверяем каталогу (иначе git откажет после chown на www-data).
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

if [ -d "$APP_DIR/.git" ]; then
    echo "    Репозиторий уже есть — обновляю (git pull)."
    git -C "$APP_DIR" fetch --all
    git -C "$APP_DIR" reset --hard "origin/$BRANCH"
else
    mkdir -p "$(dirname "$APP_DIR")"
    git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
    git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
fi

# ---------------------------------------------------------------------------
# 4. Права
# ---------------------------------------------------------------------------
echo "==> Настраиваю права"
id -u "$APP_USER" >/dev/null 2>&1 || APP_USER="www-data"
mkdir -p "$APP_DIR/public/uploads" "$APP_DIR/storage"
chown -R "$APP_USER:$APP_USER" "$APP_DIR"
chmod -R 775 "$APP_DIR/public/uploads" "$APP_DIR/storage" "$APP_DIR/config"
find "$APP_DIR" -type f -exec chmod 664 {} \;
find "$APP_DIR" -type d -exec chmod 775 {} \;

# ---------------------------------------------------------------------------
# 4b. База данных (создаём БД и пользователя, если ещё не существует)
# ---------------------------------------------------------------------------
echo "==> Создаю базу данных"
mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';" 2>/dev/null || true
# Принудительно ставим пароль (иначе CREATE USER IF NOT EXISTS игнорирует пароль у существующего пользователя).
mysql -e "ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';" 2>/dev/null || true
mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';" 2>/dev/null || true
mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true

# ---------------------------------------------------------------------------
# 5. Caddy конфиг
# ---------------------------------------------------------------------------
echo "==> Настраиваю Caddy"
PHP_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
if [ ! -S "$PHP_SOCK" ]; then
    PHP_SOCK="/run/php/php-fpm.sock"
fi

mkdir -p /etc/caddy
cat > /etc/caddy/Caddyfile <<CADDY
$SITE_DOMAIN {
    root * $APP_DIR/public

    encode gzip

    php_fastcgi unix/$PHP_SOCK

    try_files {path} /index.php?{query}

    handle_path /assets/* {
        root * $APP_DIR/themes/default
        file_server
    }

    # Загруженные медиафайлы (картинки, документы).
    handle_path /uploads/* {
        root * $APP_DIR/public
        file_server
    }

    # Важно: install.php НЕ закрываем — он нужен для первичной установки.
    # После установки файл удаляется, поэтому отдельный deny не требуется.
    @deny path /composer.json /composer.lock /netfree-install.php
    respond @deny 404

    log {
        output file /var/log/caddy/netfree.log
    }
}
CADDY

# ---------------------------------------------------------------------------
# 6. Запуск Caddy
# ---------------------------------------------------------------------------
echo "==> Перезапускаю Caddy"
systemctl enable caddy 2>/dev/null || true
systemctl restart caddy 2>/dev/null || caddy reload --config /etc/caddy/Caddyfile 2>/dev/null || true

# ---------------------------------------------------------------------------
# 7. Автоматическая установка CMS (БД + админ + config/env.php)
# ---------------------------------------------------------------------------
echo "==> Устанавливаю CMS (база + администратор)"
PHP_BIN="$(command -v php || true)"
if [ -z "$PHP_BIN" ]; then
    PHP_BIN="php${PHP_VERSION}"
fi

"$PHP_BIN" "$APP_DIR/cli-install.php" \
    --db-host="localhost" \
    --db-port="3306" \
    --db-name="$DB_NAME" \
    --db-user="$DB_USER" \
    --db-pass="$DB_PASS" \
    --admin-user="$ADMIN_USER" \
    --admin-pass="$ADMIN_PASS" \
    --site-name="$SITE_NAME" \
    --site-url="https://$SITE_DOMAIN" \
    --timezone="UTC"

INSTALL_OK=$?
rm -f "$APP_DIR/public/install.php" 2>/dev/null || true

echo ""
echo "====================================================="
echo " NetFree установлен и настроен."
echo " Сайт:            https://$SITE_DOMAIN"
echo " Админ-панель:    https://$SITE_DOMAIN/admin"
echo " Каталог:         $APP_DIR"
echo ""
echo " Доступ администратора:"
echo "   Логин:     $ADMIN_USER"
echo "   Пароль:    $ADMIN_PASS"
echo ""
echo " MySQL:"
echo "   База:      $DB_NAME"
echo "   Пользователь: $DB_USER"
echo "   Пароль:    $DB_PASS"
echo ""
echo " Повторный запуск этого скрипта обновит код с GitHub,"
echo " не затирая config/env.php и загруженные файлы."
echo "====================================================="

exit $INSTALL_OK
