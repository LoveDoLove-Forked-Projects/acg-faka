<?php
declare (strict_types=1);

namespace Kernel\Util;

use App\Model\Config;
use App\Util\Client;
use Kernel\Context\Interface\Request;

class RequestLogger
{

    /**
     * 记录当前请求
     */
    public static function logCurrentRequest(Request $request): void
    {
        if (!file_exists(BASE_PATH . '/kernel/Install/Lock')) {
            return;
        }
        if (Config::get("request_log") == 1) {
            return;
        }

        $config = config("database");
        try {
            $baseDir = rtrim(BASE_PATH, DIRECTORY_SEPARATOR) . '/runtime/request/' . md5($config['password']);
            $logFile = $baseDir . '/' . date('Y-m-d') . '.log';

            self::ensureDirectory($baseDir);

            $data = [
                'time' => Date::current(),
                'ip' => Client::getAddress(),
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'uri' => $request->uri(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referer' => $_SERVER['HTTP_REFERER'] ?? '',
                'get' => $request->get(),
                'post' => $request->post(),
                'raw_body' => $request->raw(),
                'cookies' => $request->cookie(),
                'headers' => $request->header()
            ];

            $json = json_encode(
                $data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if ($json === false) {
                $json = json_encode([
                    'time' => date('Y-m-d H:i:s'),
                    'error' => 'json_encode failed',
                    'json_last_error_msg' => json_last_error_msg(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            file_put_contents($logFile, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // 日志记录失败时，不影响主业务
            $fallbackDir = rtrim(BASE_PATH, DIRECTORY_SEPARATOR) . '/runtime/request/' . md5($config['password']);
            if (!is_dir($fallbackDir)) {
                @mkdir($fallbackDir, 0777, true);
            }

            $fallbackFile = $fallbackDir . '/logger_error.log';
            $msg = '[' . date('Y-m-d H:i:s') . '] '
                . $e->getMessage()
                . ' in ' . $e->getFile()
                . ':' . $e->getLine()
                . PHP_EOL;

            @file_put_contents($fallbackFile, $msg, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * @param string $dir
     * @return void
     */
    private static function ensureDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('创建日志目录失败');
        }
    }
}