<?php

namespace App\Services;

class Validator
{
    private array $errors = [];
    private array $data = [];
    private array $rules = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public function validate(): bool
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    private function applyRule(string $field, $value, string $rule): void
    {
        if (strpos($rule, ':') !== false) {
            [$ruleName, $ruleParam] = explode(':', $rule, 2);
        } else {
            $ruleName = $rule;
            $ruleParam = null;
        }

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->errors[$field] = "{$field} is required";
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field] = "{$field} must be a valid email address";
                }
                break;

            case 'min':
                if (!empty($value) && strlen($value) < (int)$ruleParam) {
                    $this->errors[$field] = "{$field} must be at least {$ruleParam} characters";
                }
                break;

            case 'max':
                if (!empty($value) && strlen($value) > (int)$ruleParam) {
                    $this->errors[$field] = "{$field} must not exceed {$ruleParam} characters";
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->errors[$field] = "{$field} must be a number";
                }
                break;

            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->errors[$field] = "{$field} must be an integer";
                }
                break;

            case 'in':
                $allowed = explode(',', $ruleParam);
                if (!empty($value) && !in_array($value, $allowed)) {
                    $this->errors[$field] = "{$field} must be one of: " . implode(', ', $allowed);
                }
                break;

            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if (!empty($value) && $value !== ($this->data[$confirmField] ?? null)) {
                    $this->errors[$field] = "{$field} confirmation does not match";
                }
                break;

            case 'unique':
                [$table, $column] = explode(',', $ruleParam);
                $model = new \App\Models\Model();
                $count = $model->query("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?", [$value]);
                if ($count[0]['count'] > 0) {
                    $this->errors[$field] = "{$field} already exists";
                }
                break;

            case 'exists':
                [$table, $column] = explode(',', $ruleParam);
                $model = new \App\Models\Model();
                $count = $model->query("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?", [$value]);
                if ($count[0]['count'] === 0) {
                    $this->errors[$field] = "{$field} does not exist";
                }
                break;

            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    $this->errors[$field] = "{$field} must be a valid date";
                }
                break;

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->errors[$field] = "{$field} must be a valid URL";
                }
                break;

            case 'alpha':
                if (!empty($value) && !preg_match('/^[a-zA-Z]+$/', $value)) {
                    $this->errors[$field] = "{$field} must contain only letters";
                }
                break;

            case 'alpha_num':
                if (!empty($value) && !preg_match('/^[a-zA-Z0-9]+$/', $value)) {
                    $this->errors[$field] = "{$field} must contain only letters and numbers";
                }
                break;

            case 'regex':
                if (!empty($value) && !preg_match($ruleParam, $value)) {
                    $this->errors[$field] = "{$field} format is invalid";
                }
                break;
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        return reset($this->errors) ?: null;
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }
}
