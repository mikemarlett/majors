<?php

declare(strict_types=1);

namespace Majors;

use Majors\Auth\Csrf;
use Majors\Auth\Guard;
use Majors\Auth\IdentityProvider;
use Majors\Auth\Session;
use Majors\Auth\UserRepository;
use Majors\DegreeMaps\MapRepository;
use Majors\Http\Controller;
use Majors\Majors\ProgramRepository;
use Majors\Support\Config;
use Majors\Support\Db;
use Majors\Support\Request;
use Majors\View\Layout;
use Majors\View\Theme;
use mysqli;

/**
 * The application object every front controller gets from bootstrap.php.
 * Lazily builds the handful of services the pages share; there is no
 * container because there are about ten things to wire.
 */
final class Kernel
{
    private ?mysqli $db = null;
    private ?Request $request = null;
    private ?Theme $theme = null;
    private ?Layout $layout = null;
    private ?UserRepository $users = null;
    private ?Guard $guard = null;
    private ?Csrf $csrf = null;
    private ?MapRepository $maps = null;
    private ?ProgramRepository $programs = null;
    private bool $sessionStarted = false;

    public function __construct(
        public readonly Config $config,
        public readonly string $appRoot,
        public readonly string $webRoot,   // docroot/academics/majors (the deployed public folder)
    ) {
    }

    public function isDev(): bool
    {
        return $this->config->string('env') === 'dev';
    }

    /** www | www-dev | www-test | local — which per-site config layer applied. */
    public function site(): string
    {
        return $this->config->site();
    }

    public function docroot(): string
    {
        $d = $this->config->get('docroot');
        if (is_string($d) && $d !== '') {
            return rtrim($d, '/');
        }
        return rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    }

    public function design(): string
    {
        return $this->config->enumString('design', ['old', 'new'], 'old');
    }

    public function request(): Request
    {
        return $this->request ??= Request::fromGlobals();
    }

    public function db(): mysqli
    {
        return $this->db ??= Db::connect($this->config);
    }

    public function theme(): Theme
    {
        if ($this->theme === null) {
            $dir = $this->config->string('theme_dirs.' . $this->design(), '_resources/includes');
            if (!str_starts_with($dir, '/')) {
                $dir = $this->docroot() . '/' . $dir;
            }
            $this->theme = new Theme($dir);
        }
        return $this->theme;
    }

    public function layout(): Layout
    {
        return $this->layout ??= new Layout(
            $this->appRoot . '/templates',
            $this->design(),
            $this->theme(),
            $this->config->string('base_url', '/academics/majors'),
            $this->webRoot . '/assets',
            (array) $this->config->get('site', []),
        );
    }

    public function session(): void
    {
        if (!$this->sessionStarted) {
            Session::start($this->config, $this->request());
            $this->sessionStarted = true;
        }
    }

    public function users(): UserRepository
    {
        return $this->users ??= new UserRepository($this->db());
    }

    public function identityProvider(): IdentityProvider
    {
        return Auth\ProviderFactory::make($this->config, $this->isDev());
    }

    public function guard(): Guard
    {
        $this->session();
        // No database until a page actually needs the user list (logout must work without one).
        return $this->guard ??= new Guard($this, $this->csrf());
    }

    public function csrf(): Csrf
    {
        $this->session();
        return $this->csrf ??= new Csrf();
    }

    public function maps(): MapRepository
    {
        return $this->maps ??= new MapRepository($this->db());
    }

    public function programs(): ProgramRepository
    {
        return $this->programs ??= new ProgramRepository($this->db());
    }

    /** Load one of the legacy function files (transitional). */
    public function legacy(string $file): void
    {
        $this->db(); // ensures $GLOBALS['mysqli'] exists for `global $mysqli`
        require_once $this->appRoot . '/src/Legacy/' . basename($file);
    }

    /** Instantiate a controller and let it handle the current request. */
    public function run(string $controllerClass): void
    {
        $this->configureErrors();
        try {
            /** @var Controller $controller */
            $controller = new $controllerClass($this);
            $controller->handle($this->request());
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    private function configureErrors(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $this->isDev() ? '1' : '0');
        ini_set('log_errors', '1');
    }

    private function fail(\Throwable $e): never
    {
        error_log(sprintf('[majors] %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
        if (!headers_sent()) {
            http_response_code(500);
        }
        if ($this->request()->isAjax() || str_ends_with($this->request()->path(), '/ajax.php') || str_ends_with($this->request()->path(), '/search.php')) {
            Support\Json::fail($this->isDev() ? $e->getMessage() : 'Server error', 500);
        }
        if ($this->isDev()) {
            echo '<pre>' . Support\Html::e((string) $e) . '</pre>';
        } else {
            echo '<p>Sorry, something went wrong. Please try again later.</p>';
        }
        exit;
    }
}
