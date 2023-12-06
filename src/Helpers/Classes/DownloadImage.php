<?php

namespace Hamahang\LFM\Helpers\Classes;

use Hamahang\LFM\Models\File;
use Hamahang\LFM\Models\FileMimeType;
use Intervention\Image\ImageManagerStatic as Image;

class DownloadImage
{
    public const LFM_DRIVER_DISK = 'laravel_file_manager.driver_disk';
    public const LFM_DRIVER_DISK_UPLOAD = 'laravel_file_manager.driver_disk_upload';
    public const LFM_MAIN_STORAGE_FOLDER_NAME = 'laravel_file_manager.main_storage_folder_name';

    private $sizeType = 'original';
    private $notFoundImage = '404.png';
    private $notFoundImagePath = '';
    private $inlineContent = false;
    private $quality = 90;
    private $width = false;
    private $height = false;
    private $mediaTempFolderPath = '';
    private $driverDiskStorage = null;
    private $mediaTempCreated404Path = '';
    private $mediaTempCopied404Path = '';

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

    private $config;
    private $basePath;
    private $fileNameHash;
    private $mediaTempPath;

    public function __construct($fileId, $sizeType = 'original', $notFoundImage = '404.png', $inlineContent = false, $quality = 90, $width = false, $height = false)
    {
        $this->sizeType = $sizeType;
        $this->notFoundImage = $notFoundImage;
        $this->inlineContent = $inlineContent;
        $this->quality = $quality;
        $this->width = $width;
        $this->height = $height;

        $this->prepareNeededData($fileId);
    }

    private function prepareNeededData($fileId)
    {
        $this->driverDiskStorage = \Storage::disk(config(self::LFM_DRIVER_DISK));

        $this->notFoundImagePath = $this->driverDiskStorage->path(config(self::LFM_MAIN_STORAGE_FOLDER_NAME) . "/System/{$this->notFoundImage}");
        $this->mediaTempFolderPath = config(self::LFM_MAIN_STORAGE_FOLDER_NAME) . '/media_tmp_folder';
        $this->mediaTempCreated404Path = $this->mediaTempFolderPath.'/created_new_404';
        $this->mediaTempCopied404Path = $this->mediaTempFolderPath.'/copied_defualt_404';
        $this->file = File::find(LFM_GetDecodeId($fileId));

        $this->makePathIfNotExists($this->driverDiskStorage, $this->mediaTempFolderPath);
        $this->makePathIfNotExists($this->driverDiskStorage, $this->mediaTempCreated404Path);
        $this->makePathIfNotExists($this->driverDiskStorage, $this->mediaTempCopied404Path);

        $this->fileExists = $this->file != null;
        if ($this->fileExists) {
            $this->fileId = $this->file->id;
            $this->fileIsDirect = $this->file->is_direct;
            $this->filePath = $this->file->path;
            $this->fileFilename = $this->file->filename;
            $this->fileMimeType = $this->file->mimeType;
            $this->fileOriginalName = $this->file->original_name;
            $this->fileExtension = str_replace('.', '', strtolower(FileMimeType::where('mimeType', '=', $this->fileMimeType)->firstOrFail()->ext));
            $this->headers = ["Content-Type" => $this->fileMimeType, "Cache-Control" => "public", "max-age" => 31536000];

            $this->praparefilePath();
            $this->config = $this->diskConfig();
            $this->basePath = \Storage::disk($this->config)->path('');
            $this->fileNameHash = $this->hashFileName();
            $this->mediaTempPath = "{$this->basePath}{$this->mediaTempFolderPath}";
        }
    }

    public function byId()
    {
        list ( $imageFilePath, $fileName, $fileExtension ) = $this->getFinalFileInfo();

        if (!$this->inlineContent) {
            return response()->download($imageFilePath, $fileName, $this->headers);
        }

        return $this->base64ImageContent(file_get_contents($imageFilePath), $fileExtension);
    }

    private function getFinalFileInfo()
    {
        if (!$this->fileExists) {
            return file_exists($this->notFoundImagePath) ?
                $this->pathOfCopyOfNotFoundImage() :
                $this->pathOfNewNotFoundImage();
        }

        if ($this->driverDiskStorage->has("{$this->mediaTempFolderPath}/{$this->fileNameHash}")) {

            $imageFilePath  = "{$this->mediaTempPath}/{$this->fileNameHash}";
            $fileName = "{$this->fileOriginalName}.{$this->fileExtension}";
            $fileExtension  = $this->fileExtension;

            if ($this->inlineContent) {
                file_put_contents($imageFilePath, file_get_contents($this->basePath . $this->filePath));
            }

            return [$imageFilePath, $fileName, $fileExtension];
        }

        if (\Storage::disk($this->config)->has($this->filePath)) {
            return $this->getFileFromLocalStorage();
        }

        if (!file_exists($this->notFoundImagePath)) {
            return $this->pathOfNewNotFoundImage();
        }

        return $this->getNotFoundHashImage();
    }

    private function getNotFoundHashImage()
    {

        $width = $this->width ? $this->width : 640;
        $height = $this->height ? $this->height : 480;

        list($notFoundHash, $fileExtension) = $this->notFoundImageHashAndExtension($width, $height);
        
        $filePath = "{$this->mediaTempPath}/{$notFoundHash}";
        $fileName = "{$notFoundHash}.{$fileExtension}";


        if (!$this->driverDiskStorage->has(config(self::LFM_MAIN_STORAGE_FOLDER_NAME) . "/media_tmp_folder/{$notFoundHash}")) {
            $res = Image::make($this->notFoundImagePath)
                ->fit((int) $width, (int) $height)
                ->save($filePath);
        }

        return [
            $filePath,
            $fileName,
            $fileExtension
        ];

    }

    private function getFileFromLocalStorage()
    {
        $fileNameWithExtension  = $this->fileNameHash;
        $fileExtension          = $this->fileExtension;


        $fileBasePath = "{$this->basePath}{$this->filePath}";
        $filePath = "{$this->mediaTempPath}/{$this->fileNameHash}";

        if (!in_array($fileExtension, ['png', 'jpg', 'jpeg'])) {
            $fileNameWithExtension = "{$this->fileFilename}.{$fileExtension}";
            $filePath = $fileBasePath;

        }else{
            $res = Image::make($fileBasePath);

            if ($this->width && $this->height) {
                $res = $res->fit((int) $this->width, (int) $this->height);
            } else {
                $fileExtension = $this->quality < 100 ? 'jpg' : $fileExtension;
            }
            
            $fileExtension = $this->inlineContent ? 'jpg' : $fileExtension;
            $res->save($filePath, $this->quality);
        }

        return [
            $filePath,
            $fileNameWithExtension,
            $fileExtension
        ];
    }

    private function make404image($imageType = 'png')
    {
        return (new TextImageUsingGD($this->width, $this->height, $imageType))->make();
    }

    private function pathOfNewNotFoundImage()
    {
        $fileExtension = 'jpg';
        $fileName = "404_w{$this->width}_h{$this->height}";
        $fileNameFullPath = "{$this->mediaTempCreated404Path}/{$fileName}.{$fileExtension}";
        
        if ( !file_exists($fileNameFullPath) ){
            $this->make404image($fileExtension)->save($fileNameFullPath);
        }

        return [$fileNameFullPath, "{$fileName}.{$fileExtension}", $fileExtension];
    }

    private function pathOfCopyOfNotFoundImage()
    {
        $fileExtension = $this->extractFileExtension($this->notFoundImagePath);
        $fileName = "404_w{$this->width}_h{$this->height}";
        $fileNameFullPath = "{$this->mediaTempCopied404Path}/{$fileName}.{$fileExtension}";

        if ( !file_exists($fileNameFullPath) ){
            $this->makeCopyOfNotFoundImage($fileExtension)->save($fileNameFullPath);
        }

        return [$fileNameFullPath, "{$fileName}.{$fileExtension}", $fileExtension];
    }

    private function makeCopyOfNotFoundImage($fileExtension)
    {
        $res = Image::make($this->notFoundImagePath);
        if ($this->width && $this->height) {
            $res->fit((int) $this->width, (int) $this->height);
        }
        return $res->response($fileExtension, $this->quality);
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

    private function hashFileName()
    {
        $md5File = $this->driverDiskStorage->has($this->filePath) ? md5($this->driverDiskStorage->get($this->filePath)) : '';
        $hash = md5("{$this->sizeType}_{$this->notFoundImage}_{$this->inlineContent}_{$this->quality}_{$this->width}_{$this->height}_{$md5File}");
        return "tmp_fid_{$this->fileId}_{$hash}";
    }

    private function praparefilePath()
    {
        if ($this->fileIsDirect == '1') {
            $this->filePath = "{$this->filePath}{$this->fileFilename}";
        } else {
            $fileSize = $this->sizeType != 'original' ? $this->file[$this->sizeType . '_filename'] : $this->fileFilename;
            $this->filePath = "{$this->filePath}/files/{$this->sizeType}/{$fileSize}";
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
