<?php

namespace App\Traits;

use App\Enums\Lang;
use Illuminate\Support\Facades\App;

trait HasLocaleFields
{

    public function getLocaleValue(string $attribute, Lang $lang = null, $default = null): ?string
    {
        if ($lang === null) {
            $lang = Lang::fromValue(App::getLocale());
        }

        $defaultValue = $this->getRawOriginal($attribute) ?? $default;
        if ($lang === Lang::EN) {
            return $defaultValue;
        }

        $result = $this->getRawOriginal($attribute . '_' . $lang->value) ?? $default;
        if (empty($result)) {
            $result = $defaultValue;
        }

        return $result;
    }


}
