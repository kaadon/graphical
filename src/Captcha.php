<?php

namespace Kaadon;

use Kaadon\graphical\Exception as ImageException;
use think\facade\Cache;

class Captcha
{
    protected function generate(bool $identical = false, int $start = 1, int $end = 277, $files = []): array
    {
        $number = rand($start, $end);
        if ($identical) {
            do $number1 = rand($start, $end); while ($number1 == $number);
        } else {
            $number1 = $number;
        }
        return ["start" => $number, "end" => $number1];
    }

    protected function getGenerateImage()
    {
        $files = [];
        for ($i = 1; $i <= 12; $i++) {
            $identical = false;
            if ($i % 6 !== 0) $identical = true;
            $files[(ceil($i / 6))] ?? $files[(ceil($i / 6))] = [];
            $files[(ceil($i / 6))][] = $this->generate($identical);
        }
        foreach ($files as $key => &$file) {
            shuffle($file);
        }
        return $files;
    }

    protected function createImage(int $start, int $end): string
    {
        $positions = [
            [1, 9],
            [3, 7]
        ];
        $position  = $positions[array_rand($positions)];
        $Graphical = Graphical::open(__DIR__ . "/assets/png.png");
        $pathname  = tempnam(sys_get_temp_dir(), 'ya');
        $Graphical->water(__DIR__ . "/assets/images/{$start}.png", $position[1])
            ->water(__DIR__ . "/assets/images/{$end}.png", $position[0])
            ->thumb(150, 150)
            ->save($pathname);
        $image = Graphical::open(__DIR__ . "/assets/png.png")
            ->water($pathname, 5)
            ->thumb(72, 72)
            ->toBase64Img();
        @unlink($pathname);
        return $image;
    }

    public function create()
    {
        /** 创建验证码 **/
        $images     = $this->getGenerateImage();
        $imageArray = [];
        $identical  = [];
        foreach ($images as $key => $image) {
            $imageArray[$key] = [];
            foreach ($image as $item) {
                $dataImage                  = $this->createImage($item['start'], $item['end']);
                $md5Hash                    = md5(time() . $item['start'] . $item['end']);
                $imageArray[$key][$md5Hash] = $dataImage;
                if ($item['start'] == $item['end']) {
                    $identical[] = $md5Hash;
                }
            }
        }
        $key = md5(uniqid(mt_rand(), true));
        Cache::set($key, $identical, 180);
        $verify['verify_id']  = $key;
        $verify['verify_src'] = $imageArray;
        return $verify;
    }

    public function check(string $verify_id, array $md5Hash): bool
    {
        $hashArray = Cache::get($verify_id);
        if (empty($hashArray)) {
            Cache::delete($verify_id);
            throw new ImageException("The verification code has expired");
        }
        if (count($md5Hash) !== count($hashArray)) {
            Cache::delete($verify_id);
            throw new ImageException("Data does not exist");
        }
        foreach ($md5Hash as $item) {
            if (!in_array($item, $hashArray)) {
                Cache::delete($verify_id);
                throw new ImageException("Verification code error");
            }
        }
        Cache::delete($verify_id);
        return true;
    }
}