/**
 * Временная страница для диагностики CSRF
 * Откройте: /admin/csrf-debug
 */

// Добавьте в app/AdminController.php:

public function csrfDebug(Application $app): Response
{
    if (!is_logged_in()) {
        return (new Response())->setStatus(403)->setBody('Access denied');
    }

    $debug = [
        'Current CSRF Token' => Csrf::token(),
        'Session Status' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive',
        'Session ID' => session_id(),
        'Session Data' => $_SESSION ?? [],
        'Cookies' => $_COOKIE ?? [],
        'Request Method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'User Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    ];

    // Тест формы
    $testForm = '
    <h2>Test CSRF Form</h2>
    <form method="POST" action="/admin/csrf-test">
        ' . Csrf::field() . '
        <button type="submit">Test Submit</button>
    </form>
    ';

    $html = '<html><head><title>CSRF Debug</title></head><body>';
    $html .= '<h1>CSRF Debug Information</h1>';
    $html .= '<pre>' . print_r($debug, true) . '</pre>';
    $html .= $testForm;
    $html .= '</body></html>';

    return (new Response())->setBody($html);
}

public function csrfTest(Application $app): Response
{
    $token = (string) $app->request->input('_csrf', '');
    $valid = Csrf::verify($token);

    $html = '<html><head><title>CSRF Test Result</title></head><body>';
    $html .= '<h1>CSRF Test Result</h1>';
    $html .= '<p>Token: <code>' . e($token) . '</code></p>';
    $html .= '<p>Valid: <strong>' . ($valid ? 'YES ✓' : 'NO ✗') . '</strong></p>';
    $html .= '<p><a href="/admin/csrf-debug">Back to Debug</a></p>';
    $html .= '</body></html>';

    return (new Response())->setBody($html);
}
