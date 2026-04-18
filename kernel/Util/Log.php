<?php
declare (strict_types=1);

namespace Kernel\Util;

use Kernel\Component\Singleton;

class Log
{
    use Singleton;


    private string $path;


    public function __construct()
    {
        $config = config("database");
        $this->path = rtrim(BASE_PATH, DIRECTORY_SEPARATOR) . '/runtime/log/' . md5($config['password']);
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * @param mixed $message
     * @param string $name
     * @return void
     */
    public function write(mixed $message, string $name): void
    {
        $text = "";
        if (is_string($message) || is_bool($message) || is_numeric($message) || is_double($message) || is_float($message) || is_integer($message)) {
            $text = (string)$message;
        } elseif (is_array($message) || is_object($message)) {
            $text = PHP_EOL . json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
        file_put_contents($this->path . "/{$name}.log", "[" . date("Y-m-d H:i:s", time()) . "]:" . $text . PHP_EOL, FILE_APPEND);
    }

    /**
     * @param string $name
     * @return void
     */
    public function clear(string $name): void
    {
        file_put_contents($this->path . "/{$name}.log", "");
    }


    /**
     * @param mixed $message
     * @return void
     */
    public function debug(mixed $message): void
    {
        $this->write($message, "debug");
    }

    /**
     * @param mixed $message
     * @return void
     */
    public function error(mixed $message): void
    {
        $this->write($message, "error");
    }


    /**
     * @param string $name
     * @return string
     */
    public function get(string $name): string
    {
        return file_get_contents($this->path . "/{$name}.log") ?: "";
    }
}