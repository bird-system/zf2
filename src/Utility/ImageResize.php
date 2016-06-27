<?php
/*
 * ---------------------------------------------------------------------
 * Credits: Bit Repository Source URL:
 * http://www.bitrepository.com/resize-an-image-keeping-its-aspect-ratio-using-php-and-gd.html
 * ---------------------------------------------------------------------
 */

namespace BS\Utility;

use Zend\ServiceManager\ServiceLocatorAwareTrait;

class ImageResize
{
    use ServiceLocatorAwareTrait;

    var $imageToResize;

    var $newWidth;

    var $newHeight;

    var $ratio = true;

    var $newImageName;

    var $saveFolder = '/tmp/';

    function resize()
    {
        if (!file_exists($this->imageToResize)) {
            exit("File {$this->imageToResize} does not exist.");
        }

        $info = getimagesize($this->imageToResize);

        if (empty($info)) {
            exit("The file {$this->imageToResize} doesn't seem to be an image.");
        }

        $width  = $info[0];
        $height = $info[1];
        $mime   = $info['mime'];

        /*
         * Keep Aspect Ratio? Improved, thanks to Larry
         */
        if ($this->ratio) {
            // if preserving the ratio, only new width or new height
            // is used in the computation. if both
            // are set, use width

            if (isset($this->newWidth)) {
                $factor          = (float)$this->newWidth / (float)$width;
                $this->newHeight = $factor * $height;
            } else {
                if (isset($this->newHeight)) {
                    $factor         = (float)$this->newHeight / (float)$height;
                    $this->newWidth = $factor * $width;
                } else {
                    exit('Neither new height or new width has been set');
                }
            }
        }

        // What sort of image?

        $type = substr(strrchr($mime, '/'), 1);

        switch ($type) {
            case 'jpeg':
                $imageCreateFunc = 'ImageCreateFromJPEG';
                $imageSaveFunc   = 'ImageJPEG';
                $newImageExt     = 'jpg';
                break;

            case 'png':
                $imageCreateFunc = 'ImageCreateFromPNG';
                $imageSaveFunc   = 'ImagePNG';
                $newImageExt     = 'png';
                break;

            case 'bmp':
                $imageCreateFunc = 'ImageCreateFromBMP';
                $imageSaveFunc   = 'ImageBMP';
                $newImageExt     = 'bmp';
                break;

            case 'gif':
                $imageCreateFunc = 'ImageCreateFromGIF';
                $imageSaveFunc   = 'ImageGIF';
                $newImageExt     = 'gif';
                break;

            case 'vnd.wap.wbmp':
                $imageCreateFunc = 'ImageCreateFromWBMP';
                $imageSaveFunc   = 'ImageWBMP';
                $newImageExt     = 'bmp';
                break;

            case 'xbm':
                $imageCreateFunc = 'ImageCreateFromXBM';
                $imageSaveFunc   = 'ImageXBM';
                $newImageExt     = 'xbm';
                break;

            default:
                $imageCreateFunc = 'ImageCreateFromJPEG';
                $imageSaveFunc   = 'ImageJPEG';
                $newImageExt     = 'jpg';
        }

        // New Image
        $imageC = imagecreatetruecolor($this->newWidth, $this->newHeight);

        $newImage = $imageCreateFunc($this->imageToResize);

        imagecopyresampled($imageC, $newImage, 0, 0, 0, 0, $this->newWidth, $this->newHeight, $width, $height);

        if ($this->saveFolder) {
            if ($this->newImageName) {
                $newName = $this->newImageName . '.' . $newImageExt;
            } else {
                $newName = $this->new_thumb_name(basename($this->imageToResize)) . '_resized.' . $newImageExt;
            }

            $savePath = $this->saveFolder . $newName;
        } else {
            /* Show the image without saving it to a folder */
            header('Content-Type: ' . $mime);

            $imageSaveFunc($imageC);

            $savePath = '';
        }

        $process = $imageSaveFunc($imageC, $savePath);

        return ['result' => $process, 'newFilePath' => $savePath];
    }

    function new_thumb_name($filename)
    {
        $string = trim($filename);
        $string = strtolower($string);
        $string = trim(preg_replace('/[^ A-Za-z0-9_]/', ' ', $string));
        $string = preg_replace('/[ tnr]+/', '_', $string);
        $string = str_replace(' ', '_', $string);
        $string = preg_replace('/[ _]+/', '_', $string);

        return $string;
    }
}