<?php

namespace Hamahang\LFM\Helpers\Classes;

use Hamahang\LFM\Models\File;
use Hamahang\LFM\Models\Category;
use Hamahang\LFM\Models\FileMimeType;
use Intervention\Image\Facades\Image;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Intervention\Image\ImageManager;


class Media
{
    // ---- Constants Section ---- //
    const LFM_DRIVER_DISK = 'laravel_file_manager.driver_disk';
    const LFM_DRIVER_DISK_UPLOAD = 'laravel_file_manager.driver_disk_upload';
    const LFM_MAIN_STORAGE_FOLDER_NAME = 'laravel_file_manager.main_storage_folder_name';
    const LFM_404_DEFAULT = 'vendor/hamahang/laravel_file_manager/src/Storage/SystemFiles/404.png';
    const IRANSANSWEB_FONT ='/../../assets/fonts/IranSans/ttf/IRANSansWeb.ttf';

    // ---- Static Needed Variables ---- //
    static $imageTypesList = ['png', 'gif', 'jpg'];
    static $filterOptions = [ 'options' => [ 'min_range' => 0, 'max_range' => 9999 ] ];

    public static function make404Image2($type = 'png', $text = '404', $bg = 'CC0099', $color = 'FFFFFF', $imgWidth = '640', $imgHeight = '480')
    {
        $manager = new ImageManager(['driver' => 'imagick']);

        $fontFile = realpath(__DIR__) . DIRECTORY_SEPARATOR . '/../../assets/fonts/IranSans/ttf/IRANSansWeb.ttf';
        $fontSize = 24;
        $text = 404;

        $fontSize = round(($imgWidth - 50) / 8);
        $fontSize = $fontSize <= 9 ? 9 : $fontSize;

        $textBox = imagettfbbox($fontSize, 0, $fontFile, $text);

        while ($textBox[4] >= $imgWidth)
        {
            $fontSize -= round($fontSize / 2);
            $textBox = imagettfbbox($fontSize, 0, $fontFile, $text);
            if ($fontSize <= 9)
            {
                $fontSize = 9;
                break;
            }
        }

        $textWidth  = abs($textBox[2] - $textBox[0]);
        $textHeight = abs($textBox[1] - $textBox[7]);

        // $textX      = ($imgWidth - $textWidth) / 2;
        // $textY      = ($imgHeight - $textHeight) / 2;

        $textX = ($imgWidth) / 2;
        $textY = ($imgHeight) / 2;


        // use callback to define details
        $img = $manager->canvas($imgWidth, $imgHeight);

        $img->fill($bg);

        $img->line(0, $imgHeight / 2, $imgWidth, $imgHeight / 2, function ($draw) {
            $draw->color('#ffffff');
        });

        $img->line($imgWidth / 2, 0, $imgWidth / 2, $imgHeight, function ($draw) {
            $draw->color('#ffffff');
        });

        $img->text($text, 0, 0, function($font) use ($fontFile, $fontSize, $color) {
            $font->file($fontFile);
            $font->size($fontSize);
            $font->color($color);
            $font->valign('middle');
        });

        $img->save(base_path("public/404.{$type}"));
    }

    static function makeImage($imgWidth = '640', $imgHeight = '480', $imageType = 'png', $text = '404', $backgroundColor = 'CC0099', $textColor = 'FFFFFF')
    {
        // prepare image type 
        $imageType = strtolower($imageType);
        $imageType = in_array($imageType, self::$imageTypesList) ? $imageType : 'jpg';

        // image width and height must be in valid range and number
        $imgWidth   = filter_var($imgWidth,  FILTER_VALIDATE_INT, self::$filterOptions) ? $imgWidth  : '640';
        $imgHeight  = filter_var($imgHeight, FILTER_VALIDATE_INT, self::$filterOptions) ? $imgHeight : '480';

        // convert encoding of text 
        $text = mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text, 'UTF-8, ISO-8859-1'));
        $text = mb_encode_numericentity($text, [0x0, 0xffff, 0, 0xffff], 'UTF-8');

        // dispatch text and background color
        list($bgRed, $bgGreen, $bgBlue)             = sscanf($backgroundColor, "%02x%02x%02x");
        list($colorRed, $colorGreen, $colorBlue)    = sscanf($textColor, "%02x%02x%02x");

        // use IRANSansWeb Font if exists or arial
        $fontFile = realpath(__DIR__) . self::IRANSANSWEB_FONT;
        $fontFile = !is_readable($fontFile) ? 'arial' : $fontFile;

        // prepare font size
        $fontSize = round(($imgWidth - 50) / 8);
        $fontSize = $fontSize <= 9 ? 9 : $fontSize;

        // main image resource in desired size
        $image      = imagecreatetruecolor($imgWidth, $imgHeight);

        // background and text color fill
        $colorFill  = imagecolorallocate($image, $colorRed, $colorGreen, $colorBlue);
        $bgFill     = imagecolorallocate($image, $bgRed, $bgGreen, $bgBlue);

        // fill image background
        imagefill($image, 0, 0, $bgFill);

        //calculates and returns the bounding box in pixels for a TrueType text.
        // imagettfbbox returns an array with 8 elements representing four points 
        // making the bounding box of the text on success and false on error.
        $textBox = imagettfbbox($fontSize, 0, $fontFile, $text);

        while ($textBox[4] >= $imgWidth)
        {
            $fontSize -= round($fontSize / 2);
            $textBox = imagettfbbox($fontSize, 0, $fontFile, $text);
            if ($fontSize <= 9)
            {
                $fontSize = 9;
                break;
            }
        }

        $textWidth  = abs($textBox[4] - $textBox[0]);
        $textHeight = abs($textBox[5] - $textBox[1]);
        $textX      = ($imgWidth - $textWidth) / 2;
        $textY      = ($imgHeight + $textHeight) / 2;

        imagettftext($image, $fontSize, 0, $textX, $textY, $colorFill, $fontFile, $text);

        return Image::make($image)->response($imageType);
    }

    public static function upload($file, $CustomUID = false, $CategoryID, $FileMimeType, $original_name = 'undefined', $size, $quality = 90, $crop_type = false, $height = False, $width = false)
    {
        $time = time();
        if (!$CustomUID)
        {
            if (auth()->check())
            {
                $CustomUID = auth()->id();
            }
            else
            {
                $CustomUID = 0;
            }
        }
        $extension = $FileMimeType->ext;
        $mimeType = $FileMimeType->mimeType;
        if (in_array(-2, Category::getAllParentId($CategoryID)))
        {
            $Path = config('laravel_file_manager.main_storage_folder_name') . '/share_folder/';
        }
        elseif (in_array(-1, Category::getAllParentId($CategoryID)))
        {
            $Path = config('laravel_file_manager.main_storage_folder_name') . '/public_folder/';
        }
        else
        {
            $Path = config('laravel_file_manager.main_storage_folder_name') . '/media_folder/';
        }
        $parents = Category::all_parents($CategoryID);
        $is_picture = false;
        if ($parents)
        {
            foreach ($parents as $parent)
            {
                if ($parent->parent_category && $parent->parent_category->parent_category_id != '#')
                {
                    $Path .= $parent->parent_category->title_disc . '/';
                }
            }
            if ($parent->parent_category_id != '#')
            {
                $Path .= $parent->title_disc;
            }
        }
        $original_nameWithoutExt = substr($original_name, 0, strlen($original_name) - strlen($extension) - 1);
        $OriginalFileName = LFM_Sanitize($original_nameWithoutExt);
        $extension = LFM_Sanitize($extension);

        //save data to database
        $FileSave = new File;
        $FileSave->original_name = $OriginalFileName;
        $FileSave->extension = $extension;
        $FileSave->file_mime_type_id = $FileMimeType->id;
        $FileSave->user_id = $CustomUID;
        $FileSave->category_id = $CategoryID;
        $FileSave->mimeType = $mimeType;
        $FileSave->filename = '';
        $FileSave->file_md5 = md5_file($file);
        $FileSave->size = $file->getSize();
        $FileSave->path = $Path;
        $FileSave->created_by = $CustomUID;
        $FileSave->save();
        $filename = 'fid_' . $FileSave->id . "_v0_" . '_uid_' . $CustomUID . '_' . md5_file($file) . "_" . $time . '_' . $extension;
        $FullPath = $Path . '/files/original/' . $filename;

        //upload every files in original folder
        $file_content = \File::get($file);
        \Storage::disk(config('laravel_file_manager.driver_disk'))->put($FullPath, $file_content);

        //check file is picture
        if (in_array($mimeType, config('laravel_file_manager.allowed_pic')))
        {
            $crop_database_name = self::resizeImageUpload($file, $FileSave, $FullPath, $filename);
            $is_picture = true;
            $FileSave->file_md5 = $crop_database_name['md5'];
            $FileSave->filename = $crop_database_name['original'];
            $FileSave->version = 0;
            $FileSave->large_filename = $crop_database_name['large'];
            $FileSave->large_version = 0;
            $FileSave->small_filename = $crop_database_name['small'];
            $FileSave->small_version = 0;
            $FileSave->medium_filename = $crop_database_name['medium'];
            $FileSave->medium_version = 0;
            $FileSave->size = $crop_database_name['size_original'];
            $FileSave->large_size = $crop_database_name['size_large'];
            $FileSave->medium_size = $crop_database_name['size_medium'];
            $FileSave->small_size = $crop_database_name['size_small'];
            $FileSave->save();
        }
        else
        {
            $is_picture = false;
            $FileSave->filename = $filename;
            $FileSave->save();
        }
        $result =
            [
                'id'            => LFM_getEncodeId($FileSave->id),
                'UID'           => $CustomUID,
                'Path'          => $Path,
                'Size'          => $file->getSize(),
                'FileName'      => $filename,
                'size'          => $FileSave->size,
                'icon'          => 'fa-file-o',
                'created'       => $FileSave->created_at,
                'updated'       => $FileSave->updated_at,
                'user'          => $FileSave->user_id,
                'original_name' => $OriginalFileName,
                'is_picture'    => $is_picture
            ];

        return $result;
    }

    public static function resizeImageUpload($file, $FileSave, $FullPath, $original_name, $quality = 90)
    {
        $upload_path = \Storage::disk(config('laravel_file_manager.driver_disk'))->path(config('laravel_file_manager.main_storage_folder_name') . '/media_folder/');
        $original_file = \Storage::disk(config('laravel_file_manager.driver_disk'))->path('');
        $tmp_path = \Storage::disk(config('laravel_file_manager.driver_disk'))->path(config('laravel_file_manager.main_storage_folder_name') . '/media_tmp_folder/');
        if (config('laravel_file_manager.Optimise_image'))
        {
            $optimizerChain = OptimizerChainFactory::create();
            $optimizerChain->optimize($original_file . $FullPath);
        }
        foreach (config('laravel_file_manager.crop_type') as $crop_type)
        {
            $target_path = $FileSave->path . '/files/' . $crop_type;
            if ($crop_type != 'original')
            {
                $OptionIMG = config('laravel_file_manager.size_' . $crop_type);
                $filename = 'fid_' . $FileSave->id . "_v0_" . '_uid_' . $FileSave->user_id . '_' . $crop_type . '_' . md5_file($file) . "_" . time() . '_' . $FileSave->extension;
                $crop = config('laravel_file_manager.crop_chose');

                //create directory if not exist
                if (!is_dir($tmp_path))
                {
                    \Storage::disk(config('laravel_file_manager.driver_disk'))->makeDirectory(config('laravel_file_manager.main_storage_folder_name') . '/media_tmp_folder');
                }
                switch ($crop)
                {
                    case "smart":
                        $file_cropped = LFM_SmartCropIMG($file, $OptionIMG);
                        LFM_SaveCompressImage(false, $file_cropped->oImg, $tmp_path . '/' . $filename, $FileSave->extension, $quality);
                        break;
                    case "fit":
                        $res = Image::make($file)->fit($OptionIMG['height'], $OptionIMG['width'])->save($tmp_path . '/' . $filename);
                        break;
                    case "resize":
                        $res = Image::make($file)->resize($OptionIMG['height'], $OptionIMG['width'])->save($tmp_path . '/' . $filename);
                        break;
                }
                if (config('laravel_file_manager.Optimise_image'))
                {
                    $optimizerChain->optimize($tmp_path . '/' . $filename);
                }
                $opt_name = 'fid_' . $FileSave->id . "_v0_" . 'uid_' . $FileSave->user_id . '_' . $crop_type . '_' . md5_file($tmp_path . '/' . $filename) . "_" . time() . '_' . $FileSave->extension;
                $opt_size = \Storage::disk(config('laravel_file_manager.driver_disk'))->size(config('laravel_file_manager.main_storage_folder_name') . '/media_tmp_folder/' . $filename);
                $opt_file = \Storage::disk(config('laravel_file_manager.driver_disk'))->move(config('laravel_file_manager.main_storage_folder_name') . '/media_tmp_folder/' . $filename, $FileSave->path . '/files/' . $crop_type . '/' . $opt_name);
                if ($opt_file)
                {
                    $name[ 'size_' . $crop_type ] = $opt_size;
                    $name[ $crop_type ] = $opt_name;
                }
                else
                {
                    return false;
                }
            }
            else
            {
                $name['md5'] = md5_file($original_file . $FullPath);
                $name['original'] = 'fid_' . $FileSave->id . "_v0_" . 'uid_' . $FileSave->user_id . '_' . $name['md5'] . "_" . time() . '_' . $FileSave->extension;
                if ($name['md5'] != $FileSave->file_md5)
                {
                    $opt_size = \Storage::disk(config('laravel_file_manager.driver_disk'))->size($FullPath);
                    $opt_file = \Storage::disk(config('laravel_file_manager.driver_disk'))->move($FullPath, $FileSave->path . '/files/' . $crop_type . '/' . $name['original']);
                    $name['size_original'] = $opt_size;
                }
                else
                {
                    $name['original'] = $original_name;
                    $name['size_original'] = $FileSave->size;
                }
            }
        }

        return $name;
    }

    public static function downloadById($fileId, $sizeType = 'original', $notFoundImage = '404.png', $inline_content = false, $quality = 90, $width = false, $height = False)
    {
        $driverDiskStorage = \Storage::disk(config(self::LFM_DRIVER_DISK));
        $notFoundImagePath = $driverDiskStorage->path(config(self::LFM_MAIN_STORAGE_FOLDER_NAME) . "/System/{$notFoundImage}" );

        // find file if exists
        $file = File::find(LFM_GetDecodeId($fileId));

        // file not found in database
        if (!$file) {

            // if $notFoundImage is not found 
            // then make copy from defualt image
            if (!file_exists($notFoundImagePath)){
                $res = $width || $height ? self::make404image($width, $height) : self::make404image();
            }else{
                // make a copy of $notFoundImage with desired width, height and quality
                $res = Image::make($notFoundImagePath);
                if ($width || $height){
                    $res->fit((int)$width, (int)$height);
                }
                $res = $res->response(self::extractFileExtension($notFoundImagePath), $quality);
            }

            return $inline_content ? self::base64ImageContent($res->getContent(), 'jpg') : $res;
        }

        $mediaTempFolderPath = config(self::LFM_MAIN_STORAGE_FOLDER_NAME) . '/media_tmp_folder';

        $fileName = $file->filename;

        if ($file->is_direct == '1') {
            $filePath = "{$file->path}{$fileName}";
            $config = config(self::LFM_DRIVER_DISK_UPLOAD);
        }else{
            $filePath = "{$file->path}/files/{$sizeType}/".($sizeType != 'original' ? $file[ $sizeType . '_filename' ] : $fileName);
            $config = config(self::LFM_DRIVER_DISK_UPLOAD);
        }

        $fileId            = $file->id;
        $fileMimeType      = $file->mimeType;
        $fileOriginalName  = $file->original_name;
        $basePath          = \Storage::disk($config)->path('');
        $fileNameHash      = self::hashFileName($driverDiskStorage, $filePath, $fileId, $sizeType, $notFoundImage, $inline_content, $quality, $width, $height);
        $tempPath          = "{$basePath}{$mediaTempFolderPath}/{$fileNameHash}";
        $fileExtension     = strtolower(FileMimeType::where('mimeType', '=', $fileMimeType)->firstOrFail()->ext);
        $headers           = [ "Content-Type" => $fileMimeType, "Cache-Control" => "public", "max-age" => 31536000 ];

        //check if exist in tmp folder
        if ($driverDiskStorage->has("{$mediaTempFolderPath}/{$fileNameHash}"))
        {
            if (!$inline_content){
                return response()->download($tempPath, "{$fileOriginalName}.{$fileExtension}", $headers);
            }

            $res = self::base64ImageContent(file_get_contents($basePath . $filePath), $fileExtension);
            file_put_contents($tempPath, $res);
            return $res;
        }

        self::makePathIfNotExists($driverDiskStorage, $mediaTempFolderPath);

        //check local storage for check file exist
        if (\Storage::disk($config)->has($filePath))
        {
            $file_base_path = $basePath . $filePath;

            if (!in_array($fileExtension, ['png', 'jpg', 'jpeg'])){
                return response()->download($file_base_path, "{$fileName}.{$fileExtension}", $headers);
            }

            $res = Image::make($file_base_path);

            if ($width && $height)
            {
                $res = $res->fit((int)$width, (int)$height);
            }else{
                $fileExtension = $quality < 100 ? 'jpg' : $fileExtension;
            }

            $res->save($tempPath);
            $res = $res->response($fileExtension, (int)$quality);

            return $inline_content ? self::base64ImageContent($res->getContent(), 'jpg') : $res;

        }

        $width  = $width  ? $width  : '640';
        $height = $height ? $height : '400';

        if (!file_exists($notFoundImagePath)){
            $res = self::make404image($width, $height);
            return $inline_content ? self::base64ImageContent($res->getContent(), 'jpg') : $res;
        }

        $ext = self::extractFileExtension($notFoundImagePath, 'jpg');

        $notFoundHash = "{$ext}_{$quality}_{$width}_{$height}";

        $tempPath = "{$basePath}{$mediaTempFolderPath}/{$notFoundHash}";

        if (!$driverDiskStorage->has(config(self::LFM_MAIN_STORAGE_FOLDER_NAME) . "/media_tmp_folder/{$notFoundHash}"))
        {
            $res = Image::make($notFoundImagePath)->fit((int)$width, (int)$height)->save($tempPath);
        }

        if (!isset($res))
        {
            $res = response()->download($tempPath, "{$notFoundHash}.{$ext}", $headers);
        }

        return $res;
    }

    public static function downloadByName($FileName, $not_found_img = '404.png', $size_type = 'original', $inline_content = false, $quality = 90, $width = false, $height = False)
    {
        $file = File::where('filename', '=', $FileName)->first();
        if ($file)
        {
            $id = $file->id;
        }
        else
        {
            $id = -1;
        }
        $download = self::downloadById($id, $not_found_img, $size_type, $inline_content, $quality, $width, $height);

        return $download;
    }

    public static function downloadFromPublicStorage($file_name, $path = "", $file_EXT = 'png', $headers = ["Content-Type: image/png"])
    {
        $base_path = \Storage::disk(config('laravel_file_manager.driver_disk'))->path('');
        if (\Storage::disk('public')->has($path . '/' . $file_name . '.' . $file_EXT))
        {
            $file_path = $base_path . '/public/' . $path . '/' . $file_name . '.' . $file_EXT;

            return response()->download($file_path, $file_name . "." . $file_EXT, $headers);
        }
        else
        {
            return response()->download($base_path . '/public/flags/404.png');
        }
    }

    public static function saveCropedImageBase64($data, $FileSave, $crop_type, $is_cropped = false)
    {
        $time = time();
        $base_path = \Storage::disk(config('laravel_file_manager.driver_disk'))->path('');
        switch ($crop_type)
        {
            case "original":
                $FileSave->version++;
                $filename = 'fid_' . $FileSave->id . '_v' . $FileSave->version . '_uid_' . $FileSave->user_id . '_' . md5(base64_decode($data)) . "_" . $time . '_' . $FileSave->extension;
                $orginal_path = $is_cropped ? $base_path . '/' . $FileSave->path . '/' . $filename : $base_path . '/' . $FileSave->path . '/files/original/' . $filename;
                $orginal_path = str_replace('//', '/', $orginal_path);
                \File::put($orginal_path, base64_decode($data));
                $FileSave->filename = $filename;
                $FileSave->file_md5 = md5(base64_decode($data));
                break;
            case "large":
                $FileSave->large_version++;
                $large_filename = 'fid_' . $FileSave->id . '_v' . $FileSave->large_version . '_uid_' . $FileSave->user_id . '_large_' . md5(base64_decode($data)) . "_" . $time . '_' . $FileSave->extension;
                \File::put($base_path . '/' . $FileSave->path . '/files/large/' . $large_filename, base64_decode($data));
                $FileSave->large_filename = $large_filename;
                break;
            case "medium":
                $FileSave->medium_version++;
                $medium_filename = 'fid_' . $FileSave->id . '_v' . $FileSave->medium_version . '_uid_' . $FileSave->user_id . '_medium_' . md5(base64_decode($data)) . "_" . $time . '_' . $FileSave->extension;
                \File::put($base_path . '/' . $FileSave->path . '/files/medium/' . $medium_filename, base64_decode($data));
                $FileSave->medium_filename = $medium_filename;
                break;
            case "small" :
                $FileSave->small_version++;
                $small_filename = 'fid_' . $FileSave->id . '_v' . $FileSave->small_version . '_uid_' . $FileSave->user_id . '_small_' . md5(base64_decode($data)) . "_" . $time . '_' . $FileSave->extension;
                \File::put($base_path . '/' . $FileSave->path . '/files/small/' . $small_filename, base64_decode($data));
                $FileSave->small_filename = $small_filename;
                break;
        }
        $FileSave->save();
        $result = ['ID' => $FileSave->id, 'UID' => $FileSave->user_id, 'Path' => $FileSave->path, 'Size' => $FileSave->size, 'FileName' => $FileSave->filename, 'originalFileName' => $FileSave->OriginalFileName];

        return $result;
    }

    public static function directUpload($file, $path, $FileMimeType, $quality = 90, $crop_type = false, $height = False, $width = false)
    {
        $time = time();
        $size = $file->getSize();
        if (auth()->check())
        {
            $user_id = auth()->id();
        }
        else
        {
            $user_id = 0;
        }
        $extension = $FileMimeType->ext;
        $mimeType = $FileMimeType->mimeType;
        $path .= '/';
        $is_picture = false;
        $original_name = $file->getClientOriginalName();
        $original_nameWithoutExt = substr($original_name, 0, strlen($original_name) - strlen($extension) - 1);
        $OriginalFileName = LFM_Sanitize($original_nameWithoutExt);
        $extension = LFM_Sanitize($extension);

        //save data to database
        $FileSave = new File;
        $FileSave->original_name = $OriginalFileName;
        $FileSave->extension = $extension;
        $FileSave->file_mime_type_id = $FileMimeType->id;
        $FileSave->user_id = $user_id;
        $FileSave->category_id = -5;
        $FileSave->mimeType = $mimeType;
        $FileSave->filename = '';
        $FileSave->file_md5 = md5_file($file);
        $FileSave->size = $size;
        $FileSave->path = $path;
        $FileSave->created_by = $user_id;
        $FileSave->is_direct = '1';
        $FileSave->save();
        $filename = 'fid_' . $FileSave->id . "_v0_" . '_uid_' . $user_id . '_' . md5_file($file) . "_" . $time . '_' . $extension;
        $is_picture = true;

        //upload every files in path folder
        $file_content = \File::get($file);
        \Storage::disk(config('laravel_file_manager.driver_disk_upload'))->put($path . $filename, $file_content);
        $FileSave->file_md5 = md5_file($file);
        $FileSave->filename = $filename;
        $FileSave->save();
        if (isset($file->user->name))
        {
            $username = $FileSave->user->name;
        }
        else
        {
            $username = 'Public';
        }
        $result = ['id' => LFM_getEncodeId($FileSave->id), 'UID' => $user_id, 'Path' => $path, 'size' => $size, 'FileName' => $filename, 'created' => $FileSave->created_at, 'updated' => $FileSave->updated_at, 'user' => $username, 'original_name' => $OriginalFileName, 'is_picture' => $is_picture];
        if (in_array($FileSave->mimeType, config('laravel_file_manager.allowed_pic')))
        {
            $result['icon'] = 'image';
        }
        else
        {
            $class = $FileSave->FileMimeType->icon_class;
            if ($class)
            {
                $result['icon'] = $FileSave->FileMimeType->icon_class;
            }
            else
            {
                $result['icon'] = 'fa-file-o';
            }
        }

        return $result;
    }

    public static function base64ImageContent($content, $extension)
    {
        $base64Content = base64_encode($content);
        return "data:image/{$extension};base64,{$base64Content}";
    }

    public static function extractFileExtension($filePath, $defaultExtension = 'jpg')
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return $fileExtension != ''? $fileExtension : $defaultExtension;
    }

    public static function hashFileName($storage, $filePath, $fileId, $sizeType, $notFoundImage, $inlineContent, $quality, $width, $height)
    {
        $md5File = $storage->has($filePath) ? md5($storage->get($filePath)) : '';
        $hash = md5("{$sizeType}_{$notFoundImage}_{$inlineContent}_{$quality}_{$width}_{$height}_{$md5File}");
        return "tmp_fid_{$fileId}_{$hash}";
    }

    public static function makePathIfNotExists($storage, $path)
    {
        if (!is_dir($storage->path($path)))
        {
            $storage->makeDirectory($path);
        }
    }

    public static function make404image($width = false, $height = false){
        return $width && $height ? self::makeImage($width, $height) :  self::makeImage();
    }
}
