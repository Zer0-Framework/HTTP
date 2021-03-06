<?php

namespace Zer0\HTTP;

use Zer0\App;
use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\Exceptions\TemplateNotFoundException;
use Zer0\HTTP\Exceptions\Forbidden;
use Zer0\HTTP\Exceptions\HttpError;
use Zer0\HTTP\Exceptions\InternalRedirect;
use Zer0\HTTP\Exceptions\Redirect;
use Zer0\HTTP\Intefarces\ControllerInterface;
use Zer0\HTTP\Responses\Base;
use Zer0\HTTP\Responses\JSON;
use Zer0\HTTP\Responses\Template;

/**
 * Class AbstractController
 * @package Zer0\HTTP
 */
abstract class AbstractController implements ControllerInterface
{

    /**
     * @var App
     */
    protected $app;

    /**
     * @var HTTP
     */
    protected $http;

    /**
     * @var string
     */
    public $action;

    /**
     * AbstractController constructor.
     * @param HTTP $http
     * @param App $app
     */
    public function __construct(HTTP $http, App $app)
    {
        $this->app = $app;
        $this->http = $http;
    }


    /**
     * @var \Quicky
     */
    protected $tpl;

    /**
     * @var bool
     */
    protected $skipOriginCheck = false;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var string
     */
    protected $configName;

    /**
     * @param string $name
     * @return mixed
     */
    public function sessionStart(string $name = '')
    {
        return $this->app->broker('Session')->get($name)->start();
    }

    /**
     * @param string $name
     * @return boolean
     */
    public function sessionStartIfExists(string $name = ''): bool
    {
        return $this->app->broker('Session')->get($name)->startIfExists();
    }

    /**
     * @param string $layout
     * @return string
     */
    public function pjaxVersion(string $layout): string
    {
        $version = $layout . ':' . $this->app->buildTimestamp;
        if ($this->http->isPjaxRequest()) {
            $this->http->setPjaxVersion($version);
        }
        return $version;
    }

    /**
     * @return bool
     */
    public function checkOrigin (): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? null;
        if (!isset($origin) || parse_url($origin, PHP_URL_HOST) !== $_SERVER['HTTP_HOST']) {
            $params = [
                'HTTP_ORIGIN' => isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null,
                'HTTP_REFERER' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
                'HTTP_HOST' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null,
            ];
            $this->app->log(
                'checkOrigin failed',
                $params
            );

            return false;
        }

        return true;
    }

    /**
     * @throws Forbidden
     */
    public function before(): void
    {
        if ($this->configName !== null) {
            $this->config = $this->app->config->HTTP->Controllers->{$this->configName};
        }

        if (!$this->skipOriginCheck) {
            if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'OPTIONS'], true) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $checkOrigin = $this->checkOrigin();

                if (!$checkOrigin) {
                    if (!$this->app->factory('CSRF_Token')->validate()) {
                        $this->app->log(
                            'Csrf token check failed'
                        );
                        throw new Forbidden('Bad CSRF token.');
                    }
                }
            }
        }

        if ($this->http->isPjaxRequest()) {
            $query = http_build_query(array_diff_key($_GET, ['_pjax' => true]));
            $this->http->header('X-PJAX-URL: ' . $_SERVER['DOCUMENT_URI'] . ($query !== '' ? '?' . $query : ''));
        }
    }

    /**
     *
     */
    public function after(): void
    {
    }

    /**
     *
     */
    public function onException(\Throwable $exception) {
    }
    
    /**
     * @param $response
     * @param bool $fetch
     * @return string|null
     * @throws TemplateNotFoundException
     */
    public function renderResponse($response, bool $fetch = false): ?string
    {
        if (!$response instanceof Base) {
            $response = new JSON($response);
        }
        
        $response->setController($this);
        return $response->render($this->http, $fetch);
    }
}
