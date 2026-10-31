<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case Owner = 'owner';
    case Staff = 'staff';

    public function getLabel(): string
    {
        return __('admin.enums.user_role.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Owner => 'primary',
            self::Staff => 'gray',
        };
    }
}
