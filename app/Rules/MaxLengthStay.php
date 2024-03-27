<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Validation\Validator;
use Carbon\Carbon;

class MaxLengthStay implements /*DataAwareRule, */ValidationRule
{
    //protected $data = [];
    protected $checkin;
    protected $maxLength;

    public function __construct($checkin, $maxLength)
    {
        $this->checkin = $checkin;
        $this->maxLength = $maxLength;
    }
    /*
    public function setData(array $data): static
    {
        $this->data = $data;
 
        return $this;
    }*/

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $end = Carbon::parse($value);
        $start = Carbon::parse($this->checkin);

        $diffInDays = $end->diffInDays($start);
        if ($diffInDays > $this->maxLength) {
            $fail('validation.MaxLengthStay')->translate(["days"=>$this->maxLength]);
        }
    }
}
