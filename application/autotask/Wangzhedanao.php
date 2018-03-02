<?php
namespace app\autotask;

use baidu_aip_sdk\AipOcr;
use baidu_aip_sdk\lib\AipHttpClient;
use thiagoalessio\TesseractOCR\TesseractOCR;
use think\captcha\Captcha;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

class Wangzhedanao extends Command
{
    //基础配置
    private $pic_name = 'wzdn.png';//截屏图片文件名
    private $search_type = 2;//搜索类型：1-百度www，2-百度知道；
    private $screencap_way = 3;//截图存储方式：1-adb直接存到电脑，2-adb获取php存到电脑，3-adb存至手机后再导出到电脑；
    private $auto_click = true;//模拟点击，自动答题
    private $ratio = '720*1280';//屏幕分辨率：720*1280，
    //应用配置
    private $AppID = '10751709';
    private $APIKey = '8DSPoVVKA9cuOcAVZyriVR8l';
    private $SecretKey = 'InaQtMGpqQFMIlhALBxzq7FViIMaGjPt';
    //成员变量
    private $objAipOcr;
    private $arrWordsResult = [];
    private $question = '';
    private $arrOption = [];
    private $arrAnswer = [];
    private $strImage = '';
    private $arrRGB = ['A'=>[],'B'=>[],'C'=>[],'D'=>[]];
    private $arrRGB_white = ['r'=>255,'g'=>255,'b'=>255];
    private $arrRGB_green = ['r'=>163,'g'=>210,'b'=>56];
    private $arrRGB_red   = ['r'=>251,'g'=>108,'b'=>74];

    protected function configure()
    {
        //设置命令，及帮助文档中的备注
        $this->setName('Wangzhedanao')->setDescription('Here is the remark of autotask-Wangzhedanao');
    }

    /**
     * 可以这样执行命令 php think Wangzhedanao
     * @param Input $input
     * @param Output $output
     */
    protected function execute(Input $input, Output $output)
    {
//        $imgPath = "./4.png";
//        $obj = new TesseractOCR($imgPath);
//        $res = $obj->lang('eng','chi_sim')->run();
//        $output->writeln($res);
//        Log::write($res,'ocr-php',true);
//        die();

        $y = '';
        do{
            if($y=='n'){
                $output->writeln('请确认后再运行此程序，输入 y 回车后即可开始');
                exit();
            }
            $output->writeln('请确认电脑打开了ADB，手机打开了调试模式并连接上电脑，然后进比赛再运行此程序？ y/n [y]：');
            $y = trim(fgets(STDIN));
        }while($y!='y');
        $arrXY = $this->getScreenResolution('A');
        if(empty($arrXY)){
            $output->writeln('请选择已有的分辨率，诺无对应的分辨率请联系管理员');
            exit();
        }
        $output->writeln('！！！开始！！！');

        //ljq-
        $this->pic_name = 'php.png';
//        $this->question = '中国在位时间最长的皇帝是？';
//        $this->arrOption = ['A'=>'乾隆','B'=>'康熙','C'=>'顺治','D'=>'唐太宗'];

        while (true) {
            $starttime = explode(' ',microtime());

            //截屏
//            $this->screencap();
//            //判断是否答题阶段
//            if ($this->checkRGB()) {
//                $output->writeln('未在答题阶段，跳过。。。');
//                continue;
//            }
            //上传截图字节流，识别文字
            $this->requestAipOcr();
            //提取问题&选项
            if ($this->getQuestionAndOption()) {
                $this->searchAnswer();//搜索答案
                arsort($this->arrAnswer);//根据相似度降序排序
            }
            //输出答案
            print_r($this->arrAnswer);
            //模拟点击，自动答题
            if($this->auto_click && !empty($this->arrAnswer)){
                $this->autoClickOption();
            }

            $endtime = explode(' ',microtime());
            $runtime = bcsub( bcadd($endtime[0],$endtime[1],9) , bcadd($starttime[0],$starttime[1],9) ,9 );
            $output->writeln('本次运行时间为：'.$runtime);
            exit();//ljq-
        }

        $output->writeln('！！！结束！！！');
    }

    protected function screencap()
    {
        switch ($this->screencap_way) {
            case 1:
                exec("adb shell screencap -p | sed 's/\r$//' > ./{$this->pic_name}");
                break;
            case 2:
                exec('adb shell screencap -p',$str);
//                $str = str_replace("\r\n","\n",$str);
//                $str = str_replace("\r\r\n","\n",$str);
                file_put_contents('./'.$this->pic_name,$str);
                break;
            case 3:
                exec("adb shell screencap -p /sdcard/{$this->pic_name}");
                exec("adb pull /sdcard/{$this->pic_name} ./{$this->pic_name}");
                break;
            case 4:
                exec('adb shell screencap -p',$this->strImage);
//                $str = str_replace("\r\n","\n",$str);
//                $str = str_replace("\r\r\n","\n",$str);
                break;
            default:
                //默认操作
        }
    }

    protected function checkRGB()
    {
        $im = ImageCreateFromPng($this->pic_name);
        foreach ($this->arrRGB as $option=>$v){
            $arrXY = $this->getScreenResolution($option);
            $rgb = ImageColorAt($im, $arrXY['x'], $arrXY['y']);
            $arrRGB = $this->getRGB($rgb);
            if($arrRGB != $this->arrRGB_white){
                return true;
            }
        }
        return false;
    }

    protected function getRGB($rgb)
    {
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        return ['r'=>$r,'g'=>$g,'b'=>$b];
    }

    protected function requestAipOcr()
    {
        if(is_null($this->objAipOcr)){
            $this->objAipOcr = new AipOcr($this->AppID, $this->APIKey, $this->SecretKey);
        }
        if($this->screencap_way==4){
            $image = $this->strImage;
        }else{
            $image = file_get_contents('./'.$this->pic_name);
        }
        $this->objAipOcr->setConnectionTimeoutInMillis(800);
        $this->objAipOcr->setSocketTimeoutInMillis(1000);
        $this->arrWordsResult = $this->objAipOcr->basicGeneral($image);
        if(is_array($this->arrWordsResult)){
            $msg = json_encode($this->arrWordsResult,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        }else{
            $msg = $this->arrWordsResult;
        }
        Log::write($msg,'post-result',true);//ljq-
    }

    protected function getQuestionAndOption()
    {
        if(empty($this->arrWordsResult['words_result_num']) || $this->arrWordsResult['words_result_num']<11){
            $this->output->writeln('提取问题&选项失败，请重试');
            return false;
        }
        if($this->arrWordsResult['words_result_num']<13){
            $this->question = $this->arrWordsResult['words_result'][5]['words'];//问题共1行
            $this->arrOption = [
                'A' => $this->arrWordsResult['words_result'][7]['words'],
                'B' => $this->arrWordsResult['words_result'][8]['words'],
                'C' => $this->arrWordsResult['words_result'][9]['words'],
                'D' => $this->arrWordsResult['words_result'][10]['words'],
            ];
        }else{
            $this->question = $this->arrWordsResult['words_result'][5]['words'].$this->arrWordsResult['words_result'][6]['words'];//问题共2行
            $this->arrOption = [
                'A' => $this->arrWordsResult['words_result'][8]['words'],
                'B' => $this->arrWordsResult['words_result'][9]['words'],
                'C' => $this->arrWordsResult['words_result'][10]['words'],
                'D' => $this->arrWordsResult['words_result'][11]['words'],
            ];
        }
        return true;
    }

    protected function searchAnswer()
    {
        $userAgen1 = 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.75 Safari/537.36';
        $userAgen2 = 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.146 Safari/537.36';
        $userAgen3 = 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; BIDUBrowser 2.6)';
        $header = [
//            'Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8',//post请求
//            'Content-Type' => 'application/json;charset=utf-8',//ajax请求
            'Content-Type' => 'text/html;charset=UTF-8',//网页请求
            'Accept-Language' => 'Accept-Language:zh-CN,zh;q=0.9',
            'Connection' => 'keep-alive',
            'User-Agent' => $userAgen1,
        ];
        switch ($this->search_type) {
            case 1:
                //请求参数
                $url = "http://www.baidu.com/s?wd=".$this->question;
                $header['Host'] = 'www.baidu.com';
                $header['Referer'] = 'http://www.baidu.com';
                //匹配答案正则规则
                $pattrrn = '/<div class="c-abstract">(.*?)<\/div>/';
                $matches_in = 1;
                break;
            case 2:
                //请求参数
                $url = 'https://zhidao.baidu.com/search?word='.$this->question;
                $header['Host'] = 'zhidao.baidu.com';
                $header['Referer'] = 'http://zhidao.baidu.com';
                //匹配答案正则规则
                $da_text = '<i class="i-answer-text">答：<\/i>';
                $tuijiandaan_text = '<span class="flag">推荐答案<i class="i-right-arrow"><\/i><\/span>';
                $pattrrn = '/<dd class="dd answer">('.$da_text.'|'.$tuijiandaan_text.')(.*?)<\/dd>/';
                $matches_in = 2;
                break;
            default:
                //默认
                return false;
        }
        //发起请求
        $objHttpClient = new AipHttpClient();
        $objHttpClient->setConnectionTimeoutInMillis(800);
        $objHttpClient->setSocketTimeoutInMillis(1000);
        $res = $objHttpClient->get($url, [], $header);
        if($res['code']!=200){
            $this->output->writeln('搜索答案失败，请确保网络畅通');
            return -1;
        }
//        file_put_contents('./zzz.html',$res['content']);//ljq-
//        $res['content'] = file_get_contents('./zzz.html');//ljq-

        //提取答案
        $content = $this->getUtf8String($res['content']);
        preg_match_all($pattrrn,$content,$arr);
//        file_put_contents('./zzz.log',var_export($arr,true));//ljq-

        //匹配答案
        foreach ($this->arrOption as $k=>$v){
            $this->arrAnswer[$k] = 0;
            foreach ($arr[$matches_in] as $str){
                $this->arrAnswer[$k] += substr_count($str,$v);//速度最快，但只统计各选项出现次数 0.000059000s
//                $this->arrAnswer[$k] += similar_text($str,$v);//精度高 0.000232935s
//                $this->arrAnswer[$k] += levenshtein($str,$v);//速度快，但有长度限制，暂时不用
            }
        }
    }

    protected function getUtf8String($str)
    {
        $encoding = mb_detect_encoding($str,array('ASCII','UTF-8','GB2312','GBK','BIG5'));
        if($encoding !== 'UTF-8'){
            $str = iconv('GBK','UTF-8',$str);
        }
        return $str;
    }

    protected function autoClickOption()
    {
        $option = array_shift(array_keys($this->arrAnswer));
        $arrXY = $this->getScreenResolution($option);
        $command = "adb shell input tap {$arrXY['x']} {$arrXY['y']}";
        $this->output->writeln('自动选择 【'.$option.'】 ： '.$command);
        exec($command);
    }

    protected function getScreenResolution($option)
    {
        $arr = [
            '720*1280'=>[
                'A'=>['x'=>155,'y'=>690],
                'B'=>['x'=>155,'y'=>820],
                'C'=>['x'=>155,'y'=>950],
                'D'=>['x'=>155,'y'=>1070],
            ],
        ];
        return $arr[$this->ratio][$option];
    }

    protected function ocr()
    {
        $imgPath = "http://bj.ganji.com/tel/5463013757650d6c5e31093e563c51315b6c5c6c5237.png";
        $gjPhone = new gjPhone($imgPath);
//进行颜色分离
        $gjPhone->getHec();
//画出横向数据
        $horData = $gjPhone->magHorData();
        echo "===============横向数据==============<br/><br/><br/>";
        $gjPhone->drawWH($horData);
// 画出纵向数据
        $verData = $gjPhone->magVerData($horData);
        echo "<br/><br/><br/>===============纵向数据==============< br/><br/><br/>";
        $gjPhone->drawWH($verData);

// 输出电话
        $phone = $gjPhone->showPhone($verData);
        echo "<br/><br/><br/>===============电话==============<br /><br/><br/>".$phone;
    }
}