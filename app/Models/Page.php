<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'title_ar', 'title_en', 'body_ar', 'body_en', 'meta_description', 'is_active'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasLocalizedFields;

    public const CORE_SLUGS = ['about', 'contact', 'delivery', 'returns', 'privacy'];

    public function isCore(): bool
    {
        return in_array($this->slug, self::CORE_SLUGS, true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
