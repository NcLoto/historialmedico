<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validación de entradas. Reglas separadas por "|":
 * required, max:n, min:n, email, date, past (fecha no futura), in:a,b, int, between:a,b, decimal, bool, phone, regex:/…/
 * Los valores vacíos se normalizan a null (salvo bool).
 */
final class Validator
{
    private array $errors = [];
    private array $clean = [];

    /** @param array<string,string> $rules @param array<string,string> $labels */
    public function __construct(array $data, array $rules, array $labels = [])
    {
        foreach ($rules as $field => $ruleString) {
            $rulesList = explode('|', $ruleString);
            $label = $labels[$field] ?? ucfirst_mb(str_replace('_', ' ', $field));
            $raw = $data[$field] ?? null;
            $value = is_string($raw) ? trim($raw) : $raw;

            if (in_array('bool', $rulesList, true)) {
                $this->clean[$field] = !empty($value) && $value !== '0' ? 1 : 0;
                continue;
            }
            if ($value === '' || $value === null || is_array($value)) {
                if (in_array('required', $rulesList, true)) {
                    $this->errors[$field] = "$label es obligatorio.";
                }
                $this->clean[$field] = null;
                continue;
            }
            $this->clean[$field] = $value;
            foreach ($rulesList as $rule) {
                if ($msg = $this->check($rule, (string) $value, $label)) {
                    $this->errors[$field] = $msg;
                    break;
                }
            }
            if (!isset($this->errors[$field]) && in_array('int', $rulesList, true)) {
                $this->clean[$field] = (int) $value;
            }
        }
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    private function check(string $rule, string $v, string $label): ?string
    {
        [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
        switch ($name) {
            case 'max':
                return mb_strlen($v) > (int) $arg ? "$label no puede superar $arg caracteres." : null;
            case 'min':
                return mb_strlen($v) < (int) $arg ? "$label debe tener al menos $arg caracteres." : null;
            case 'email':
                return filter_var($v, FILTER_VALIDATE_EMAIL) ? null : "$label no es un correo válido.";
            case 'date':
            case 'past':
                $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
                if (!$d || $d->format('Y-m-d') !== $v) {
                    return "$label no es una fecha válida.";
                }
                if ($name === 'past' && $d > new \DateTimeImmutable('today')) {
                    return "$label no puede ser una fecha futura.";
                }
                return null;
            case 'in':
                return in_array($v, explode(',', (string) $arg), true) ? null : "$label tiene un valor no permitido.";
            case 'int':
                return preg_match('/^-?\d+$/', $v) ? null : "$label debe ser un número entero.";
            case 'decimal':
                return preg_match('/^-?\d+([.,]\d+)?$/', $v) ? null : "$label debe ser un número.";
            case 'between':
                [$a, $b] = explode(',', (string) $arg);
                $n = (float) str_replace(',', '.', $v);
                return ($n < (float) $a || $n > (float) $b) ? "$label debe estar entre $a y $b." : null;
            case 'phone':
                return preg_match('/^[0-9+\-\s()]{7,25}$/', $v) ? null : "$label no es un teléfono válido.";
            case 'regex':
                return preg_match((string) $arg, $v) ? null : "$label tiene un formato no válido.";
        }
        return null;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    public function clean(): array
    {
        return $this->clean;
    }
}
