<?php

namespace App\Support;

class ArabicText
{
    /**
     * Folds the spelling differences shoppers type without thinking: hamza on alef,
     * ta marbuta, alef maqsura, diacritics and kashida. "مرآة" then finds "مراه".
     */
    public static function normalize(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', mb_strtolower($text));

        return strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);
    }
}
