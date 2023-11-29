<?php

namespace Hamahang\LFM\Helpers\Classes;

use Hamahang\LFM\Models\File;
use Hamahang\LFM\Models\Category;
use Hamahang\LFM\Models\FileMimeType;
use Intervention\Image\Facades\Image;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class Media
{
    const LFM_DRIVER_DISK = 'laravel_file_manager.driver_disk';
    const LFM_DRIVER_DISK_UPLOAD = 'laravel_file_manager.driver_disk_upload';
    const LFM_MAIN_STORAGE_FOLDER_NAME = 'laravel_file_manager.main_storage_folder_name';
    const LFM_404_DEFAULT = 'vendor/hamahang/laravel_file_manager/src/Storage/SystemFiles/404.png';

    static function get_file_content($not_found_default_img_path, $type = 'png', $text = '404', $bg = 'CC0099', $color = 'FFFFFF', $width = '640', $height = '480')
    {
        $size = $width . 'x' . $height;
        list($imgWidth, $imgHeight) = explode('x', $size . 'x');
        if ($imgHeight === '')
        {
            $imgHeight = $imgWidth;
        }
        $filterOptions = [
            'options' => [
                'min_range' => 0,
                'max_range' => 9999
            ]
        ];
        if (filter_var($imgWidth, FILTER_VALIDATE_INT, $filterOptions) === false)
        {
            $imgWidth = '640';
        }
        if (filter_var($imgHeight, FILTER_VALIDATE_INT, $filterOptions) === false)
        {
            $imgHeight = '480';
        }
        $encoding = mb_detect_encoding($text, 'UTF-8, ISO-8859-1');
        if ($encoding !== 'UTF-8')
        {
            $text = mb_convert_encoding($text, 'UTF-8', $encoding);
        }
        $text = mb_encode_numericentity($text,
            [0x0, 0xffff, 0, 0xffff],
            'UTF-8');
        /**
         * Handle the “bg” parameter.
         */
        list($bgRed, $bgGreen, $bgBlue) = sscanf($bg, "%02x%02x%02x");

        list($colorRed, $colorGreen, $colorBlue) = sscanf($color, "%02x%02x%02x");
        /**
         * Define the typeface settings.
         */
        $fontFile = realpath(__DIR__) . DIRECTORY_SEPARATOR . '/../../assets/fonts/IranSans/ttf/IRANSansWeb.ttf';
        if (!is_readable($fontFile))
        {
            $fontFile = 'arial';
        }
        $fontSize = round(($imgWidth - 50) / 8);
        if ($fontSize <= 9)
        {
            $fontSize = 9;
        }
        /**
         * Generate the image.
         */
        $image = imagecreatetruecolor($imgWidth, $imgHeight);
        $colorFill = imagecolorallocate($image, $colorRed, $colorGreen, $colorBlue);
        $bgFill = imagecolorallocate($image, $bgRed, $bgGreen, $bgBlue);
        imagefill($image, 0, 0, $bgFill);
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
        $textWidth = abs($textBox[4] - $textBox[0]);
        $textHeight = abs($textBox[5] - $textBox[1]);
        $textX = ($imgWidth - $textWidth) / 2;
        $textY = ($imgHeight + $textHeight) / 2;
        imagettftext($image, $fontSize, 0, $textX, $textY, $colorFill, $fontFile, $text);
        /**
         * Return the image and destroy it afterwards.
         */


        switch ($type)
        {
            case 'png':
                $img = Image::make($image);

                return $img->response('png');
                break;
            case 'gif':
                $img = Image::make($image);

                return $img->response('gif');
                break;
            case 'jpg':
            case 'jpeg':
                $img = Image::make($image);

                return $img->response('jpg');
                break;
        }
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

    public static function downloadById($fileId, $size_type = 'original', $not_found_img = '404.png', $inline_content = false, $quality = 90, $width = false, $height = False)
    {
        $driverDiskStorage  = \Storage::disk(config(self::LFM_DRIVER_DISK));

        // define some needed paths
        $mediaTempFolderPath = config(self::LFM_MAIN_STORAGE_FOLDER_NAME) . '/media_tmp_folder';
        $not_found_img_path = $driverDiskStorage->path(config(self::LFM_MAIN_STORAGE_FOLDER_NAME) . '/System/' . $not_found_img);
        $not_found_default_img_path = base_path(LFM_404_DEFAULT);
        
        // find file if exists
        $file = File::find(LFM_GetDecodeId($fileId));

        // file not found action
        if (!$file) {

            // if $not_found_img is not found 
            // then make copy from defualt image
            if (!file_exists($not_found_img_path)){
                $res = $width || $height ? 
                    self::get_file_content($not_found_default_img_path, 'png', '404', 'cecece', 'FFFFFF', $width, $height) :
                    self::get_file_content($not_found_default_img_path, 'png', '404', 'cecece', 'FFFFFF');
            }else{
                // make a copy of $not_found_img with desired width, height and quality
                $res = Image::make($not_found_img_path);
                if ($width || $height){
                    $res->fit((int)$width, (int)$height);
                }
                $res = $res->response(self::extractFileExtension($not_found_img_path), $quality);
            }

            return $inline_content ? self::base64ImageContent($res->getContent(), 'jpg') : $res;
        }

        $isDirect = $file->is_direct == '1';
        $fileName = $file->filename;
        $filePath = $isDirect ? 
            "{$file->path}{$fileName}" : 
            "{$file->path}/files/{$size_type}/".($size_type != 'original' ? $file[ $size_type . '_filename' ] : $fileName);
        $fileId = $file->id;
        $fileMimeType = $file->mimeType;
        $fileOriginalName = $file->original_name;
        $config = $isDirect ? config(self::LFM_DRIVER_DISK_UPLOAD) : config(self::LFM_DRIVER_DISK);
        $base_path = \Storage::disk($config)->path('');
        $file_name_hash = self::hashFileName($driverDiskStorage, $filePath, $fileId, $size_type, $not_found_img, $inline_content, $quality, $width, $height);
        $relative_tmp_path =  "{$mediaTempFolderPath}/{$file_name_hash}";
        $tmp_path = $base_path . $relative_tmp_path;
        $file_EXT = strtolower(FileMimeType::where('mimeType', '=', $fileMimeType)->firstOrFail()->ext);
        $headers = ["Content-Type"=>$fileMimeType,"Cache-Control"=>"public","max-age"=>31536000];

        //check if exist in tmp folder
        if ($driverDiskStorage->has($relative_tmp_path))
        {
            if (!$inline_content)
            {
                return response()->download($tmp_path, $fileOriginalName . '.' . $file_EXT, $headers);
            }

            $res = self::base64ImageContent(file_get_contents($base_path . $filePath), str_replace('.', '', $file_EXT));
            file_put_contents($tmp_path, $res);
            return $res;
        }

        self::makePathIfNotExists($driverDiskStorage, $mediaTempFolderPath);

        //check local storage for check file exist
        if (\Storage::disk($config)->has($filePath))
        {
            $file_base_path = $base_path . $filePath;

            if (!in_array($file_EXT, ['png', 'jpg', 'jpeg'])){
                return response()->download($file_base_path, $fileName . '.' . $file_EXT, $headers);
            }


            $res = Image::make($file_base_path);
                
            if ($width && $height)
            {
                $res = $res->fit((int)$width, (int)$height);
                $res->save($tmp_path);
                $res = $res->response($file_EXT, (int)$quality);
            }else{
                $fileExt = $quality < 100 ? 'jpg' : $file_EXT;
                $res->save($tmp_path);
                $res = $res->response($fileExt, (int)$quality);
            }

            return $inline_content ? self::base64ImageContent($res->getContent(), 'jpg') : $res;

        }

        $width = $width ? $width : '640';
        $height = $height ? $height : '400';

        if (!file_exists($not_found_img_path)){
            $res = self::get_file_content($not_found_default_img_path, 'png', '404', 'cecece', 'FFFFFF', $width, $height);
            return $inline_content ? self::base64ImageContent($res->getContent(), 'jpg') : $res;
        }

        $ext = self::extractFileExtension($not_found_img_path, 'jpg');

        $not_found_hash = "{$ext}_{$quality}_{$width}_{$height}";

        $relative_not_found_tmp_path = config(self::LFM_MAIN_STORAGE_FOLDER_NAME) . '/media_tmp_folder/' . $not_found_hash;

        $relative_tmp_path =  "{$mediaTempFolderPath}/{$not_found_hash}";

        $tmp_path = $base_path . $relative_tmp_path;

        if (!$driverDiskStorage->has($relative_not_found_tmp_path))
        {
            $res = Image::make($not_found_img_path)->fit((int)$width, (int)$height)->save($tmp_path);
        }

        if (!isset($res))
        {
            $res = response()->download($tmp_path, $not_found_hash . '.' . $ext, $headers);
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
}
