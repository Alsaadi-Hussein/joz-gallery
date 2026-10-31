<?php

namespace App\Models\Concerns;

trait HasLocalizedFields
{
    /**
     * The field in the current language, falling back to Arabic when the English
     * version has not been filled in.
     */
    public function localized(string $field): mixed
    {
        return $this->getAttribute($field.'_'.app()->getLocale()) ?: $this->getAttribute($field.'_ar');
    }
}
