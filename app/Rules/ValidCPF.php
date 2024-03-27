<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Validation\Validator;
use App\Utils\Utils;

class ValidCPF implements ValidationRule
{

    protected $paymentMethod;

    public function __construct($paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->paymentMethod=="PIX" && !Utils::validCPF($value)) {
            $fail('validation.ValidCPF')->translate();
        }
    }
}
