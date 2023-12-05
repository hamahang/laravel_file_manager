<?php

namespace Hamahang\LFM\Helpers\Classes;

use Hamahang\LFM\Models\File;
use Hamahang\LFM\Models\FileMimeType;
use Intervention\Image\ImageManagerStatic as Image;

class DownloadImage
{
    public const LFM_DRIVER_DISK               = 'laravel_file_manager.driver_disk';
    public const LFM_DRIVER_DISK_UPLOAD        = 'laravel_file_manager.driver_disk_upload';
    public const LFM_MAIN_STORAGE_FOLDER_NAME  = 'laravel_file_manager.main_storage_folder_name';
    public const LFM_404_DEFAULT               = 'vendor/hamahang/laravel_file_manager/src/Storage/SystemFiles/404.png';

    private $sizeType = 'original';
    private $notFoundImage = '404.png';
    private $notFoundImagePath = '';
    private $inlineContent = false;
    private $quality = 90;
    private $width = false;
    private $height = false;
    private $mediaTempFolderPath = '';
    private $driverDiskStorage = null;

    private $file = null;
    private $fileExists = false;
    private $fileId = null;
    private $fileIsDirect = null;
    private $filePath = null;
    private $fileFilename = null;
    private $fileMimeType = null;
    private $fileOriginalName = null;
    private $fileExtension = null;

    private $headers = [];

    public function __construct($fileId, $sizeType = 'original', $notFoundImage = '404.png', $inlineContent = false, $quality = 90, $width = false, $height = false)
    {
        $this->sizeType      = $sizeType;
        $this->notFoundImage = $notFoundImage;
        $this->inlineContent = $inlineContent;
        $this->quality       = $quality;
        $this->width         = $width;
        $this->height        = $height;

        $this->prepareNeededData($fileId);
    }

    private function prepareNeededData($fileId)
    {
        $this->driverDiskStorage = \Storage::disk(config(self::LFM_DRIVER_DISK));

        $this->notFoundImagePath    = $this->driverDiskStorage->path(config(self::LFM_MAIN_STORAGE_FOLDER_NAME) . "/System/{$this->notFoundImage}");
        $this->mediaTempFolderPath  = config(self::LFM_MAIN_STORAGE_FOLDER_NAME) . '/media_tmp_folder';
        $this->file                 = File::find(LFM_GetDecodeId($fileId));
        ;

        $this->fileExists = $this->file != null;
        if ($this->fileExists) {
            $this->fileId           = $this->file->id;
            $this->fileIsDirect     = $this->file->is_direct;
            $this->filePath         = $this->file->path;
            $this->fileFilename     = $this->file->filename;
            $this->fileMimeType     = $this->file->mimeType;
            $this->fileOriginalName = $this->file->original_name;
            $this->fileExtension    = strtolower(FileMimeType::where('mimeType', '=', $this->fileMimeType)->firstOrFail()->ext);
            $this->headers          = [ "Content-Type" => $this->fileMimeType, "Cache-Control" => "public", "max-age" => 31536000 ];
        }
    }

    public function byId()
    {
        if (!$this->fileExists) {
            if (!file_exists($this->notFoundImagePath)) {
                $res = $this->width || $this->height ? $this->make404image($this->width, $this->height) : $this->make404image();
            } else {
                $res = $this->makeCopyOfNotFoundImage();
            }
            return $this->inlineContent ? $this->base64ImageContent($res->getContent(), 'jpg') : $res;
        }

        $filePath      = $this->filePath();
        $config        = $this->diskConfig();
        $basePath      = \Storage::disk($config)->path('');
        $fileNameHash  = $this->hashFileName($filePath);
        $mediaTempPath = "{$basePath}{$this->mediaTempFolderPath}";

        //check if exist in tmp folder
        if ($this->driverDiskStorage->has("{$this->mediaTempFolderPath}/{$fileNameHash}")) {
            if (!$this->inlineContent) {
                return response()->download("{$mediaTempPath}/{$fileNameHash}", "{$this->fileOriginalName}.{$this->fileExtension}", $this->headers);
            }

            $res = $this->base64ImageContent(file_get_contents($basePath . $filePath), $this->fileExtension);
            file_put_contents("{$mediaTempPath}/{$fileNameHash}", $res);
            return $res;
        }

        $this->makePathIfNotExists($this->driverDiskStorage, $this->mediaTempFolderPath);

        //check local storage for check file exist
        if (\Storage::disk($config)->has($filePath)) {
            $file_base_path = $basePath . $filePath;

            if (!in_array($this->fileExtension, ['png', 'jpg', 'jpeg'])) {
                return response()->download($file_base_path, "{$this->fileFilename}.{$this->fileExtension}", $this->headers);
            }

            $res = Image::make($file_base_path);

            if ($this->width && $this->height) {
                $res = $res->fit((int)$this->width, (int)$this->height);
            } else {
                $this->fileExtension = $this->quality < 100 ? 'jpg' : $this->fileExtension;
            }

            $res = $res->save("{$mediaTempPath}/{$fileNameHash}")->response($this->fileExtension, (int)$this->quality);

            return $this->inlineContent ? $this->base64ImageContent($res->getContent(), 'jpg') : $res;

        }

        $width  = $this->width ? $this->width : '640';
        $height = $this->height ? $this->height : '400';

        if (!file_exists($this->notFoundImagePath)) {
            $res = $this->make404image($width, $height);
            return $this->inlineContent ? $this->base64ImageContent($res->getContent(), 'jpg') : $res;
        }

        list($notFoundHash, $ext) = $this->notFoundImageHashAndExtension($width, $height);

        if (!$this->driverDiskStorage->has(config(self::LFM_MAIN_STORAGE_FOLDER_NAME) . "/media_tmp_folder/{$notFoundHash}")) {
            $res = Image::make($this->notFoundImagePath)
                    ->fit((int)$width, (int)$height)
                    ->save("{$mediaTempPath}/{$notFoundHash}");
        }

        if (!isset($res)) {
            $res = response()->download("{$mediaTempPath}/{$notFoundHash}", "{$notFoundHash}.{$ext}", $this->headers);
        }

        return $res;
    }

    private function make404image($width = false, $height = false)
    {
        if ($width && $height) {
            $textImage = new TextImage($width, $height);
            return $textImage->make();
        }

        $textImage = new TextImage();
        return $textImage->make();
    }

    private function makeCopyOfNotFoundImage()
    {
        $res = Image::make($this->notFoundImagePath);
        if ($this->width || $this->height) {
            $res->fit((int)$this->width, (int)$this->height);
        }
        return $res->response($this->extractFileExtension($this->notFoundImagePath), $this->quality);
    }

    private function extractFileExtension($filePath, $defaultExtension = 'jpg')
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return $fileExtension != '' ? $fileExtension : $defaultExtension;
    }

    private function base64ImageContent($content, $extension)
    {
        $base64Content = base64_encode($content);
        return "data:image/{$extension};base64,{$base64Content}";
    }

    private function makePathIfNotExists($storage, $path)
    {
        if (!is_dir($storage->path($path))) {
            $storage->makeDirectory($path);
        }
    }

    private function hashFileName($filePath)
    {
        $md5File = $this->driverDiskStorage->has($filePath) ? md5($this->driverDiskStorage->get($filePath)) : '';
        $hash = md5("{$this->sizeType}_{$this->notFoundImage}_{$this->inlineContent}_{$this->quality}_{$this->width}_{$this->height}_{$md5File}");
        return "tmp_fid_{$this->fileId}_{$hash}";
    }

    private function filePath()
    {
        if ($this->fileIsDirect == '1') {
            return "{$this->filePath}{$this->fileFilename}";
        } else {
            return "{$this->filePath}/files/{$this->sizeType}/".($this->sizeType != 'original' ? $this->file[ $this->sizeType . '_filename' ] : $this->fileFilename);
        }
    }

    private function diskConfig()
    {
        if ($this->fileIsDirect == '1') {
            return config(self::LFM_DRIVER_DISK_UPLOAD);
        } else {
            return config(self::LFM_DRIVER_DISK);
        }
    }

    private function notFoundImageHashAndExtension($width, $height)
    {
        $ext = $this->extractFileExtension($this->notFoundImagePath, 'jpg');
        return [
            "{$ext}_{$this->quality}_{$width}_{$height}",
            $ext
        ];
    }


}
