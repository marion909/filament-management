<?php

declare(strict_types=1);

namespace Filament\Security;

/**
 * Input Validation Service
 */
class InputValidator
{
    private array $errors = [];
    
    public function validate(array $data, array $rules): array
    {
        $this->errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $this->validateField($field, $value, $fieldRules);
        }
        
        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'data' => $this->sanitizeData($data)
        ];
    }
    
    private function validateField(string $field, $value, array $rules): void
    {
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $this->applyRule($field, $value, $rule);
            } elseif (is_array($rule)) {
                $ruleName = $rule[0];
                $ruleParams = array_slice($rule, 1);
                $this->applyRule($field, $value, $ruleName, $ruleParams);
            }
        }
    }
    
    private function applyRule(string $field, $value, string $rule, array $params = []): void
    {
        switch ($rule) {
            case 'required':
                if (empty($value)) {
                    $this->errors[$field][] = "Field $field is required";
                }
                break;
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = "Field $field must be a valid email";
                }
                break;
            case 'min':
                $min = $params[0] ?? 0;
                if (strlen($value) < $min) {
                    $this->errors[$field][] = "Field $field must be at least $min characters";
                }
                break;
            case 'max':
                $max = $params[0] ?? 255;
                if (strlen($value) > $max) {
                    $this->errors[$field][] = "Field $field must not exceed $max characters";
                }
                break;
        }
    }
    
    private function sanitizeData(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
}