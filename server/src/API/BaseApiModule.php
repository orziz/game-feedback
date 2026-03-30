<?php

declare(strict_types=1);

namespace GameFeedback\API;

use GameFeedback\Support\AppInputSanitizer;
use RuntimeException;


/**
 * API 模块分发器
 *
 * 负责把 action=SubModuleName/FunctionName 分发到对应文件和方法。
 */
abstract class BaseApiModule
{
    /** @var array<string, mixed> */
    protected $appConfig;

    /** @var array<string, mixed> */
    protected $dbConfig;

    /** @var string */
    protected $databaseConfigPath;

    /** @var bool */
    protected $installed;

    /** @var AppInputSanitizer */
    protected $sanitizer;

    /** @var array<string, BaseApiSubModule> */
    private $subModules = [];

    /**
     * @param array<string, mixed> $appConfig
     * @param array<string, mixed> $dbConfig
     */
    public function __construct(
        array $appConfig,
        array $dbConfig,
        string $databaseConfigPath,
        bool $installed,
        AppInputSanitizer $sanitizer
    ) {
        $this->appConfig = $appConfig;
        $this->dbConfig = $dbConfig;
        $this->databaseConfigPath = $databaseConfigPath;
        $this->installed = $installed;
        $this->sanitizer = $sanitizer;
    }

    abstract protected function moduleDirName(): string;

    public function hasAction(string $action): bool
    {
        $route = $this->parseAction($action);
        if ($route === null) {
            return false;
        }

        $subModule = $this->resolveSubModule($route['subModule']);

        return $subModule !== null && $subModule->hasActionFunction($route['function']);
    }

    public function allowsMethod(string $action, string $method): bool
    {
        $route = $this->parseAction($action);
        if ($route === null) {
            return false;
        }

        $subModule = $this->resolveSubModule($route['subModule']);
        if ($subModule === null) {
            return false;
        }

        return $subModule->allowsMethod($route['function'], $method);
    }

    public function allowsBeforeInstall(string $action): bool
    {
        $route = $this->parseAction($action);
        if ($route === null) {
            return false;
        }

        $subModule = $this->resolveSubModule($route['subModule']);
        if ($subModule === null) {
            return false;
        }

        return $subModule->allowsBeforeInstall($route['function']);
    }

    public function dispatch(string $action): void
    {
        $route = $this->parseAction($action);
        if ($route === null) {
            throw new RuntimeException('Invalid action: ' . $action);
        }

        $subModule = $this->resolveSubModule($route['subModule']);
        if ($subModule === null) {
            throw new RuntimeException('Unknown sub module: ' . $route['subModule']);
        }

        $subModule->dispatch($route['function']);
    }

    /**
     * @return array{subModule: string, function: string}|null
     */
    private function parseAction(string $action): ?array
    {
        if (preg_match('/^([A-Za-z][A-Za-z0-9_]*)\/([A-Za-z][A-Za-z0-9_]*)$/', $action, $matches) !== 1) {
            return null;
        }

        return [
            'subModule' => $matches[1],
            'function' => $matches[2],
        ];
    }

    private function resolveSubModule(string $subModuleName): ?BaseApiSubModule
    {
        if (isset($this->subModules[$subModuleName])) {
            return $this->subModules[$subModuleName];
        }

        $className = __NAMESPACE__ . '\\' . $this->moduleDirName() . '\\' . $subModuleName;
        if (!class_exists($className)) {
            return null;
        }

        $subModule = new $className(
            $this->appConfig,
            $this->dbConfig,
            $this->databaseConfigPath,
            $this->installed,
            $this->sanitizer
        );

        if (!$subModule instanceof BaseApiSubModule) {
            return null;
        }

        $this->subModules[$subModuleName] = $subModule;

        return $subModule;
    }
}
