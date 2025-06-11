<?php

namespace Kodhe\Pulen\Framework\Helpers;

class CaptchaHelper
{
    public static function createCaptcha($data = '', $imgPath = '', $imgUrl = '', $fontPath = '')
    {
        $defaults = [
            'word'       => '',
            'img_path'   => '',
            'img_url'    => '',
            'img_width'  => '150',
            'img_height' => '30',
            'font_path'  => '',
            'expiration' => 7200,
            'word_length'=> 8,
            'font_size'  => 16,
            'img_id'     => '',
            'pool'       => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'colors'     => [
                'background' => [255, 255, 255],
                'border'     => [153, 102, 102],
                'text'       => [204, 153, 153],
                'grid'       => [255, 182, 182],
            ],
        ];

        foreach ($defaults as $key => $val) {
            if (!is_array($data) && empty($$key)) {
                $$key = $val;
            } else {
                $$key = isset($data[$key]) ? $data[$key] : $val;
            }
        }

        if ($imgPath === '' || $imgUrl === '' || !is_dir($imgPath) || !is_writable($imgPath) || !extension_loaded('gd')) {
            return false;
        }

        $now = microtime(true);

        // Remove old images
        $currentDir = @opendir($imgPath);
        while ($filename = @readdir($currentDir)) {
            if (in_array(substr($filename, -4), ['.jpg', '.png'])
                && (str_replace(['.jpg', '.png'], '', $filename) + $expiration) < $now) {
                @unlink($imgPath . $filename);
            }
        }
        @closedir($currentDir);

        if (empty($word)) {
            $word = self::generateWord($wordLength, $pool);
        }

        $im = function_exists('imagecreatetruecolor')
            ? imagecreatetruecolor($imgWidth, $imgHeight)
            : imagecreate($imgWidth, $imgHeight);

        is_array($colors) || $colors = $defaults['colors'];

        foreach (array_keys($defaults['colors']) as $key) {
            is_array($colors[$key]) || $colors[$key] = $defaults['colors'][$key];
            $colors[$key] = imagecolorallocate($im, $colors[$key][0], $colors[$key][1], $colors[$key][2]);
        }

        imagefilledrectangle($im, 0, 0, $imgWidth, $imgHeight, $colors['background']);

        // Draw the text and decorations
        self::drawCaptcha($im, $word, $imgWidth, $imgHeight, $fontPath, $fontSize, $colors);

        imagerectangle($im, 0, 0, $imgWidth - 1, $imgHeight - 1, $colors['border']);

        $imgUrl = rtrim($imgUrl, '/') . '/';
        $imgFilename = $now . (function_exists('imagejpeg') ? '.jpg' : '.png');

        if (function_exists('imagejpeg')) {
            imagejpeg($im, $imgPath . $imgFilename);
        } elseif (function_exists('imagepng')) {
            imagepng($im, $imgPath . $imgFilename);
        } else {
            return false;
        }

        imagedestroy($im);

        return [
            'word'     => $word,
            'time'     => $now,
            'image'    => '<img ' . ($imgId === '' ? '' : 'id="' . $imgId . '"') . ' src="' . $imgUrl . $imgFilename . '" style="width: ' . $imgWidth . 'px; height: ' . $imgHeight . 'px; border: 0;" alt="" />',
            'filename' => $imgFilename,
        ];
    }

    private static function generateWord($length, $pool)
    {
        $word = '';
        $poolLength = strlen($pool);
        $randMax = $poolLength - 1;

        if (function_exists('random_int')) {
            try {
                for ($i = 0; $i < $length; $i++) {
                    $word .= $pool[random_int(0, $randMax)];
                }
            } catch (Exception $e) {
                // Fallback
            }
        }

        if ($word === '') {
            for ($i = 0; $i < $length; $i++) {
                $word .= $pool[mt_rand(0, $randMax)];
            }
        }

        return $word;
    }

    private static function drawCaptcha($im, $word, $width, $height, $fontPath, $fontSize, $colors)
    {
        $length = strlen($word);
        $angle = ($length >= 6) ? mt_rand(-($length - 6), ($length - 6)) : 0;
        $x = mt_rand(0, $width / ($length / 3));
        $y = $fontSize + 2;

        for ($i = 0; $i < $length; $i++) {
            if ($fontPath && file_exists($fontPath) && function_exists('imagettftext')) {
                imagettftext($im, $fontSize, $angle, $x, $y, $colors['text'], $fontPath, $word[$i]);
            } else {
                imagestring($im, $fontSize, $x, mt_rand(0, $height / 2), $word[$i], $colors['text']);
            }
            $x += ($fontSize * 2);
        }
    }
}
