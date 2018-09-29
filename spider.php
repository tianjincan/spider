<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use yii\console\Controller;
use Yii;


/**
 * This command echoes the first argument that you have entered.
 *
 * This command is provided as an example for you to learn how to create console commands.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class SpiderController extends Controller
{
    public $verbArr = ["蒸","征","煮","找","寻","觅"];
    public $femaleNounArr = ["女","妹子","姑娘","妹纸","girl","MM","小姐姐","她"];
    public $maleNounArr = ["男","汉子","汉纸","boy","GG","小哥哥","他","奶狗"];
    /**
     * This command echoes what you have entered as the message.
     * @param string $message the message to be echoed.
     * @return int Exit code
     */
    public function actionIndex()
    {
        $topicUrl = "https://www.douban.com/group/topic/";
        $url="https://www.douban.com/group/search?group=274483&cat=1013&q=94&sort=time";
        header("Content-type:text/html;charset=utf-8");
        //addslashes stripslashes


        //$this->test();
        $this->createTable();
        $ageArr = [94,95,96];
        $groupArr = [274483,159755,216303,239545,368133];
        $groupToNameArr=[274483=>"上海恋爱10万人",159755=>"上海单身男女",216303=>"上海高学历",239545=>"上海最靠谱2",368133=>"上海最靠谱3"];


        $groupArr1 = [56317];
        $groupToNameArr1=[56317=>'mtg'];
        $sql = "SELECT topic_id FROM douban";
        $savedIdArr = Yii::$app->db->createCommand($sql)->queryColumn();


        Yii::$app->db->createCommand($sql)->execute();
        foreach ($ageArr as $age) {
            foreach ($groupArr as $group) {

                $url = "https://www.douban.com/group/search?group={$group}&cat=1013&q={$age}&sort=time";
                $output = $this->getUrlContent($url);

                //[1=>href,2=>title,3=>time]
                $topicIdArr=[];
                $hrefArr = [];
                $titleArr = [];
                $timeArr =[];
                $contentArr = [];
                $authorArr =[];
                $dateLimit = date("Y-m-d H:i:s",strtotime("-15 days"));
                preg_match_all('/<tr class="pl">[\s\S]*?<a class="" href="([\s\S]*?)"[\s\S]*?>([\s\S]*?)<\/a>[\s\S]*?<td class="td-time" title="([\s\S]*?)"/',$output,$match);
                foreach ($match[1] as $key=>$href) {
                    if ($match[3][$key]<= $dateLimit)
                        continue;
                    $topicIdTmp = preg_replace("/[^0-9]/",'',$href);
                    $topicIdArr[] = $topicIdTmp;
                    $hrefArr[$topicIdTmp] = $this->filterEmoji(addslashes($href));
                    $timeArr[$topicIdTmp] = $match[3][$key];
                    $titleArr[$topicIdTmp] = $this->filterEmoji(addslashes($match[2][$key]));
                }
                $rightIdArr = [];
                foreach ($topicIdArr as $topicId) {

                    if (in_array($topicId,$savedIdArr))
                        continue;
                    $rightIdArr[] = $topicId;
                    $urlTmp = $topicUrl.$topicId;
                    $output = $this->getUrlContent1($urlTmp);
                    preg_match_all('/<span class="from">[\s\S]*?<a href="https:\/\/www.douban.com\/people\/([\s\S]*?)\/">([\s\S]*?)<\/a>[\s\S]*?<\/span>/',$output,$match);
                    $authorArr[$topicId] = ['name'=>$this->filterEmoji(addslashes($match[2][0])),'id'=>$this->filterEmoji(addslashes($match[1][0]))];
                    preg_match_all('/<div id="link-report" class="">([\s\S]*?)<div id="link-report_group">/',$output,$match);
                    $contentArr[$topicId] = $this->filterEmoji(addslashes($match[1][0]));

                }
                foreach ($rightIdArr as $rightId) {
                    $ensureFemale = $this->filterFemale($contentArr[$rightId]) && $this->filterFemale($titleArr[$rightId],'title');

                    if (!$ensureFemale){
                        continue;
                    }
                    echo $timeArr[$rightId].":".$rightId.":".$titleArr[$rightId]."\n";
                    $sql = "INSERT IGNORE INTO douban VALUES($rightId,$age,";
                    $sql.= "'{$authorArr[$rightId]['id']}'".",";
                    $sql.= "'{$authorArr[$rightId]['name']}',";
                    $sql.= "'{$titleArr[$rightId]}',";
                    $sql.= "'{$contentArr[$rightId]}',";
                    $sql.= "'{$timeArr[$rightId]}',";
                    $sql.= "'{$groupToNameArr[$group]}',1)";
                    Yii::$app->db->createCommand($sql)->execute();


                }

            }
        }









    }
    public function test(){
        $a="  ";



        var_dump($a);die;
        preg_match("/.*?女.*?男/u",$b,$match);
        print_r($match);die;
        $isFemale = $this->filterByBreakNoun($b,implode("|",$this->verbArr),$this->maleNounArr);

        var_dump($isFemale);die;
        $ensureFemale = $this->filterFemale($b) ;
        echo 22;
        var_dump($ensureFemale);die;
    }
    public function filterFemale($content,$type="content"){
        $content = strip_tags($content);
        $content = $this->filterEmoji($content);
        $content = str_replace("'","",$content);
        $content = str_replace('"',"",$content);
        $content = str_replace(' ',"",$content);
        $content = str_replace("\n","",$content);

        $verbArr = $this->verbArr;
        $femaleNounArr = $this->femaleNounArr;
        $maleNounArr = $this->maleNounArr;
        $verbStr = implode("|",$verbArr);

        $isMale = $this->filterByBreakNoun($content,$verbStr,$femaleNounArr);

        $isFemale = $this->filterByBreakNoun($content,$verbStr,$maleNounArr);

        //  otherCondition

        $specialLimit = false;
        if ($type == "title"){
            $specialLimit = $this->getSpecailLimit($content);

        }
        if ($isMale){
            if ($isFemale){
                if ($specialLimit){
                    return false;
                }
                return true;
            }
            return false;
        }elseif ($isFemale){
            return true;
        }else{
            if ($specialLimit){
                return false;
            }
        }

        return true;

    }
    public function getSpecailLimit($content){
        preg_match("/.*?女.*?男/u",$content,$match1);
        if ($match1){
            return false;
        }
        preg_match("/.*?男/u",$content,$match2);
        if ($match2){
            return true;
        }
        preg_match("/[微信群|qq群|QQ群]/u",$content,$match3);
        if ($match3){
            return true;
        }
        return false;

    }

    public function filterByBreakNoun($content,$verbStr,$nounArr){

        foreach ($nounArr as $noun) {
            if (preg_match("/[$verbStr].*$noun/u",$content,$match)){
                return true;
            }
        }


        return false;

    }

    public function filterEmoji($str)
    {
        $str = preg_replace_callback(
            '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $str);

        return $str;
    }


    private function mainFunction($url){

    }
    private function getUrlContent($url){
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);//我弄的一个小论坛 ＝＝
        curl_setopt($ch,CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1 )");
        curl_setopt($ch,CURLOPT_HEADER,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
    private function getUrlContent1($url){
        $url .= "/";
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);//我弄的一个小论坛 ＝＝
        curl_setopt($ch,CURLOPT_USERAGENT,"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36");
        curl_setopt($ch,CURLOPT_HEADER,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
    private function createTable(){
        $sql = "CREATE TABLE IF NOT EXISTS `douban` (
          `topic_id` int(10) NOT NULL AUTO_INCREMENT,
          `age` tinyint(3) NOT NULL,
          `female_id` varchar(55) DEFAULT '0',
          `female_name` varchar(55) NOT NULL,
          `note_name` varchar(55) NOT NULL,
          `note_content` text,
          `updated_at` datetime DEFAULT NULL,
          `group_name` varchar(50) DEFAULT '',
          `is_right` tinyint(3) DEFAULT '0',
          PRIMARY KEY (`topic_id`) USING BTREE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
        Yii::$app->db->createCommand($sql)->execute();
    }

}
