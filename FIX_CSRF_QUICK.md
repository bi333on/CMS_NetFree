# 🔧 Быстрое исправление CSRF token mismatch

## Проблема
При работе в админке CoreCMS появляется ошибка **"CSRF token mismatch"**.

---

## ⚡ Быстрое решение (3 шага)

### Шаг 1: Проверьте файл `public/index.php`

Убедитесь, что сессия запускается **ДО** любых других операций:

```php
<?php
require_once __DIR__ . '/../app/bootstrap.php';

use NetFree\Application;
use NetFree\Session;

// ВАЖНО: запустить сессию первым делом!
Session::start();

$app = Application::getInstance();
$response = $app->handle();
$response->send();
```

### Шаг 2: Проверьте `app/bootstrap.php`

В начале файла должно быть:

```php
<?php
require_once __DIR__ . '/Autoloader.php';
\NetFree\Autoloader::register();

// Запуск сессии
\NetFree\Session::start();
```

### Шаг 3: Очистите cookies браузера

1. Откройте DevTools (F12)
2. Вкладка Application → Cookies
3. Удалите все cookies для вашего сайта
4. Обновите страницу (Ctrl+F5)

---

## 🔍 Если не помогло - диагностика

### Вариант A: Добавьте отладку

В `app/AdminController.php` замените строки с проверкой CSRF:

**Было:**
```php
if (!Csrf::verify((string) $app->request->input('_csrf', ''))) {
    return $resp->setStatus(403)->setBody('CSRF token mismatch');
}
```

**Стало:**
```php
$csrfToken = (string) $app->request->input('_csrf', '');
$isValid = Csrf::verify($csrfToken);

if (!$isValid) {
    error_log('CSRF Failed! Token: ' . substr($csrfToken, 0, 10) . '...');
    error_log('Session: ' . print_r($_SESSION, true));
    return $resp->setStatus(403)->setBody('CSRF token mismatch - check error log');
}
```

Затем проверьте лог ошибок PHP.

### Вариант B: Увеличьте время жизни сессии

В `app/Session.php` добавьте:

```php
public static function start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    
    // Увеличиваем время жизни до 24 часов
    ini_set('session.gc_maxlifetime', '86400');
    
    session_set_cookie_params([
        'lifetime' => 86400, // 24 часа
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => false, // измените на true если используете HTTPS
    ]);
    
    session_start();
}
```

---

## 🚨 Частые причины

| Причина | Решение |
|---------|---------|
| Сессия не запускается | Добавьте `Session::start()` в начало `index.php` |
| Cookies заблокированы | Проверьте настройки браузера |
| Используете HTTPS с `secure=false` | Измените на `'secure' => true` |
| Форма не содержит токен | Убедитесь что `<?= Csrf::field() ?>` присутствует |
| Долгое бездействие | Увеличьте время жизни сессии |
| Несколько вкладок | Обновите все вкладки (Ctrl+F5) |

---

## 📝 Проверочный список

- [ ] `Session::start()` вызывается в `index.php` или `bootstrap.php`
- [ ] Все формы содержат `<?= Csrf::field() ?>`
- [ ] Cookies браузера очищены
- [ ] Время жизни сессии достаточно длинное
- [ ] Нет конфликтов с другими приложениями на том же домене

---

## 🔬 Расширенная диагностика

### 1. Проверьте сессию

Создайте файл `test-session.php` в корне:

```php
<?php
session_start();

echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Data: " . print_r($_SESSION, true) . "\n";
```

Откройте в браузере и проверьте вывод.

### 2. Проверьте CSRF токен

Создайте `test-csrf.php`:

```php
<?php
require_once __DIR__ . '/app/bootstrap.php';

use NetFree\Csrf;
use NetFree\Session;

Session::start();

$token = Csrf::token();

echo "CSRF Token: " . $token . "\n";
echo "Token Length: " . strlen($token) . "\n";
echo "Session Data: " . print_r($_SESSION, true) . "\n";
```

### 3. Тест формы

Создайте `test-form.php`:

```php
<?php
require_once __DIR__ . '/app/bootstrap.php';
use NetFree\Csrf;
use NetFree\Session;

Session::start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    $valid = Csrf::verify($token);
    
    echo "<h2>Result: " . ($valid ? "SUCCESS ✓" : "FAILED ✗") . "</h2>";
    echo "<p>Token received: " . htmlspecialchars($token) . "</p>";
    echo "<p>Token in session: " . htmlspecialchars(Csrf::token()) . "</p>";
} else {
    ?>
    <form method="POST">
        <?= Csrf::field() ?>
        <button type="submit">Test CSRF</button>
    </form>
    <?php
}
```

---

## 💡 Решение для продакшна

Замените стандартный `Csrf.php` на улучшенную версию `CsrfImproved.php` (уже создана в проекте):

```php
// В bootstrap.php или где используется Csrf
// Замените:
use NetFree\Csrf;

// На:
class_alias('NetFree\CsrfImproved', 'NetFree\Csrf');
```

Или просто переименуйте:
```bash
mv app/Csrf.php app/Csrf.old.php
mv app/CsrfImproved.php app/Csrf.php
```

---

## ✅ Проверка исправления

После применения исправлений:

1. Откройте админку: `/admin`
2. Войдите в систему
3. Попробуйте создать/редактировать страницу
4. Если всё работает - проблема решена! 🎉

---

## 🆘 Всё ещё не работает?

Если проблема остаётся:

1. Проверьте права на папку `storage/` (должна быть записываемая)
2. Проверьте конфигурацию PHP: `session.save_path`
3. Проверьте, не конфликтует ли с другими приложениями на домене
4. Попробуйте в режиме инкогнито браузера

---

**Вопросы?** Проверьте `CSRF_FIX.md` для подробностей или используйте отладочный скрипт `csrf_debug_example.php`.
