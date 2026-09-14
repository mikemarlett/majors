<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\Kernel;
use Majors\Support\Request;

abstract class Controller
{
    public function __construct(protected readonly Kernel $app)
    {
    }

    abstract public function handle(Request $request): void;

    protected function redirect(string $url, int $status = 302): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }

    /** Render a full page through the current design's layout and send it. */
    protected function page(string $content, array $opts = []): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->app->layout()->page($content, $opts);
    }

    protected function notFound(string $message = 'Not found'): never
    {
        http_response_code(404);
        $this->page($this->app->layout()->render('error', ['message' => $message, 'code' => 404]), ['title' => 'Not found']);
        exit;
    }
}
