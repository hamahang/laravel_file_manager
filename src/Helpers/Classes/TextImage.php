<?php

namespace Hamahang\LFM\Helpers\Classes;

use Intervention\Image\ImageManagerStatic as Image;

class TextImage
{
    private $imgWidth = '640';
    private $imgHeight = '480';
    private $imageType = 'png';
    private $text = '404';
    private $backgroundColor = 'CC0099';
    private $textColor = 'FFFFFF';
    private $fontFile = 'FFFFFF';
    private $angle = 0;

    public function __construct($imgWidth = '640', $imgHeight = '480', $imageType = 'png', $text = '404', $backgroundColor = 'CC0099', $textColor = 'FFFFFF', $angle = 0)
    {
        $this->angle            = (int)$angle;
        $this->imgWidth         = $this->validateDimension($imgWidth, '640');
        $this->imgHeight        = $this->validateDimension($imgHeight, '480');
        $this->imageType        = $this->imageTypeValidation($imageType);
        $this->text             = $this->fixTextEncoding($text);
        $this->backgroundColor  = $backgroundColor;
        $this->textColor        = $textColor;
        $this->fontFile         = $this->fontFile();
    }

    public function make()
    {
        $fontSize = round(($this->imgWidth - 50) / 8);
        $fontSize = $fontSize <= 9 ? 9 : $fontSize;

        $image = Image::canvas($this->imgWidth, $this->imgHeight, $this->backgroundColor);

        list($xpoint, $ypoint) = self::textCenterCordinates($image->width(), $image->height(), $fontSize);

        $image->text($this->text, $xpoint, $ypoint, function ($font) use ($fontSize) {
            $font->file($this->fontFile);
            $font->size($fontSize);
            $font->color($this->textColor);
        });

        return $image->response($this->imageType);
    }

    private function validateDimension($cordinate, $default)
    {
        $filterOptions = ['options' => ['min_range' => 0, 'max_range' => 9999]];
        return filter_var($cordinate, FILTER_VALIDATE_INT, $filterOptions) ? $cordinate : $default;
    }

    private function imageTypeValidation($type)
    {
        $type = strtolower($type);
        return in_array($type, ['png', 'gif', 'jpg']) ? $type : 'jpg';
    }

    private function fixTextEncoding($text)
    {
        return mb_encode_numericentity(
            mb_convert_encoding(
                $text,
                'UTF-8',
                mb_detect_encoding($text, 'UTF-8, ISO-8859-1')
            ),
            [0x0, 0xffff, 0, 0xffff],
            'UTF-8'
        );
    }

    private function textCenterCordinates($imageWidth, $imageHeigth, $fontSize)
    {
        list($left, $bottom, $right, , , $top) = imagettfbbox($fontSize, $this->angle, $this->fontFile, $this->text);
        $left_offset = ($right - $left) / 2;
        $top_offset = ($bottom - $top) / 2;
        return [
            ($imageWidth / 2) - $left_offset,
            ($imageHeigth / 2) + $top_offset
        ];
    }

    private function fontFile()
    {
        $fontFile = realpath(__DIR__) . '/../../assets/fonts/IranSans/ttf/IRANSansWeb.ttf';
        return !is_readable($fontFile) ? 'arial' : $fontFile;
    }
}
