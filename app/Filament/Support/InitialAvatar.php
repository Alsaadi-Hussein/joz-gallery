<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament's default avatar sends each staff member's name to ui-avatars.com on
 * every admin page. This draws the first letter here instead, so nothing leaves.
 */
class InitialAvatar implements AvatarProvider
{
    private const BACKGROUND = '#5b3e34';

    public function get(Model|Authenticatable $record): string
    {
        $initial = e(mb_substr(Filament::getNameForDefaultAvatar($record), 0, 1));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">'
            .'<rect width="40" height="40" fill="'.self::BACKGROUND.'"/>'
            .'<text x="20" y="27" font-family="sans-serif" font-size="20" fill="#fff" text-anchor="middle">'.$initial.'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
