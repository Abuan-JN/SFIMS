<?php

namespace App\Exceptions;

class Handler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(\Throwable $exception): void
    {
        $statusCode = $exception instanceof \App\Exceptions\HttpException 
            ? $exception->getStatusCode() 
            : 500;

        error_log($exception->getMessage() . "\n" . $exception->getTraceAsString());

        if (ini_get('display_errors')) {
            self::renderDevelopmentError($exception, $statusCode);
        } else {
            self::renderProductionError($statusCode);
        }
    }

    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::handleException(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    private static function renderDevelopmentError(\Throwable $exception, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Error {$statusCode}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
                .error-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #e74c3c; margin-top: 0; }
                .message { background: #fee; padding: 15px; border-left: 4px solid #e74c3c; margin: 20px 0; }
                .trace { background: #f8f8f8; padding: 15px; overflow-x: auto; font-family: monospace; font-size: 12px; }
                .file { color: #666; }
            </style>
        </head>
        <body>
            <div class='error-box'>
                <h1>Error {$statusCode}</h1>
                <div class='message'>" . htmlspecialchars($exception->getMessage()) . "</div>
                <p class='file'>File: " . htmlspecialchars($exception->getFile()) . ":" . $exception->getLine() . "</p>
                <h3>Stack Trace:</h3>
                <pre class='trace'>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>
            </div>
        </body>
        </html>";
    }

    private static function renderProductionError(int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Page Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
        ];

        $message = $messages[$statusCode] ?? 'An error occurred';

        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Error {$statusCode}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; text-align: center; }
                .error-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: inline-block; }
                h1 { color: #e74c3c; margin-top: 0; font-size: 72px; }
                p { color: #666; font-size: 18px; }
                a { color: #3498db; text-decoration: none; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class='error-box'>
                <h1>{$statusCode}</h1>
                <p>{$message}</p>
                <p><a href='/'>Return to Home</a></p>
            </div>
        </body>
        </html>";
    }
}

class HttpException extends \Exception
{
    private int $statusCode;

    public function __construct(int $statusCode, string $message = '', \Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
