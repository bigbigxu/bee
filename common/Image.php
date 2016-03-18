<?php
/*****************************************************
 * 用于图片缩略图的生成和水印增
 * 核心方法
 * 构造方法,设定图片路径
 * thumb()，制作缩略图
 * waterMark(),添加印
 ****************************************************/
class Image
{
    const THUMB_WHITE = 1; //生成图片的时候补白
    const THUMB_RESIZE = 0; //生成等比例

    /**
     * 生成缩略图
     * @param string $srcFile 要处理的图片路径
     * @param string $dstFile 处理后保存的路径
     * @param int $width 缩放后的宽度
     * @param int $height 缩放后的高度
     * @param int $type 生成缩略图类型，默认为1，表示补白，设为0，表示等比例绽放
     * @return string|bool 新图片名称，失败返回false
     */
    public function thumb($srcFile, $dstFile, $width = 100, $height = 100, $type = self::THUMB_WHITE)
    {
        $imageInfo = $this->getInfo($srcFile); //得到图片信息
        $srcImage = $this->getImage($srcFile, $imageInfo); //获取图片资源,各种类型的图片都可以创建资源。
        $size = $this->getNewSize($width, $height, $imageInfo); //计算缩放图片等比例大小
        if ($type == self::THUMB_WHITE) { //生成补白的的新图片，得到新图片绘制的起始位置
            $newXY = $this->getNewImageXY($size, $width, $height);
            $dstImage = $this->newImageWhite($srcImage, $imageInfo, $size, $newXY, $width, $height);
        } else {
            $dstImage = $this->newImage($srcImage, $size, $imageInfo);
        }
        //另存为一个新的图片，返回新的图片名称
        return $this->saveImage($dstImage, $dstFile, $imageInfo);
    }

    /**
     * 生成补白的缩略图
     * @param resource $srcImage 源图片资源
     * @param array $imageInfo 源图片信息
     * @param array $size 新图片尺寸
     * @param array $newXY 缩略图起始位置
     * @param int $width 缩略度宽
     * @param int $height 缩略图高
     * @return resource 生图片资源
     */
    private function newImageWhite($srcImage, $imageInfo, $size, $newXY, $width, $height)
    {
        $dstImage = imagecreatetruecolor($width, $height);
        $backColor = imagecolorallocate($dstImage, 255, 255, 255);
        imagefill($dstImage, 0, 0, $backColor);
        imagecopyresampled(
            $dstImage,
            $srcImage,
            $newXY['x'],
            $newXY['y'],
            0,
            0,
            $size['width'],
            $size['height'],
            $imageInfo['width'],
            $imageInfo['height']
        );
        imagedestroy($srcImage);
        return $dstImage;
    }

    /**
     * 得到新图片绘制的起始位置
     * @param array $size 新
     * @param int $width 缩略图宽度
     * @param int $height 缩略图高度
     * @return array 起始位置X,Y坐
     */
    private function getNewImageXY($size, $width, $height)
    {
        $xy['x'] = round(($width - $size['width']) / 2);
        $xy['y'] = round(($height - $size['height']) / 2);
        return $xy;
    }

    /**
     * 保存新的图片
     * @param resource $dstImage 新图片资源
     * @param string $dstFile 新图片文件名
     * @param array $imageInfo 源图片信息
     * @return bool
     */
    private function saveImage($dstImage, $dstFile, $imageInfo)
    {
        $result = false;
        switch ($imageInfo['type']) {
            case 1: //gif
                $result = imagegif($dstImage, $dstFile);
                break;
            case 2: //jpg
                $result = imagejpeg($dstImage, $dstFile);
                break;
            case 3: //png
                $result = imagepng($dstImage, $dstFile);
                break;
        }
        imagedestroy($dstImage);
        return $result;
    }

    /**
     * 生成新的缩略图,按等比例缩放。
     * @param resource $srcImage 源图片资源
     * @param array $size 源图片尺寸
     * @param array $imageInfo 新图片尺寸
     * @return resource 新图的资源
     */
    private function newImage($srcImage, $size, $imageInfo)
    {
        $newImage = imagecreatetruecolor($size['width'], $size['height']);
        imagecopyresampled(
            $newImage,
            $srcImage,
            0,
            0,
            0,
            0,
            $size['width'],
            $size['height'],
            $imageInfo['width'],
            $imageInfo['height']
        );
        imagedestroy($srcImage);
        return $newImage;
    }

    /**
     * 计算缩略图尺寸
     * @param int $width 缩略图宽
     * @param int $height 缩略图高
     * @param array $imageInfo 原图信息
     * @reutrn array 返回新图片尺寸。
     */
    private function getNewSize($width, $height, $imageInfo)
    {
        $size['width'] = $imageInfo['width'];
        $size['height'] = $imageInfo['height'];
        if ($width < $imageInfo['width']) {
            $size['width'] = $width;
        }
        if ($height < $imageInfo['height']) {
            $size['height'] = $height;
        }
        if ($imageInfo['width'] / $imageInfo['height'] > $size['width'] / $size['height']) {
            $size['height'] = round($size['width'] * ($imageInfo['height'] / $imageInfo['width']));
        } else {
            $size['width'] = round($imageInfo['width'] * $size['height'] / $imageInfo['height']);
        }
        return $size;
    }

    /**
     * 得到图片资源
     * @param string $srcFile 图片名称
     * @param array $imageInfo 当前图片信息
     * @return bool|resource 返回图片资源 ，失败返回false
     */
    private function getImage($srcFile, $imageInfo)
    {
        switch ($imageInfo['type']) {
            case 1://gif
                $img = imagecreatefromgif($srcFile);
                break;
            case 2://jpg
                $img = imagecreatefromjpeg($srcFile);
                break;
            case 3://png
                $img = imagecreatefrompng($srcFile);
                break;
            default:
                return false;
        }
        return $img;
    }

    /**
     * 得到图片信息
     * @param string $file 图片名称
     * @return array 包含当前图片的宽，高，类型
     */
    private function getInfo($file)
    {
        $data = getimagesize($file);
        $imageInfo['width'] = $data[0];
        $imageInfo['height'] = $data[1];
        $imageInfo['type'] = $data[2];
        return $imageInfo;
    }

    /**
     * 为图片添加水印
     * @param string $srcFile 要加水印的图片路径
     * @param string $waterFile 水印的图片路径
     * @param string $dstFile 保存的图片路径
     * @param mixed $waterPos
     * @param int $pct 水印透明度.
     * @return string 处理后图片的名称，失败返回false
     */
    public function waterMark($srcFile, $waterFile, $dstFile, $waterPos = 1, $pct = 60)
    {
        if (file_exists($srcFile) && file_exists($waterFile)) {
            $srcInfo = $this->getInfo($srcFile);
            $waterInfo = $this->getInfo($waterFile);
            //得到水印的位置
            if ($pos = $this->_position($srcInfo, $waterInfo, $waterPos)) {
                //得到图片资源
                $scrImage = $this->getImage($srcFile, $srcInfo);
                $waterImage = $this->getImage($waterFile, $waterInfo);
                $newImage = $this->_copyImage($scrImage, $waterImage, $pos, $waterInfo);
                return $this->saveImage($newImage, $dstFile, $srcInfo);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 生成小印图片
     * @param resource $scrImage 图片资源
     * @param resource $waterImage 水印图片资源
     * @param array $pos 起始位置
     * @param array $waterInfo 水印图片信息
     * @return resource 处理后的图片资源
     */
    private function _copyImage($scrImage, $waterImage, $pos, $waterInfo)
    {
        imagecopymerge(
            $scrImage,
            $waterImage,
            $pos[0],
            $pos[1],
            0,
            0,
            $waterInfo['width'],
            $waterInfo['height'],
            80
        );
        imagedestroy($waterImage);
        return $scrImage;
    }

    /**
     * 计算水印在图片中的位置
     * @param array $srcInfo 图片信息
     * @param array $waterInfo 水印信息
     * @param int $waterPos 图片位置
     * @return array 水印在图片中起始位置
     */
    private function _position($srcInfo, $waterInfo, $waterPos)
    {
        if ($srcInfo['width'] < $waterInfo['width'] || $srcInfo['height'] < $waterInfo['height'])
            return false;
        switch ($waterPos) {
            case 0://随机位置
                $posX = mt_rand(0, $srcInfo['width']);
                $posY = mt_rand(0, $srcInfo['height']);
                break;
            case 1://顶端居左
                $posX = 0;
                $posY = 0;
                break;
            case 2://顶端居中
                $posX = round(($srcInfo['width'] - $waterInfo['width']) / 2);
                $posY = 0;
                break;
            case 3://顶端居右
                $posX = round($srcInfo['width'] - $waterInfo['width']);
                $posY = 0;
                break;
            case 4://中部居左
                $posX = 0;
                $posY = round(($srcInfo['height'] - $waterInfo['height']) / 2);
                break;
            case 5://中部居中
                $posX = round(($srcInfo['width'] - $waterInfo['width']) / 2);
                $posY = round(($srcInfo['height'] - $waterInfo['height']) / 2);
                break;
            case 6://中部居右
                $posX = round($srcInfo['width'] - $waterInfo['width']);
                $posY = round(($srcInfo['height'] - $waterInfo['height']) / 2);
                break;
            case 7://底部居左
                $posX = 0;
                $posY = round($srcInfo['height'] - $waterInfo['height']);
                break;
            case 8://底部居中
                $posX = round((($srcInfo['width'] - $waterInfo['width'])) / 2);
                $posY = round($srcInfo['height'] - $waterInfo['height']);
                break;
            case 9://底部居右
                $posX = round($srcInfo['width'] - $waterInfo['width']);
                $posY = round($srcInfo['height'] - $waterInfo['height']);
                break;
            default:
                $posX = $waterPos[0];
                $posY = $waterPos[1];
        }
        return array($posX, $posY);
    }
}
