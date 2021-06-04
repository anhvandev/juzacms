<?php

namespace Modules\Movie\Http\Controllers\Frontend\Stream;

use App\Core\Helpers\GoogleDrive;
use Mymo\Core\Http\Controllers\BackendController;
use Illuminate\Support\Facades\Crypt;

class GoogleDriveController extends BackendController
{
    public function stream($file, $quality = '360p') {
        $stream = json_decode(Crypt::decryptString(base64_decode(urldecode($file))));
        $gdrive = new GoogleDrive();
        $gdrive->stream($stream->file, $quality);
        die();
    }
}
