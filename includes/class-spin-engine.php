<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

class VisualSitemap_SpinEngine {

    private $synonym_dict;

    private $spin_level;

    private $spin_mode;

    private $synonym_keys = array();

    public function __construct($level = 2, $mode = 'word') {
        $this->spin_level = max(1, min(5, intval($level)));
        $this->spin_mode = in_array($mode, array('word', 'sentence', 'both')) ? $mode : 'word';
        $this->initSynonymDict();
    }

    private function initSynonymDict() {
        // 基础同义词库
        $this->synonym_dict = array(
            // 动词
            '使用' => array('利用', '应用', '采用', '运用'),
            '学习' => array('掌握', '了解', '研习'),
            '实现' => array('达成', '完成', '落实'),
            '获得' => array('取得', '得到', '赢得', '获取'),
            '需要' => array('必要', '必需', '需求'),
            '认为' => array('觉得', '以为', '感觉'),
            '发现' => array('发觉', '找到', '寻得'),
            '提高' => array('提升', '增加', '增进', '改善'),
            '促进' => array('推动', '推进', '促使'),
            '帮助' => array('协助', '辅助', '助力', '帮忙'),

            // 形容词
            '重要' => array('关键', '核心', '主要'),
            '优秀' => array('良好', '出色', '卓越'),
            '有效' => array('管用', '奏效', '生效'),
            '简单' => array('简易', '简便', '容易'),
            '复杂' => array('繁杂', '错综'),
            '快速' => array('迅速', '快捷', '敏捷'),
            '专业' => array('专业', '专业'),
            '全新' => array('崭新', '全新'),

            // 名词
            '方法' => array('方式', '办法', '途径', '手段'),
            '效果' => array('成效', '功效'),
            '内容' => array('实质', '内涵'),
            '问题' => array('难题', '困难'),
            '建议' => array('提议', '意见'),
            '用户' => array('使用者', '客户'),
            '系统' => array('体系'),
            '功能' => array('功用', '机能'),
            '数据' => array('数据', '数据'),
            '信息' => array('信息', '信息'),

            // 连接词
            '但是' => array('然而', '可是', '不过'),
            '因此' => array('所以', '因而', '故此'),
            '而且' => array('并且', '此外', '况且'),
            '另外' => array('此外', '再者'),
            '首先' => array('第一', '起初'),
            '总之' => array('总而言之', '综上所述', '简而言之'),
        );

        // 缓存同义词键数组,提升遍历性能
        $this->synonym_keys = array_keys($this->synonym_dict);
    }

    public function loadCustomSynonyms($custom_synonyms) {
        if (is_array($custom_synonyms)) {
            $this->synonym_dict = array_merge($this->synonym_dict, $custom_synonyms);
        }
    }

    public function spin($content) {
        if (empty($content)) {
            return $content;
        }
        
        $spun_content = $content;
        
        // 根据模式选择处理方式
        if ($this->spin_mode === 'word' || $this->spin_mode === 'both') {
            $spun_content = $this->spinByWords($spun_content);
        }
        
        if ($this->spin_mode === 'sentence' || $this->spin_mode === 'both') {
            $spun_content = $this->spinBySentences($spun_content);
        }
        
        return $spun_content;
    }

    private function spinByWords($content) {
        $spun_content = $content;
        $replace_count = 0;

        // 根据强度决定替换比例
        $replace_ratio = $this->spin_level * 0.15; // 15%-75%替换率

        // 使用缓存的键数组遍历
        foreach ($this->synonym_keys as $original) {
            $synonyms = $this->synonym_dict[$original];

            // 随机决定是否替换
            if (mt_rand(1, 100) / 100 > $replace_ratio) {
                continue;
            }

            if (count($synonyms) < 1) {
                continue;
            }

            // 随机选择一个同义词
            $replacement = $synonyms[array_rand($synonyms)];

            // 简单替换（实际应用中需要更复杂的词边界检测）
            $pattern = '/\b' . preg_quote($original, '/') . '\b/u';
            $count = 0;
            $spun_content = preg_replace($pattern, $replacement, $spun_content, 1, $count);

            if ($count > 0) {
                $replace_count++;
            }

            // 限制单次处理的最大替换次数
            if ($replace_count > 50) {
                break;
            }
        }

        return $spun_content;
    }

    private function spinBySentences($content) {
        // 提取段落
        $paragraphs = preg_split('/\n\s*\n/', $content);
        
        foreach ($paragraphs as &$paragraph) {
            if (empty(trim($paragraph))) {
                continue;
            }
            
            // 根据强度决定是否重组
            if (mt_rand(1, 10) > $this->spin_level * 2) {
                continue;
            }
            
            // 提取句子
            $sentences = preg_split('/([。！？!?]+)/', $paragraph, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            // 重组句子对（交换相邻句子）
            for ($i = 0; $i < count($sentences) - 3; $i += 2) {
                if (mt_rand(1, 10) > $this->spin_level) {
                    continue;
                }
                
                // 交换句子对
                $temp = $sentences[$i];
                $sentences[$i] = $sentences[$i + 2];
                $sentences[$i + 2] = $temp;
            }
            
            $paragraph = implode('', $sentences);
        }
        
        return implode("\n\n", $paragraphs);
    }

    public function calculateSpinDegree($original, $spun) {
        $original_words = preg_split('/[\s,.!?:;()]+/', $original);
        $spun_words = preg_split('/[\s,.!?:;()]+/', $spun);
        
        $original_set = array_count_values(array_filter($original_words));
        $spun_set = array_count_values(array_filter($spun_words));
        
        $common_words = array_intersect_key($original_set, $spun_set);
        $total_words = count($original_words);
        
        if ($total_words === 0) {
            return 0;
        }
        
        $common_count = 0;
        foreach ($common_words as $word => $count) {
            $common_count += min($count, $original_set[$word]);
        }
        
        $similarity = $common_count / $total_words;
        $spin_degree = (1 - $similarity) * 100;
        
        return round($spin_degree, 2);
    }
}
