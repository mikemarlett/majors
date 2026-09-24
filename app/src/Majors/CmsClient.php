<?php

declare(strict_types=1);

namespace Majors\Majors;

use RuntimeException;

/**
 * The little of the Modern Campus API the importer needs, through the
 * procedural helpers every WSU box has at /data/www/config
 * (modern_campus_helper_functions.php + modern_campus_config.php): list the
 * program pages, read a page's source, and resolve {{f:…}}/{{d:…}} link tags
 * to URLs (cached in a JSON file, since a full import touches ~1,000 tags).
 */
final class CmsClient
{
    private ?string $token = null;
    /** @var array<string,string> tag => url */
    private array $tags = [];
    private bool $tagsDirty = false;

    public function __construct(
        private readonly string $site,
        private readonly string $helpers = '/data/www/config/modern_campus_helper_functions.php',
        private readonly string $config = '/data/www/config/modern_campus_config.php',
        private readonly string $tagCache = '',
    ) {
        if ($this->tagCache !== '' && is_file($this->tagCache)) {
            $this->tags = json_decode((string) file_get_contents($this->tagCache), true) ?: [];
        }
    }

    private function token(): string
    {
        if ($this->token === null) {
            if (!is_file($this->helpers) || !is_file($this->config)) {
                throw new RuntimeException("Modern Campus helpers/config not found ({$this->helpers}, {$this->config}).");
            }
            (static function (string $c, string $h): void {
                require_once $c;
                require_once $h;
            })($this->config, $this->helpers);
            $this->token = \mc_login();
        }
        return $this->token;
    }

    /** @return list<array{basename:string,path:string,date:string,size:int}> */
    public function listProgramPages(string $dir = '/academics/majors'): array
    {
        $token = $this->token(); // load the helpers before PHP looks the function up
        $list  = \mc_list_files($token, $dir, $this->site);
        $out  = [];
        foreach (($list['entries'] ?? $list) as $e) {
            $name = (string) ($e['file_name'] ?? '');
            if (!str_ends_with($name, '.pcf')) {
                continue;
            }
            $out[] = [
                'basename' => substr($name, 0, -4),
                'path'     => rtrim($dir, '/') . '/' . $name,
                'date'     => (string) ($e['file_date_format'] ?? $e['file_date'] ?? ''),
                'size'     => (int) ($e['file_size'] ?? 0),
            ];
        }
        return $out;
    }

    public function source(string $path): string
    {
        $token = $this->token();
        return \mc_get_file_source($token, $path, $this->site);
    }

    /** Replace every {{f:N}} / {{d:N}} in $html with its URL. Unknown tags are left as-is. */
    public function resolveTags(string $html): string
    {
        return (string) preg_replace_callback('/\{\{([fd]):(\d+)\}\}/', function (array $m): string {
            $tag = $m[0];
            if (!array_key_exists($tag, $this->tags)) {
                $this->tags[$tag] = $this->lookup($tag);
                $this->tagsDirty  = true;
            }
            return $this->tags[$tag] !== '' ? $this->tags[$tag] : $tag;
        }, $html);
    }

    private function lookup(string $tag): string
    {
        $token = $this->token();
        $url   = 'https://' . MC_DOMAIN . '/files/dependency?site=' . rawurlencode($this->site) . '&tag=' . rawurlencode($tag);
        $resp  = \mc_api_request($url, 'GET', ['X-Auth-Token: ' . $token]);
        $path = (string) ($resp['json']['path'] ?? '');
        if ($path === '') {
            return '';
        }
        if (str_starts_with($tag, '{{d:')) {
            return rtrim($path, '/') . '/';
        }
        return str_ends_with($path, '.pcf') ? substr($path, 0, -4) . '.php' : $path;
    }

    public function saveTagCache(): void
    {
        if ($this->tagsDirty && $this->tagCache !== '') {
            @mkdir(dirname($this->tagCache), 0775, true);
            file_put_contents($this->tagCache, json_encode($this->tags, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->tagsDirty = false;
        }
    }

    public function tagCount(): int
    {
        return count($this->tags);
    }
}
