<?php
namespace WPForge\Security;

/**
 * General-purpose input validator for WPForge requests.
 */
class Validator
{
    /**
     * Validate that a required parameter is present.
     */
    public static function required(mixed $value, string $name): true|\WP_Error
    {
        if ($value === null || $value === '') {
            return new \WP_Error('missing_field', sprintf('"%s" is required.', $name));
        }
        return true;
    }

    /**
     * Validate that a value is a valid integer within a range.
     */
    public static function integer(mixed $value, string $name, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): true|\WP_Error
    {
        if ($value === null || $value === '') {
            return true; // optional
        }
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($int === false) {
            return new \WP_Error('invalid_value', sprintf('"%s" must be an integer between %d and %d.', $name, $min, $max));
        }
        return true;
    }

    /**
     * Validate that a value is a valid email.
     */
    public static function email(mixed $value, string $name): true|\WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!is_email($value)) {
            return new \WP_Error('invalid_value', sprintf('"%s" must be a valid email address.', $name));
        }
        return true;
    }

    /**
     * Validate that a value is one of the allowed values.
     */
    public static function oneOf(mixed $value, string $name, array $allowed): true|\WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!in_array($value, $allowed, true)) {
            return new \WP_Error(
                'invalid_value',
                sprintf('"%s" must be one of: %s.', $name, implode(', ', $allowed))
            );
        }
        return true;
    }

    /**
     * Validate a string length.
     */
    public static function maxLength(string $value, string $name, int $max): true|\WP_Error
    {
        if (mb_strlen($value) > $max) {
            return new \WP_Error('invalid_value', sprintf('"%s" must be at most %d characters.', $name, $max));
        }
        return true;
    }

    /**
     * Validate that a string matches a regex pattern.
     */
    public static function pattern(string $value, string $name, string $pattern, string $patternDescription = ''): true|\WP_Error
    {
        if (!preg_match($pattern, $value)) {
            $desc = $patternDescription ?: $pattern;
            return new \WP_Error('invalid_value', sprintf('"%s" does not match the required format (%s).', $name, $desc));
        }
        return true;
    }

    /**
     * Validate that a value is a boolean (or 0/1).
     */
    public static function boolean(mixed $value, string $name): true|\WP_Error
    {
        if ($value === null) {
            return true;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($filtered === null) {
            return new \WP_Error('invalid_value', sprintf('"%s" must be a boolean.', $name));
        }
        return true;
    }

    /**
     * Validate an array of values.
     */
    public static function array(mixed $value, string $name): true|\WP_Error
    {
        if ($value === null) {
            return true;
        }
        if (!is_array($value)) {
            return new \WP_Error('invalid_value', sprintf('"%s" must be an array.', $name));
        }
        return true;
    }

    /**
     * Validate a nonce token.
     */
    public static function nonce(string $nonce, string $action): true|\WP_Error
    {
        if (!wp_verify_nonce($nonce, $action)) {
            return new \WP_Error('invalid_nonce', 'Invalid security token.');
        }
        return true;
    }

    /**
     * Run multiple validations and return all errors.
     *
     * @param array<string, mixed> $fields Field name => value pairs.
     * @param array<string, callable> $rules Field name => validator callable pairs.
     * @return \WP_Error|true
     */
    public static function validate(array $fields, array $rules): true|\WP_Error
    {
        $errors = new \WP_Error();

        foreach ($rules as $field => $validator) {
            $value = $fields[$field] ?? null;
            $result = $validator($value, $field);

            if ($result instanceof \WP_Error) {
                $errors->add($result->get_error_code(), $result->get_error_message());
            }
        }

        if (count($errors->get_error_messages()) > 0) {
            return $errors;
        }

        return true;
    }
}
