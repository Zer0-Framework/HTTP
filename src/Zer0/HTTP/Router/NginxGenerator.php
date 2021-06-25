<?php

namespace Zer0\HTTP\Router;

use Zer0\HTTP\Exceptions\RouteNotFound;

/**
 * Class NginxGenerator
 * @package Zer0\HTTP\Router
 */
class NginxGenerator extends Basic
{

    /**
     * @var array
     */
    protected $include = [];

    /**
     * @param array $include
     * @return void
     */
    public function setInclude(array $include): void
    {
        $this->include = $include;
    }

    /**
     * @param string $inject = ''
     * @return string
     * @throws RouteNotFound
     */
    public function generate(string $inject = ''): string
    {
        $cfg = '';

        $stringMap = array_flip($this->stringMap);
        
        foreach ($this->routes as $name => $conf) {
            $conf['type'] = $conf['type'] ?? 'plain';
            if ($conf['type'] === 'websocket') {
                $location = $conf['path'];
            } elseif (isset($this->patternMap[$name])) {
                list($regex, $params) = $this->patternMap[$name];
                $location = '~ ' . $regex;
            } elseif (isset($stringMap[$name])) {
                $location = '= ' . $stringMap[$name];
                $params = [];
            } else {
                throw new RouteNotFound($name);
            }
            $cfg .= 'location ' . $location . " {\n\t#" . $name . "\n";

            // Acceptable methods
            if (isset($conf['methods'])) {
                $cfg .= "\tlimit_except " . implode(' ', $conf['methods']) ." {deny all;}\n";
            }

            // Error pages
            if (isset($conf['error_pages'])) {
                foreach ($conf['error_pages'] as $code => $location) {
                    $cfg .= "\terror_page {$code} $location;\n";
                }
            }

            // Includes
            foreach ($this->include as $file) {
                $cfg .= "\tinclude " . $file . ";\n";
            }

            if ($conf['type'] === 'websocket') {
                $cfg .= " \tproxy_pass http://" . $conf['backend'] . ";\n";
                $cfg .= " \tproxy_http_version 1.1;\n";
                $cfg .= " \tproxy_set_header Upgrade \$http_upgrade;\n";
                $cfg .= " \tproxy_set_header Connection \"Upgrade\";\n";
            } else {
                // Add params
                foreach ($params as $k => $p) {
                    ++$k;
                    if (isset($conf['defaults'][$p]) &&
                        (!is_bool($conf['defaults'][$p]) || $conf['defaults'][$p])
                    ) {
                        $cfg .= "\t" . 'fastcgi_param DEFAULT_ROUTE_' . strtoupper(ltrim(
                            $p,
                                '_'
                        )) . ' "' . addslashes($conf['defaults'][$p]) . "\";\n";
                    }
                    $cfg .= "\t" . 'fastcgi_param ROUTE_' . strtoupper(ltrim($p, '_')) . ' $' . $k . ";\n";
                }

                // Add request-agnostic params
                foreach ($conf['defaults'] as $p => $v) {
                    if (!in_array($p, $params)) {
                        $cfg .= "\t" . 'fastcgi_param ROUTE_' . strtoupper(ltrim($p, '_')) . ' "' . $v . "\";\n";
                    }
                }

                $cfg .= "\tinclude fastcgi_params;\n";
                $cfg .= "\tfastcgi_param SCRIPT_FILENAME \$projectDir/vendor/zer0-framework/http/src/public/index.php;\n";
                $cfg .= "\tfastcgi_param APPNAME \"\Zer0\\FastCGI\\Application\";\n";
                $cfg .= "\tfastcgi_param REQUEST_URI \$request_uri;\n";
                $cfg .= "\tfastcgi_param ZERO_ROOT \$projectDir;\n";
                $cfg .= "\tfastcgi_param ROUTE '" . addslashes($name) . "';\n";
                if ($inject !== '') {
                    $cfg .= "\t" . $inject . "\n";
                } else {
                    $cfg .= "\tfastcgi_pass fastcgi;\n";
                }
            }

            $cfg .= "}\n\n";
        }
        return $cfg;
    }
}
