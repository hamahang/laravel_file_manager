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
        $this->file = File::find(LFM_GetDecodeId($fileId));
        ;

        $this->fileExists = $this->file != null;
        if ($this->fileExists) {
            $this->fileId = $this->file->id;
            $this->fileIsDirect = $this->file->is_direct;
            $this->filePath = $this->file->path;
            $this->fileFilename = $this->file->filename;
            $this->fileMimeType = $this->file->mimeType;
            $this->fileOriginalName = $this->file->original_name;
            $this->fileExtension = strtolower(FileMimeType::where('mimeType', '=', $this->fileMimeType)->firstOrFail()->ext);
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
        if (!$this->fileExists) {
            return $this->makeCopyOfNotFoundImageOrMake404();
        }

        if ($this->driverDiskStorage->has("{$this->mediaTempFolderPath}/{$this->fileNameHash}")) {
            return $this->getHashNamedFileFromTemp();
        }

        $this->makePathIfNotExists($this->driverDiskStorage, $this->mediaTempFolderPath);

        if (\Storage::disk($this->config)->has($this->filePath)) {
            return $this->getFileFromLocalStorage();
        }

        $width = $this->width ? $this->width : '640';
        $height = $this->height ? $this->height : '400';

        if (!file_exists($this->notFoundImagePath)) {
            $res = $this->make404image($width, $height);
            return $this->inlineContent ? $this->base64ImageContent($res->getContent(), 'jpg') : $res;
        }

        return $this->getNotFoundHashImage($width, $height);
    }

    private function getNotFoundHashImage($width, $height)
    {
        list($notFoundHash, $ext) = $this->notFoundImageHashAndExtension($width, $height);

        if (!$this->driverDiskStorage->has(config(self::LFM_MAIN_STORAGE_FOLDER_NAME) . "/media_tmp_folder/{$notFoundHash}")) {
            $res = Image::make($this->notFoundImagePath)
                ->fit((int) $width, (int) $height)
                ->save("{$this->mediaTempPath}/{$notFoundHash}");
        }

        if (!isset($res)) {
            $res = response()->download("{$this->mediaTempPath}/{$notFoundHash}", "{$notFoundHash}.{$ext}", $this->headers);
        }

        return $res;
    }

    private function getHashNamedFileFromTemp()
    {
        $fileFullPath = "{$this->mediaTempPath}/{$this->fileNameHash}";

        if (!$this->inlineContent) {
            $originalFileName = "{$this->fileOriginalName}.{$this->fileExtension}";
            return response()->download($fileFullPath, $originalFileName, $this->headers);
        }

        $res = $this->base64ImageContent(file_get_contents($this->basePath . $this->filePath), $this->fileExtension);
        file_put_contents($fileFullPath, $res);
        return $res;
    }

    private function getFileFromLocalStorage()
    {
        $fileBasePath = "{$this->basePath}{$this->filePath}";
        $fileFullPath = "{$this->mediaTempPath}/{$this->fileNameHash}";

        if (!in_array($this->fileExtension, ['png', 'jpg', 'jpeg'])) {
            $fileNameWithExtension = "{$this->fileFilename}.{$this->fileExtension}";
            return response()->download($fileBasePath, $fileNameWithExtension, $this->headers);
        }

        $res = Image::make($fileBasePath);

        if ($this->width && $this->height) {
            $res = $res->fit((int) $this->width, (int) $this->height);
        } else {
            $this->fileExtension = $this->quality < 100 ? 'jpg' : $this->fileExtension;
        }

        $res = $res->save($fileFullPath)
            ->response($this->fileExtension, (int) $this->quality);

        return $this->inlineContent ? $this->base64ImageContent($res->getContent(), 'jpg') : $res;
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

    private function makeCopyOfNotFoundImageOrMake404()
    {
        if (!file_exists($this->notFoundImagePath)) {
            $res = $this->width || $this->height ? $this->make404image($this->width, $this->height) : $this->make404image();
        } else {
            $res = Image::make($this->notFoundImagePath);
            if ($this->width || $this->height) {
                $res->fit((int) $this->width, (int) $this->height);
            }
            $res = $res->response($this->extractFileExtension($this->notFoundImagePath), $this->quality);
        }
        return $this->inlineContent ? $this->base64ImageContent($res->getContent(), 'jpg') : $res;
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
