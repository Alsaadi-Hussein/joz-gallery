<?php

use App\Support\Phone;

it('normalizes the ways people write an Iraqi mobile', function (string $typed) {
    expect(Phone::normalize($typed))->toBe('07701234567');
})->with([
    'plain' => '07701234567',
    'with spaces' => '0770 123 4567',
    'with dashes' => '0770-123-4567',
    'country code' => '+9647701234567',
    'country code with spaces' => '+964 770 123 4567',
    'double zero' => '009647701234567',
    'no plus' => '9647701234567',
    'arabic digits' => '٠٧٧٠١٢٣٤٥٦٧',
    'persian digits' => '۰۷۷۰۱۲۳۴۵۶۷',
]);

it('accepts the Iraqi mobile prefixes', function (string $number) {
    expect($number)->toMatch(Phone::PATTERN);
})->with(['07501234567', '07701234567', '07801234567', '07901234567']);

it('rejects anything else', function (string $number) {
    expect(Phone::normalize($number))->not->toMatch(Phone::PATTERN);
})->with([
    'too short' => '0770123456',
    'too long' => '077012345678',
    'landline' => '0612345678',
    'unknown prefix' => '07601234567',
    'letters' => '0770abc4567',
]);

it('formats numbers for display', function () {
    expect(Phone::local('07740243538'))->toBe('0774 024 3538')
        ->and(Phone::international('9647740243538'))->toBe('+964 774 024 3538');
});
