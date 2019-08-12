<?php
namespace minarae/Image;

/**
 * Class Image
 */
class Image
{
    /**
     * @var resource
     */
    private $image;
    /**
     * @var int
     */
    private $width;
    /**
     * @var int
     */
    private $height;

    /**
     * Image constructor.
     *
     * @param        $content
     * @param string $type
     */
    public function __construct($content, $type = 'file')
    {
        $this->image = $this->openImage($content, $type);

        // 비어있는채로 반환하는 경우를 대비하여서
        $this->imageResized = $this->image;
        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);
    }

    /**
     * @param $file
     * @param $type
     *
     * @return bool|false|resource
     */
    private function openImage($file, $type)
    {
        if ($type === 'file') {
            $type = exif_imagetype($file);

            switch ($type) {
                case IMAGETYPE_JPEG:
                    $img = imagecreatefromjpeg($file);
                    break;
                case IMAGETYPE_PNG:
                    $img = imagecreatefrompng($file);
                    break;
                default:
                    $img = false;
                    break;
            }
        } else if ($type === 'string') {
            $img = imagecreatefromstring($file);
        } else {
            $img = false;
        }

        return $img;
    }

    /**
     * @var resource
     */
    private $imageResized;

    public function resizeImage($newWidth, $newHeight, $option = 'auto')
    {
        // *** Get optimal width and height - based on $option
        $optionArray = $this->getDimensions($newWidth, $newHeight, strtolower($option));

        $optimalWidth = $optionArray['optimalWidth'];
        $optimalHeight = $optionArray['optimalHeight'];

        // *** Resample - create image canvas of x, y size
        $this->imageResized = imagecreatetruecolor($optimalWidth, $optimalHeight);
        imagecopyresampled($this->imageResized, $this->image, 0, 0, 0, 0, $optimalWidth, $optimalHeight, $this->width, $this->height);

        // *** if option is 'crop', then crop too
        if ($option === 'crop') {
            $this->crop($optimalWidth, $optimalHeight, $newWidth, $newHeight);
        }
    }

    private function getDimensions($newWidth, $newHeight, $option)
    {
        if ($newWidth > $this->width && $newHeight > $this->height) {
            // 리사이즈할 사이즈가 원본보다 클 경우는 원본을 유지
            $optimalWidth = $this->width;
            $optimalHeight = $this->height;
        } else {
            switch ($option) {
                case 'exact':
                    $optimalWidth = $newWidth;
                    $optimalHeight = $newHeight;
                    break;
                case 'portrait':
                    $optimalWidth = $this->getSizeByFixedHeight($newHeight);
                    $optimalHeight = $newHeight;
                    break;
                case 'landscape':
                    $optimalWidth = $newWidth;
                    $optimalHeight = $this->getSizeByFixedWidth($newWidth);
                    break;
                case 'auto':
                    $optionArray = $this->getSizeByAuto($newWidth, $newHeight);
                    $optimalWidth = $optionArray['optimalWidth'];
                    $optimalHeight = $optionArray['optimalHeight'];
                    break;
                case 'crop':
                    $optionArray = $this->getOptimalCrop($newWidth, $newHeight);
                    $optimalWidth = $optionArray['optimalWidth'];
                    $optimalHeight = $optionArray['optimalHeight'];
                    break;
                default:
                    $optimalWidth = $newWidth;
                    $optimalHeight = $newHeight;
            }
        }

        return [
            'optimalWidth'  => $optimalWidth,
            'optimalHeight' => $optimalHeight
        ];
    }

    private function getSizeByFixedHeight($newHeight)
    {
        $ratio = $this->width / $this->height;
        $newWidth = $newHeight * $ratio;

        return $newWidth;
    }

    private function getSizeByFixedWidth($newWidth)
    {
        $ratio = $this->height / $this->width;
        $newHeight = $newWidth * $ratio;

        return $newHeight;
    }

    private function getSizeByAuto($newWidth, $newHeight)
    {
        if ($this->height < $this->width) {
            // *** Image to be resized is wider (landscape)
            $optimalWidth = $newWidth;
            $optimalHeight = $this->getSizeByFixedWidth($newWidth);
        } else if ($this->height > $this->width) {
            // *** Image to be resized is taller (portrait)
            $optimalWidth = $this->getSizeByFixedHeight($newHeight);
            $optimalHeight = $newHeight;
        } else {
            // *** Image to be resizerd is a square
            if ($newHeight < $newWidth) {
                $optimalWidth = $newWidth;
                $optimalHeight = $this->getSizeByFixedWidth($newWidth);
            } else if ($newHeight > $newWidth) {
                $optimalWidth = $this->getSizeByFixedHeight($newHeight);
                $optimalHeight = $newHeight;
            } else {
                // *** Sqaure being resized to a square
                $optimalWidth = $newWidth;
                $optimalHeight = $newHeight;
            }
        }

        return [
            'optimalWidth'  => $optimalWidth,
            'optimalHeight' => $optimalHeight
        ];
    }

    private function getOptimalCrop($newWidth, $newHeight)
    {

        $heightRatio = $this->height / $newHeight;
        $widthRatio = $this->width / $newWidth;

        if ($heightRatio < $widthRatio) {
            $optimalRatio = $heightRatio;
        } else {
            $optimalRatio = $widthRatio;
        }

        $optimalHeight = $this->height / $optimalRatio;
        $optimalWidth = $this->width / $optimalRatio;

        return [
            'optimalWidth'  => $optimalWidth,
            'optimalHeight' => $optimalHeight
        ];
    }

    private function crop($optimalWidth, $optimalHeight, $newWidth, $newHeight)
    {
        // *** Find center - this will be used for the crop
        $cropStartX = ($optimalWidth / 2) - ($newWidth / 2);
        $cropStartY = ($optimalHeight / 2) - ($newHeight / 2);

        $crop = $this->imageResized;
        //imagedestroy($this->imageResized);

        // *** Now crop from center to exact requested size
        $this->imageResized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($this->imageResized, $crop, 0, 0, $cropStartX, $cropStartY, $newWidth, $newHeight, $newWidth, $newHeight);
    }

    public function saveImage($savePath, $imageQuality = "100")
    {
        // *** Get extension
        $extension = strrchr($savePath, '.');
        $extension = strtolower($extension);

        switch ($extension) {
            case '.jpg':
            case '.jpeg':
                if (imagetypes() & IMG_JPG) {
                    imagejpeg($this->imageResized, $savePath, $imageQuality);
                }
                break;
            case '.gif':
                if (imagetypes() & IMG_GIF) {
                    imagegif($this->imageResized, $savePath);
                }
                break;
            case '.png':
                // *** Scale quality from 0-100 to 0-9
                $scaleQuality = round(($imageQuality / 100) * 9);

                // *** Invert quality setting as 0 is best, not 9
                $invertScaleQuality = 9 - $scaleQuality;

                if (imagetypes() & IMG_PNG) {
                    imagepng($this->imageResized, $savePath, $invertScaleQuality);
                }
                break;
            default:
                // *** No extension - No save.
                break;
        }

        imagedestroy($this->imageResized);
    }

    public function getStream($type = 'jpg')
    {
        ob_start();
        if ($type === 'png') {
            imagepng($this->imageResized);
        } else {
            imagejpeg($this->imageResized);
        }
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }
}
