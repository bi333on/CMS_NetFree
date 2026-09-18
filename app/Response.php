<?php

declare(strict_types=1);

namespace NetFree;

class Response
{
    protected int $status = 200;

    /**
     * Заголовки по умолчанию. X-Frame-Options: SAMEORIGIN не мешает iframe-холсту
     * конструктора (он загружается с того же origin) и защищает от clickjacking.
     */
    protected array $headers = [
        'Content-Type'           => 'text/html; charset=utf-8',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options'        => 'SAMEORIGIN',
        'Referrer-Policy'        => 'same-origin',
    ];
    protected string $body = '';

    public function setStatus(int $code): static
    {
        $this->status = $code;
        return $this;
    }

    public function setHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;
        return $this;
    }

    public function json($data, int $status = 200): static
    {
        $this->status = $status;
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';
        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this;
    }

    public function redirect(string $url, int $status = 302): static
    {
        $this->status = $status;
        $this->headers['Location'] = $url;
        $this->body = '';
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
