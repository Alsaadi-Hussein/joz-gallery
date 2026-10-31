<?php

namespace App\View\Composers;

use App\Models\Category;
use App\Models\Setting;
use App\Support\Phone;
use Illuminate\View\View;

class StorefrontComposer
{
    /**
     * Gives the layout the menu sections and the store's contact details.
     */
    public function compose(View $view): void
    {
        $instagram = Setting::get('instagram');

        $view->with([
            'menuSections' => Category::query()->active()->whereNull('parent_id')
                ->with(['media', 'children' => fn ($query) => $query->active()->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get(),
            'store' => [
                'phone' => Phone::local(Setting::get('store_phone')),
                'whatsapp' => Phone::international(Setting::get('whatsapp_number')),
                'instagram' => $instagram,
                'instagram_url' => "https://instagram.com/{$instagram}",
                'address' => Setting::get('address'),
            ],
        ]);
    }
}
