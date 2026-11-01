<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary file uploads
    |--------------------------------------------------------------------------
    |
    | Livewire saves a file here before the form validates it, with the extension
    | it was sent with. Its default is the site's default disk, which on this site
    | is the public one, so uploads must be sent to the private disk instead and
    | anything that is not an image refused at the door.
    |
    */

    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => ['png', 'gif', 'jpg', 'jpeg', 'webp'],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
