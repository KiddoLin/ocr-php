<?php

namespace my_tools;

class OcrPhone
{
    protected $imgPath;//图片路径
    protected $imgSize;//图片大小
    protected $hecData;//分离后数组
    protected $horData;//横向整理的数据
    protected $verData;//纵向整理的数据

    function __construct($path)
    {
        $this->imgPath = $path;
    }

    /**
     * 颜色分离转换...
     */
    public function getHec()
    {
        $size = getimagesize($this->imgPath);
        $res = imagecreatefrompng($this->imgPath);
        $data = [];
        for ($i = 0; $i < $size[1]; ++$i) {
            for ($j = 0; $j < $size[0]; ++$j) {
                $rgb = imagecolorat($res, $j, $i);
                $rgbarray = imagecolorsforindex($res, $rgb);
                if ($rgbarray['red'] < 125 || $rgbarray['green'] < 125
                    || $rgbarray['blue'] < 125) {
                    $data[$i][$j] = 1;
                } else {
                    $data[$i][$j] = 0;
                }
            }
        }
        $this->imgSize = $size;
        $this->hecData = $data;
    }

    /**
     * 颜色分离后的数据横向整理...
     */
    public function magHorData()
    {

        $data = $this->hecData;
        $size = $this->imgSize;
        $z = 0;
        $newdata = [];
        for ($i = 0; $i < $size[1]; ++$i) {
            if (in_array('1', $data[$i])) {
                $z++;
                for ($j = 0; $j < $size[0]; ++$j) {
                    if ($data[$i][$j] == 1) {
                        $newdata[$z][$j] = 1;
                    } else {
                        $newdata[$z][$j] = 0;
                    }
                }
            }
        }
        return $this->horData = $newdata;
    }

    /**
     * 整理纵向数据...
     * @param array $newdata
     */
    public function magVerData(array $newdata)
    {
        $ndata = [];
        $iLeng = count($newdata);
        $jLeng = count($newdata[1]);
        for ($i = 1; $i < $iLeng; ++$i) {
            for ($j = 1; $j < $jLeng; ++$j) {
                $ndata[$j][$i] = $newdata[$i][$j];
            }
        }
        $sum = count($ndata);
        $c = 0;
        $ndatas = [];
        for ($a = 1; $a < $sum; $a++) {
            $value = $ndata[$a];
            if (in_array(1, $value)) {
                $ndatas[$c] = $value;
                $c++;
            } elseif (is_array($ndatas)) {
                $b = $c - 1;
                if (in_array(1, $ndatas[$b])) {
                    $ndatas[$c] = $value;
                    $c++;
                }
            }
        }
        return $this->verData = $ndatas;
    }

    /**
     * 显示电话号码...
     * @param array $ndatas
     * @return string
     */
    public function showPhone(array $ndatas)
    {
        $phone = '';
        $d = 0;
        $ndArr = [];
        foreach ($ndatas as $key => $val) {
//            $ndArr[$d] = implode('',$val);
            $ndArr[$d] = '';
            if (in_array(1, $val)) {
                foreach ($val as $k => $v) {
                    $ndArr[$d] .= $v;
                }
            }
            if (!in_array(1, $val)) {
                $d++;
            }
        }
//        print_r($ndArr);exit();
        foreach ($ndArr as $key01 => $val01) {
            echo $val01.'<br>';
            $phone .= $this->initData($val01);
        }
        return $phone;
    }

    /**
     * 分离显示...
     *
     * @param array $dataArr
     */
    function drawWH(array $dataArr)
    {
        $c = '';
        if (is_array($dataArr)) {
            foreach ($dataArr as $key => $val) {
                foreach ($val as $k => $v) {
                    if ($v == 0) {
                        $c .= "<font color='#FFFFFF'>" . $v . "</font>";
                    } else {
                        $c .= $v;
                    }
                }
                $c .= "<br/>";
            }
        }
        echo $c;
    }

    /**
     * 初始数据...
     *
     * @param string $numStr
     * @return string
     */
    public function initData($numStr)
    {
        $result = null;
        $data = [
            0 => '000011111000001111111110011000000011110000000001110000000001110000000001110000000001011000000011011100000111000111111100000001110000',
            1 => '011000000000011000000000111111111111111111111111',
            2 => '001000000011011000000111110000001101110000011001110000011001110000110001111001100001011111100001000110000001',
            3 => '001000000010011000000011110000000001110000000001110000110001110000110001011001110011011111011111000110001100',
            4 => '000000001100000000111100000001111100000011101100000111001100001100001100011000001100111111111111111111111111000000001100000000000100',
            5 => '111111000001111111000001110001000001110001000001110001100001110001100001110000110011110000111111000000001100',
            6 => '000011111000001111111110011000110011110001100001110001100001110001100001110001100001010001110011010000111111000000001100',
            7 => '110000000000110000000111110000111111110001110000110111000000111100000000111000000000111000000000',
            8 => '000100011110011111111111110011100001110001100001110001100001110001100001110011100001011111111111000100011110',
            9 => '001111000000011111100001110000110001110000110001110000110001110000110001011000100001011111100111000111111110000001110000',
        ];
        foreach ($data as $key => $val) {
            similar_text($numStr, $val, $pre);
            if ($pre > 95) {//相似度95%以上
                $result = $key;
                break;
            }
        }
        return $result;
    }
}