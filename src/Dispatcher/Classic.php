<?php

namespace FastRoute\Dispatcher;

use FastRoute\Dispatcher;

class Classic implements Dispatcher{
    protected $staticRouteMap;
    protected $variableRouteData;

    public function __construct($data) {
        list($this->staticRouteMap, $this->variableRouteData) = $data;
    }

    public function dispatch($httpMethod, $uri) {
        if (isset($this->staticRouteMap[$httpMethod][$uri])) {
            $handler = $this->staticRouteMap[$httpMethod][$uri];
            return [self::FOUND, $handler, []];
        } else if ($httpMethod === 'HEAD' && isset($this->staticRouteMap['GET'][$uri])) {
            $handler = $this->staticRouteMap['GET'][$uri];
            return [self::FOUND, $handler, []];
        }

        $varRouteData = $this->variableRouteData;
        if (isset($varRouteData[$httpMethod])) {
            $result = $this->dispatchVariableRoute($varRouteData[$httpMethod], $uri);
            if ($result[0] === self::FOUND) {
                return $result;
            }
        } else if ($httpMethod === 'HEAD' && isset($varRouteData['GET'])) {
            $result = $this->dispatchVariableRoute($varRouteData['GET'], $uri);
            if ($result[0] === self::FOUND) {
                return $result;
            }
        }

        // Find allowed methods for this URI by matching against all other HTTP methods as well
        $allowedMethods = [];

        foreach ($this->staticRouteMap as $method => $uriMap) {
            if ($method !== $httpMethod && isset($uriMap[$uri])) {
                $allowedMethods[] = $method;
            }
        }

        foreach ($varRouteData as $method => $routeData) {
            if ($method === $httpMethod) {
                continue;
            }

            $result = $this->dispatchVariableRoute($routeData, $uri);
            if ($result[0] === self::FOUND) {
                $allowedMethods[] = $method;
            }
        }

        // If there are no allowed methods the route simply does not exist
        if ($allowedMethods) {
            return [self::METHOD_NOT_ALLOWED, $allowedMethods];
        } else {
            return [self::NOT_FOUND];
        }
    }

    protected function dispatchVariableRoute($routeData, $uri) {
        foreach ($routeData as $regex => $route) {
            if (!preg_match('~^' . $regex. '$~', $uri, $matches)) {
                continue;
            }
            $vars = [];
            $i = 0;
            foreach ($route->variables as $varName) {
                $vars[$varName] = $matches[++$i];
            }
            return [self::FOUND, $route->handler, $vars];
        }

        return [self::NOT_FOUND];
    }
}
