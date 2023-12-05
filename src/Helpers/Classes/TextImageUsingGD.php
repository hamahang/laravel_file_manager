<?php

namespace Hamahang\LFM\Helpers\Classes;

use Intervention\Image\ImageManagerStatic as Image;

class TextImageUsingGD
{
    private $imgWidth = '640';
    private $imgHeight = '480';
    private $imgType = 'png';
    private $text = '404';
    private $backgroundColor = 'CC0099';
    private $textColor = 'FFFFFF';
    private $fontFile = 'FFFFFF';
    private $angle = 0;

    public function __construct($imgWidth = '640', $imgHeight = '480', $imageType = 'png', $text = '404', $backgroundColor = 'CC0099', $textColor = 'FFFFFF', $angle = 0)
    {
        if ($imgHeight === '') {
            $imgHeight = $imgWidth;
        }

        $this->angle = (int) $angle;
        $this->imgWidth = $this->validateDimension($imgWidth, '640');
        $this->imgHeight = $this->validateDimension($imgHeight, '480');
        $this->imgType = $this->imageTypeValidation($imageType);
        $this->text = $this->fixTextEncoding($text);
        $this->backgroundColor = $backgroundColor;
        $this->textColor = $textColor;
        $this->fontFile = $this->fontFile();
    }

    public function make()
    {
        $fontSize = round(($this->imgWidth - 50) / 8);
        $fontSize = $fontSize <= 9 ? 9 : $fontSize;

        $imageResource = $this->makeCanvas();

        // draw cross lines in center of image to check text is center
        $this->drawCenterCrossLines($imageResource, '000000');

        list($xpoint, $ypoint) = $this->getCoordinatesOfTextInCenterOfImage($fontSize);

        $this->drawText($imageResource, $xpoint, $ypoint, $fontSize);

        // all action have been used GD library because of centerning problem in Intervention Library
        // in final return we use Intervention for compatibility
        return Image::make($imageResource)->response($this->imgType);
    }

    private function makeCanvas()
    {
        $imageResource = imagecreatetruecolor($this->imgWidth, $this->imgHeight);
        imagefilledrectangle($imageResource, 0, 0, $this->imgWidth, $this->imgHeight, $this->gdColor($imageResource, $this->backgroundColor));
        return $imageResource;
    }

    private function drawText(&$imageResource, $xpoint, $ypoint, $fontSize)
    {
        imagettftext($imageResource, $fontSize, 0, $xpoint, $ypoint, $this->gdColor($imageResource, $this->textColor), $this->fontFile, $this->text);
    }

    private function drawCenterCrossLines($imageResource, $hexColor='000000')
    {
        $this->hrLine($imageResource, $hexColor, $this->imgHeight / 2, $this->imgWidth);
        $this->vtLine($imageResource, $hexColor, $this->imgWidth / 2, $this->imgHeight);
    }

    private function validateDimension($coordinate, $default)
    {
        $filterOptions = ['options' => ['min_range' => 0, 'max_range' => 9999]];
        return filter_var($coordinate, FILTER_VALIDATE_INT, $filterOptions) ? $coordinate : $default;
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

    private function getCoordinatesOfTextInCenterOfImage($fontSize)
    {
        list($left, $bottom, $right, , , $top) = imagettfbbox($fontSize, $this->angle, $this->fontFile, $this->text);
        $leftOffset = ($right - $left) / 2;
        $topOffset = ($bottom - $top) / 2;
        return [
            ($this->imgWidth / 2) - $leftOffset,
            ($this->imgHeight / 2) + $topOffset
        ];
    }

    private function fontFile()
    {
        $fontFile = realpath(__DIR__) . '/../../assets/fonts/IranSans/ttf/IRANSansWeb.ttf';
        return !is_readable($fontFile) ? 'arial' : $fontFile;
    }

    public function hex2rgb($hexColor)
    {
        return sscanf(str_replace('#', '', $hexColor), "%02x%02x%02x");
    }

    public function gdColor(&$gdResource, $hexColor)
    {
        list($r, $g, $b) = $this->hex2rgb($hexColor);
        return imagecolorallocate($gdResource, $r, $g, $b);
    }

    public function hrLine(&$gdResource, $hexColor, $yLocation, $length)
    {
        return $this->line($gdResource, $hexColor, [0, $yLocation], [$length, $yLocation]);
    }

    public function vtLine(&$gdResource, $hexColor, $xLocation, $length)
    {
        return $this->line($gdResource, $hexColor, [$xLocation, 0], [$xLocation, $length]);
    }

    public function line(&$gdResource, $hexColor, $pointOne, $pointTwo)
    {
        return imageline($gdResource, $pointOne[0], $pointOne[1], $pointTwo[0], $pointTwo[1], $this->gdColor($gdResource, $hexColor));
    }
}
