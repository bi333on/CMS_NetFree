<?php

declare(strict_types=1);

namespace NetFree\Builder;

/**
 * Условная видимость блоков конструктора.
 * Позволяет показывать/скрывать секции и виджеты по условиям:
 * - роль пользователя
 * - время/дата
 * - параметры URL
 * - cookies
 * - кастомные PHP условия
 */
class ConditionalVisibility
{
    /**
     * Проверяет, должен ли блок быть виден при текущих условиях.
     */
    public static function isVisible(array $node): bool
    {
        $conditions = (array) ($node['visibility'] ?? []);

        if (empty($conditions) || !isset($conditions['enabled']) || !$conditions['enabled']) {
            return true; // Нет условий = всегда показывать
        }

        $rules = (array) ($conditions['rules'] ?? []);
        $logic = (string) ($conditions['logic'] ?? 'AND'); // AND или OR

        if (empty($rules)) {
            return true;
        }

        $results = [];
        foreach ($rules as $rule) {
            $results[] = self::checkRule($rule);
        }

        // Применяем логику AND/OR
        if ($logic === 'OR') {
            return in_array(true, $results, true);
        }

        return !in_array(false, $results, true);
    }

    /**
     * Проверяет отдельное правило видимости.
     */
    protected static function checkRule(array $rule): bool
    {
        $type = (string) ($rule['type'] ?? '');

        switch ($type) {
            case 'user_role':
                return self::checkUserRole($rule);

            case 'user_logged':
                return self::checkUserLogged($rule);

            case 'date_range':
                return self::checkDateRange($rule);

            case 'time_range':
                return self::checkTimeRange($rule);

            case 'day_of_week':
                return self::checkDayOfWeek($rule);

            case 'url_param':
                return self::checkUrlParam($rule);

            case 'cookie':
                return self::checkCookie($rule);

            case 'referrer':
                return self::checkReferrer($rule);

            case 'device':
                return self::checkDevice($rule);

            case 'php_condition':
                return self::checkPhpCondition($rule);

            default:
                return true;
        }
    }

    /**
     * Проверка по роли пользователя.
     */
    protected static function checkUserRole(array $rule): bool
    {
        $user = current_user();
        if (!$user) {
            return false;
        }

        $requiredRoles = (array) ($rule['roles'] ?? []);
        $userRole = (string) ($user['role'] ?? 'subscriber');

        return in_array($userRole, $requiredRoles, true);
    }

    /**
     * Проверка авторизации пользователя.
     */
    protected static function checkUserLogged(array $rule): bool
    {
        $shouldBeLogged = (bool) ($rule['logged'] ?? true);
        $isLogged = is_logged_in();

        return $shouldBeLogged ? $isLogged : !$isLogged;
    }

    /**
     * Проверка диапазона дат.
     */
    protected static function checkDateRange(array $rule): bool
    {
        $from = (string) ($rule['from'] ?? '');
        $to = (string) ($rule['to'] ?? '');
        $now = time();

        if ($from !== '' && strtotime($from) > $now) {
            return false;
        }

        if ($to !== '' && strtotime($to . ' 23:59:59') < $now) {
            return false;
        }

        return true;
    }

    /**
     * Проверка времени суток.
     */
    protected static function checkTimeRange(array $rule): bool
    {
        $from = (string) ($rule['from'] ?? '00:00');
        $to = (string) ($rule['to'] ?? '23:59');
        $now = date('H:i');

        return $now >= $from && $now <= $to;
    }

    /**
     * Проверка дня недели.
     */
    protected static function checkDayOfWeek(array $rule): bool
    {
        $allowedDays = (array) ($rule['days'] ?? []);
        $currentDay = (int) date('N'); // 1=пн, 7=вс

        return in_array($currentDay, $allowedDays, true);
    }

    /**
     * Проверка GET-параметра URL.
     */
    protected static function checkUrlParam(array $rule): bool
    {
        $param = (string) ($rule['param'] ?? '');
        $operator = (string) ($rule['operator'] ?? 'equals');
        $value = (string) ($rule['value'] ?? '');

        if ($param === '') {
            return true;
        }

        $actual = (string) ($_GET[$param] ?? '');

        switch ($operator) {
            case 'equals':
                return $actual === $value;
            case 'not_equals':
                return $actual !== $value;
            case 'contains':
                return strpos($actual, $value) !== false;
            case 'not_contains':
                return strpos($actual, $value) === false;
            case 'exists':
                return isset($_GET[$param]);
            case 'not_exists':
                return !isset($_GET[$param]);
            default:
                return true;
        }
    }

    /**
     * Проверка cookie.
     */
    protected static function checkCookie(array $rule): bool
    {
        $name = (string) ($rule['name'] ?? '');
        $operator = (string) ($rule['operator'] ?? 'equals');
        $value = (string) ($rule['value'] ?? '');

        if ($name === '') {
            return true;
        }

        $actual = (string) ($_COOKIE[$name] ?? '');

        switch ($operator) {
            case 'equals':
                return $actual === $value;
            case 'not_equals':
                return $actual !== $value;
            case 'contains':
                return strpos($actual, $value) !== false;
            case 'exists':
                return isset($_COOKIE[$name]);
            case 'not_exists':
                return !isset($_COOKIE[$name]);
            default:
                return true;
        }
    }

    /**
     * Проверка реферера.
     */
    protected static function checkReferrer(array $rule): bool
    {
        $operator = (string) ($rule['operator'] ?? 'contains');
        $value = (string) ($rule['value'] ?? '');
        $referrer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        switch ($operator) {
            case 'contains':
                return strpos($referrer, $value) !== false;
            case 'not_contains':
                return strpos($referrer, $value) === false;
            case 'equals':
                return $referrer === $value;
            case 'starts_with':
                return strpos($referrer, $value) === 0;
            default:
                return true;
        }
    }

    /**
     * Проверка типа устройства (мобильное/десктоп).
     */
    protected static function checkDevice(array $rule): bool
    {
        $device = (string) ($rule['device'] ?? 'any');

        if ($device === 'any') {
            return true;
        }

        $isMobile = self::isMobileDevice();

        return ($device === 'mobile') ? $isMobile : !$isMobile;
    }

    /**
     * Проверка произвольного PHP условия (только для администраторов).
     */
    protected static function checkPhpCondition(array $rule): bool
    {
        $condition = trim((string) ($rule['condition'] ?? ''));

        if ($condition === '') {
            return true;
        }

        // Безопасность: только для администраторов
        $user = current_user();
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            return false;
        }

        try {
            // Выполняем условие через eval (опасно, только для админов!)
            return (bool) @eval('return ' . $condition . ';');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Определяет, является ли устройство мобильным.
     */
    protected static function isMobileDevice(): bool
    {
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        return preg_match(
            '/(android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i',
            $userAgent
        ) === 1;
    }

    /**
     * Применяет фильтр видимости к секции перед рендерингом.
     */
    public static function filterSection(array $section): ?array
    {
        if (!self::isVisible($section)) {
            return null; // Секция скрыта
        }

        // Фильтруем виджеты внутри секции
        if ($section['type'] === 'free') {
            $section['widgets'] = array_values(array_filter(
                $section['widgets'] ?? [],
                [self::class, 'isVisible']
            ));
        } else {
            foreach ($section['columns'] ?? [] as &$column) {
                $column['widgets'] = array_values(array_filter(
                    $column['widgets'] ?? [],
                    [self::class, 'isVisible']
                ));
            }
        }

        return $section;
    }

    /**
     * Применяет фильтр видимости ко всему документу.
     */
    public static function filterDocument(array $document): array
    {
        $document['sections'] = array_values(array_filter(
            array_map(
                [self::class, 'filterSection'],
                $document['sections'] ?? []
            )
        ));

        return $document;
    }
}
