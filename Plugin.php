<?php
/**
 * 外链跳转提示工具 Pro
 * 
 * 智能识别子域名，自动清洗白名单格式，支持自定义跳转页模板。
 * 
 * @package ExternalLink
 * @author huntuo146
 * @version 2.1.0
 * @link https://github.com/huntuo146/typecho_ExternalLink
 */

namespace TypechoPlugin\ExternalLink;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Typecho\Widget\Helper\Form\Element\Radio;
use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Plugin implements PluginInterface
{
    public static function activate()
    {
        Helper::addRoute('external_link_jump', '/go', 'TypechoPlugin\ExternalLink\Action', 'render');
        \Typecho\Plugin::factory('Widget_Archive')->footer = __CLASS__ . '::footer';
        return _t('插件已激活，白名单逻辑已增强');
    }

    public static function deactivate()
    {
        Helper::removeRoute('external_link_jump');
    }

    public static function config(Form $form)
    {
        // 1. 白名单配置
        $whitelist = new Textarea(
            'whitelist', 
            null, 
            "github.com\ncloudflare.com\nbaidu.com", 
            _t('域名白名单（根域名）'), 
            _t('每行一个。只需输入根域名（如 github.com），该域名及其所有子域名（如 api.github.com）均会自动放行。<br>会自动忽略 http:// 前缀和空格。')
        );
        $form->addInput($whitelist);

        // 2. 页面标题
        $pageTitle = new Text(
            'pageTitle', 
            null, 
            '即将离开本站', 
            _t('跳转页标题'), 
            _t('浏览器标签页标题')
        );
        $form->addInput($pageTitle);

        // 3. 模板模式选择
        $mode = new Radio(
            'mode',
            ['default' => '使用默认精美模板', 'custom' => '使用自定义HTML代码'],
            'default',
            _t('跳转页模板模式'),
            _t('选择自定义后，下方的HTML代码将生效。')
        );
        $form->addInput($mode);

        // 4. 自定义 HTML 代码
        $customHtml = new Textarea(
            'customHtml', 
            null, 
            self::getExampleTemplate(), 
            _t('自定义 HTML 代码'), 
            _t('可用变量：<br><b>{url}</b> : 目标链接<br><b>{title}</b> : 标题<br><b>{intro}</b> : 提示语')
        );
        $form->addInput($customHtml);
        
        // 5. 默认模板提示语
        $alertText = new Textarea(
            'alertText', 
            null, 
            '您点击的链接非本站链接，请注意保护个人信息。', 
            _t('默认模板提示语'), 
            _t('仅在“默认模板”模式下显示。')
        );
        $form->addInput($alertText);
    }

    public static function personalConfig(Form $form) {}

    public static function footer()
    {
        $options = \Typecho\Widget::widget('Widget_Options');
        $pluginOpts = $options->plugin('ExternalLink');
        
        // --- 核心修复：PHP端强力清洗白名单数据 ---
        $whitelistStr = $pluginOpts->whitelist ?? '';
        
        // 1. 按换行符分割
        $rawList = preg_split("/[\r\n]+/", $whitelistStr);
        
        $cleanList = [];
        foreach ($rawList as $domain) {
            $domain = trim($domain); // 去除首尾空格
            if (empty($domain)) continue;

            // 2. 移除 http:// 或 https:// 前缀
            $domain = preg_replace('#^https?://#i', '', $domain);
            
            // 3. 移除路径部分（如 github.com/user -> github.com）
            $parts = explode('/', $domain);
            $domain = $parts[0];

            // 4. 转小写
            $cleanList[] = strtolower($domain);
        }

        // 自动加入本站域名
        $currentHost = strtolower(parse_url($options->siteUrl, PHP_URL_HOST));
        if ($currentHost && !in_array($currentHost, $cleanList)) {
            $cleanList[] = $currentHost;
        }
        
        $jsonWhitelist = json_encode(array_values(array_unique($cleanList)));

        echo <<<SCRIPT
<script>
(function(){
    var whitelist = {$jsonWhitelist};
    var links = document.querySelectorAll('a');
    var currentHost = window.location.hostname.toLowerCase();
    
    // 获取标准化的域名
    function getDomain(url) {
        try { 
            var u = new URL(url);
            // 排除 mailto, tel 等非http协议
            if (u.protocol !== 'http:' && u.protocol !== 'https:') return null;
            return u.hostname.toLowerCase(); 
        } catch(e) { return null; }
    }

    // --- 核心修复：JS端严格匹配逻辑 ---
    function isWhitelisted(domain) {
        if (!domain) return false;
        
        for(var i=0; i<whitelist.length; i++) {
            var w = whitelist[i]; // 已经是小写且去除了空格
            
            // 1. 完全相等 (例如 github.com === github.com)
            if (domain === w) return true;
            
            // 2. 子域名匹配 (例如 api.github.com 匹配 .github.com)
            // 必须加上点号，防止 my-github.com 匹配到 github.com
            if (domain.endsWith('.' + w)) return true;
        }
        return false;
    }

    links.forEach(function(link) {
        var href = link.href;
        // 基础过滤
        if (!href || href.indexOf('javascript:') === 0 || href.indexOf('#') === 0) return;
        
        var targetDomain = getDomain(href);
        
        // 如果无法获取域名（可能是相对路径或非http协议），直接忽略，视为内链
        if (!targetDomain) return;
        
        // 如果是外链 且 不在白名单
        if (t
