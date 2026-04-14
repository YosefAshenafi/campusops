<?php

namespace app\service;

class PinyinService
{
    protected static array $charMap = [
        '啊' => 'a', '八' => 'ba', '擦' => 'ca', '大' => 'da', '饿' => 'e',
        '发' => 'fa', '咖' => 'ga', '哈' => 'ha', '啊' => 'a', '击' => 'ji',
        '可' => 'ke', '啦' => 'la', '妈' => 'ma', '那' => 'na', '哦' => 'o',
        '啪' => 'pa', '七' => 'qi', '然' => 'ran', '撒' => 'sa', '他' => 'ta',
        '哇' => 'wa', '西' => 'xi', '呀' => 'ya', '在' => 'zai', '中' => 'zhong',
        '爱' => 'ai', '被' => 'bei', '从' => 'cong', '的' => 'de', '而' => 'er',
        '非' => 'fei', '给' => 'gei', '和' => 'he', '就' => 'jiu', '可' => 'ke',
        '了' => 'le', '吗' => 'ma', '呢' => 'ne', '或' => 'huo', '破' => 'po',
        '去' => 'qu', '是' => 'shi', '同' => 'tong', '为' => 'wei', '下' => 'xia',
        '呀' => 'ya', '在' => 'zai'
    ];

    protected static array $initialMap = [
        'b' => ['八', '把', '爸', '伯', '办', '半', '帮', '棒', '包', '宝', '保', '报', '北', '本', '比', '必', '笔'],
        'p' => ['怕', '排', '派', '盘', '旁', '跑', '赔', '配', '品', '平'],
        'm' => ['妈', '马', '吗', '买', '满', '忙', '毛', '没', '每', '门'],
        'f' => ['发', '法', '翻', '饭', '方', '放', '飞', '费', '分', '份'],
        'd' => ['大', '打', '到', '道', '得', '等', '低', '地', '点', '定'],
        't' => ['他', '她', '它', '台', '太', '天', '同', '图', '土'],
        'n' => ['那', '拿', '哪', '南', '难', '内', '能', '你'],
        'l' => ['拉', '来', '蓝', '老', '乐', '里', '力', '两'],
        'g' => ['嘎', '给', '工', '公', '共', '功', '过', '国'],
        'k' => ['卡', '开', '看', '可', '课', '空'],
        'h' => ['哈', '还', '好', '和', '河', '很', '红'],
        'j' => ['机', '几', '加', '家', '见', '将', '经'],
        'q' => ['七', '其', '起', '千', '请', '去'],
        'x' => ['西', '下', '夏', '先', '想', '学'],
        'zh' => ['中', '字', '主', '住', '着', '知', '至'],
        'ch' => ['出', '处', '创', '春', '从'],
        'sh' => ['是', '时', '十', '事', '收', '受'],
        'z' => ['在', '早', '则', '增', '总'],
        'c' => ['从', '此', '存', '村'],
        's' => ['四', '思', '死', '所', '算']
    ];

    public static function toPinyin(string $text): string
    {
        $result = '';
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($chars as $char) {
            if (isset(self::$charMap[$char])) {
                $result .= self::$charMap[$char];
            } else {
                $result .= $char;
            }
            $result .= ' ';
        }

        return trim($result);
    }

    public static function getInitial(string $pinyin): array
    {
        $initial = strtolower(substr($pinyin, 0, 1));
        return self::$initialMap[$initial] ?? [];
    }

    public static function searchByPinyin(string $query): array
    {
        $queryLower = strtolower($query);
        $results = [];
        
        $initialSuggestions = self::getInitial($queryLower);
        
        if (!empty($initialSuggestions)) {
            foreach ($initialSuggestions as $suggestion) {
                $results[] = $suggestion;
            }
        }

        return $results;
    }

    public static function convertToSearchable(string $text): string
    {
        $pinyin = self::toPinyin($text);
        return $text . ' ' . $pinyin . ' ' . str_replace(' ', '', $pinyin);
    }
}