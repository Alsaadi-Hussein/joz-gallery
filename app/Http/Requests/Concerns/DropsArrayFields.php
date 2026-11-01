<?php

namespace App\Http\Requests\Concerns;

trait DropsArrayFields
{
    /**
     * Every field on these forms is plain text, but anyone can post name[]=x. An array
     * crashes the request here, or the page that shows the form again, so it is treated
     * as left empty and fails validation like any other missing field.
     */
    protected function dropArrayFields(): void
    {
        $fields = array_map(fn (mixed $value) => is_array($value) ? null : $value, $this->all());

        $this->merge($fields);

        // A form request is a copy, and a failed one sends the original back to the form.
        request()->merge($fields);
    }
}
