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
# 1. Пакеты
# ---------------------------------------------------------------------------
apt-get update -y

PHP_PKG="php${PHP_VERSION}-fpm php${PHP_VERSION}-mysql php${PHP_VERSION}-mbstring \
php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-xml php${PHP_VERSION}-gd"

apt-get install -y git curl unzip ca-certificates "$PHP_PKG" 2>/dev/null || \
apt-get install -y git curl unzip ca-certificates php-fpm php-mysql php-mbstring php-curl php-zip php-xml php-gd

# Composer (для автозагрузчика — опционально, ядро работает и без него)
if ! command -v composer >/dev/null 2>&1; then
    echo "==> Устанавливаю Composer"
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

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
if [ -d "$APP_DIR/.git" ]; then
    echo "    Репозиторий уже есть — обновляю (git pull)."
    git -C "$APP_DIR" fetch --all
    git -C "$APP_DIR" reset --hard "origin/$BRANCH"
else
    mkdir -p "$(dirname "$APP_DIR")"
    git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
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

# Composer autoload (опционально, ядро сам подгружается)
if [ -f "$APP_DIR/composer.json" ] && command -v composer >/dev/null 2>&1; then
    (cd "$APP_DIR" && composer install --no-dev --optimize-autoloader) || true
fi

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

    @deny path /install.php /composer.json /composer.lock /netfree-install.php
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

echo ""
echo "====================================================="
echo " NetFree установлен."
echo " Сайт:        http://$SITE_DOMAIN"
echo " Установка:   http://$SITE_DOMAIN/install.php"
echo " Каталог:     $APP_DIR"
echo ""
echo " Дальше:"
echo " 1) Откройте http://$SITE_DOMAIN/install.php в браузере."
echo " 2) Заполните данные MySQL и администратора."
echo " 3) После установки УДАЛИТЕ: $APP_DIR/public/install.php"
echo ""
echo " Повторный запуск этого скрипта обновит код с GitHub,"
echo " не затирая config/env.php и загруженные файлы."
echo "====================================================="
