<?php

namespace App\Filament\Support;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

/**
 * The rules every uploaded image shares. Image conversions run inside the upload
 * request and decode the whole picture, about 5 MB of memory per megapixel, so a
 * 12 MP phone photo crashes a host with the common 128M memory limit.
 */
class ImageUpload
{
    // Shrunk in the browser to this, which also makes uploads quick on mobile data.
    private const MAX_SIDE = 2400;

    // Refused above this, for a file that reached the server without being shrunk.
    private const MAX_SIDE_ON_SERVER = 6000;

    private const MAX_KILOBYTES = 5120;

    public static function configure(SpatieMediaLibraryFileUpload $upload): SpatieMediaLibraryFileUpload
    {
        return $upload
            ->image()
            ->imageEditor()
            ->imageResizeMode('contain')
            ->imageResizeTargetWidth((string) self::MAX_SIDE)
            ->imageResizeTargetHeight((string) self::MAX_SIDE)
            ->imageResizeUpscale(false)
            ->maxSize(self::MAX_KILOBYTES)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->rules(['dimensions:max_width='.self::MAX_SIDE_ON_SERVER.',max_height='.self::MAX_SIDE_ON_SERVER])
            ->validationMessages([
                'mimetypes' => __('admin.common.image_types'),
                'dimensions' => __('admin.common.image_dimensions', ['size' => self::MAX_SIDE_ON_SERVER]),
            ]);
    }
}
