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

        $result = $this->getRawOriginal($attribute . '_' . $lang->value) ?? $default;
        if (empty($result)) {
            $result = $default;
        }

        // Russian is optional in the admin, so fall back to the other languages (English first)
        // instead of returning an empty value for visitors browsing in a language that wasn't filled in.
        if (empty($result)) {
            foreach ([Lang::default(), Lang::RU, Lang::UZ] as $fallback) {
                $value = $this->getRawOriginal($attribute . '_' . $fallback->value);
                if (! empty($value)) {
                    return $value;
                }
            }
        }

        return $result;
    }


}
