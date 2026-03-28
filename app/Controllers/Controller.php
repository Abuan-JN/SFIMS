<?php

namespace App\Controllers;

abstract class Controller
{
    protected function view(string $view, array $data = []): void
    {
        extract($data);
        $viewFile = dirname(__DIR__) . "/Views/{$view}.php";
        
        if (!file_exists($viewFile)) {
            throw new \Exception("View {$view} not found");
        }
        
        require_once $viewFile;
    }

    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $ruleList = explode('|', $rule);
            
            foreach ($ruleList as $r) {
                if ($r === 'required' && empty($value)) {
                    $errors[$field] = "{$field} is required";
                } elseif ($r === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = "{$field} must be a valid email";
                } elseif (strpos($r, 'min:') === 0 && strlen($value) < (int)substr($r, 4)) {
                    $errors[$field] = "{$field} must be at least " . substr($r, 4) . " characters";
                } elseif (strpos($r, 'max:') === 0 && strlen($value) > (int)substr($r, 4)) {
                    $errors[$field] = "{$field} must not exceed " . substr($r, 4) . " characters";
                }
            }
        }
        
        return $errors;
    }
}
