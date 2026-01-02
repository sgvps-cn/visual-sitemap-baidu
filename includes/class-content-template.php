<?php
if (!defined('ABSPATH')) {
    exit('禁止直接访问！');
}

class VisualSitemap_ContentTemplate {

    public static function getAllTemplates() {
        return array(
            'default' => array(
                'name' => '默认模板',
                'description' => '标准的SEO友好模板',
                'structure' => 'standard'
            ),
            'news' => array(
                'name' => '新闻资讯模板',
                'description' => '适合新闻类内容的模板',
                'structure' => 'news'
            ),
            'blog' => array(
                'name' => '博客文章模板',
                'description' => '适合个人博客的模板',
                'structure' => 'blog'
            ),
            'product' => array(
                'name' => '产品介绍模板',
                'description' => '适合产品介绍的模板',
                'structure' => 'product'
            )
        );
    }

    public static function getRecommendedTemplate($post_id) {
        $categories = get_the_category($post_id);
        
        if (empty($categories)) {
            return 'default';
        }
        
        $category_slugs = array();
        foreach ($categories as $cat) {
            $category_slugs[] = $cat->slug;
        }
        
        // 根据分类关键词推荐模板
        if (in_array('news', $category_slugs) || in_array('资讯', $category_slugs)) {
            return 'news';
        } elseif (in_array('product', $category_slugs) || in_array('产品', $category_slugs)) {
            return 'product';
        } elseif (in_array('blog', $category_slugs) || in_array('博客', $category_slugs)) {
            return 'blog';
        }
        
        return 'default';
    }

    public static function applyTemplate($content, $template_type, $post_id = 0) {
        $templates = self::getAllTemplates();
        
        if (!isset($templates[$template_type])) {
            $template_type = 'default';
        }
        
        $post = get_post($post_id);
        $title = $post ? $post->post_title : '';
        $excerpt = $post ? $post->post_excerpt : '';
        
        // 根据模板类型构建内容结构
        switch ($template_type) {
            case 'news':
                return self::buildNewsTemplate($title, $content, $excerpt, $post_id);
            case 'blog':
                return self::buildBlogTemplate($title, $content, $excerpt, $post_id);
            case 'product':
                return self::buildProductTemplate($title, $content, $excerpt, $post_id);
            default:
                return self::buildDefaultTemplate($title, $content, $excerpt, $post_id);
        }
    }
    
    /**
     * 构建默认模板
     */
    private static function buildDefaultTemplate($title, $content, $excerpt, $post_id) {
        $template = "<h1 class=\"article-title\">{$title}</h1>\n";
        
        if (!empty($excerpt)) {
            $template .= "<p class=\"article-excerpt\">{$excerpt}</p>\n";
        }
        
        $template .= "<div class=\"article-content\">\n";
        $template .= $content;
        $template .= "\n</div>\n";
        
        return $template;
    }

    private static function buildNewsTemplate($title, $content, $excerpt, $post_id) {
        $date = get_the_date('Y-m-d H:i', $post_id);
        $author = get_the_author_meta('display_name', get_post_field('post_author', $post_id));
        
        $template = "<article class=\"news-article\">\n";
        $template .= "<header>\n";
        $template .= "<h1>{$title}</h1>\n";
        $template .= "<div class=\"news-meta\">\n";
        $template .= "<span class=\"news-date\">发布时间：{$date}</span>\n";
        $template .= "<span class=\"news-author\">作者：{$author}</span>\n";
        $template .= "</div>\n";
        $template .= "</header>\n";
        
        if (!empty($excerpt)) {
            $template .= "<div class=\"news-summary\"><p>{$excerpt}</p></div>\n";
        }
        
        $template .= "<div class=\"news-content\">\n";
        $template .= $content;
        $template .= "</div>\n";
        $template .= "</article>\n";
        
        return $template;
    }

    private static function buildBlogTemplate($title, $content, $excerpt, $post_id) {
        $tags = get_the_tags($post_id);
        $tag_list = '';
        
        if ($tags) {
            $tag_links = array();
            foreach ($tags as $tag) {
                $tag_links[] = "<a href=\"" . get_tag_link($tag->term_id) . "\">{$tag->name}</a>";
            }
            $tag_list = implode(', ', $tag_links);
        }
        
        $template = "<article class=\"blog-post\">\n";
        $template .= "<h1>{$title}</h1>\n";
        
        if (!empty($excerpt)) {
            $template .= "<blockquote class=\"blog-quote\">{$excerpt}</blockquote>\n";
        }
        
        $template .= "<div class=\"blog-body\">\n";
        $template .= $content;
        $template .= "</div>\n";
        
        if (!empty($tag_list)) {
            $template .= "<div class=\"blog-tags\">\n";
            $template .= "<span>标签：</span>{$tag_list}\n";
            $template .= "</div>\n";
        }
        
        $template .= "</article>\n";
        
        return $template;
    }

    private static function buildProductTemplate($title, $content, $excerpt, $post_id) {
        $template = "<div class=\"product-detail\">\n";
        $template .= "<h1 class=\"product-title\">{$title}</h1>\n";
        
        if (!empty($excerpt)) {
            $template .= "<div class=\"product-intro\">\n";
            $template .= "<h3>产品简介</h3>\n";
            $template .= "<p>{$excerpt}</p>\n";
            $template .= "</div>\n";
        }
        
        $template .= "<div class=\"product-description\">\n";
        $template .= "<h3>详细说明</h3>\n";
        $template .= $content;
        $template .= "</div>\n";
        $template .= "</div>\n";
        
        return $template;
    }
}
