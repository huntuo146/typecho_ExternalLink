<?php
/**
 * 外链跳转提示工具 Pro
 * 
 * 智能识别子域名，支持自定义跳转页模板（HTML/CSS），保护用户安全。
 * 
 * @package ExternalLink
 * @author huotuo146
 * @version 2.0.0
 * @link https://typecho.org
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
        return _t('插件已激活，请前往设置配置白名单');
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
            _t('每行一个。只需输入根域名（如 github.com），该域名的所有子域名（如 api.github.com）和所有页面均会自动放行。')
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
            _t('仅在模式选择“自定义”时生效。<br>可用变量：<br><b>{url}</b> : 目标链接地址<br><b>{title}</b> : 页面标题<br><b>{intro}</b> : 提示语')
        );
        $form->addInput($customHtml);
        
        // 5. 默认模板的提示语 (仅默认模板有效)
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
        
        // 处理白名单
        $whitelistStr = $pluginOpts->whitelist ?? '';
        $whitelistArr = array_filter(explode("\r\n", $whitelistStr));
        // 自动加入本站域名
        $whitelistArr[] = parse_url($options->siteUrl, PHP_URL_HOST);
        // 去除空行和空格
        $whitelistArr = array_map('trim', $whitelistArr);
        $jsonWhitelist = json_encode(array_values($whitelistArr));

        echo <<<SCRIPT
<script>
(function(){
    var whitelist = {$jsonWhitelist};
    var links = document.querySelectorAll('a');
    var currentHost = window.location.hostname;
    
    // 获取域名
    function getDomain(url) {
        try { return new URL(url).hostname; } catch(e) { return ''; }
    }

    // 智能匹配：支持根域名匹配 (例如 whitelist 包含 github.com，则 api.github.com 也返回 true)
    function isWhitelisted(domain) {
        if (!domain) return false;
        for(var i=0; i<whitelist.length; i++) {
            var w = whitelist[i];
            if (!w) continue;
            // 1. 完全相等
            if (domain === w) return true;
            // 2. 是子域名 (domain 以 .w 结尾)
            if (domain.endsWith('.' + w)) return true;
        }
        return false;
    }

    links.forEach(function(link) {
        var href = link.href;
        if (!href || href.indexOf('javascript:') === 0 || href.indexOf('#') === 0) return;
        
        var targetDomain = getDomain(href);
        
        // 逻辑：如果是有效域名 AND 不是当前域名 AND 不在白名单
        if (targetDomain && targetDomain !== currentHost && !isWhitelisted(targetDomain)) {
            var encodedUrl = btoa(encodeURIComponent(href));
            link.href = '{$options->siteUrl}go?url=' + encodedUrl;
            link.target = '_blank';
            link.rel = 'noopener noreferrer nofollow';
        }
    });
})();
</script>
SCRIPT;
    }

    // 提供一个自定义模板的初始示例
    private static function getExampleTemplate() {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{title}</title>
    <style>
        body{background:#f0f2f5;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
        .box{background:#fff;padding:40px;border-radius:10px;text-align:center;box-shadow:0 10px 25px rgba(0,0,0,0.1);}
        .btn{background:#007bff;color:#fff;text-decoration:none;padding:10px 30px;border-radius:5px;display:inline-block;margin-top:20px;}
    </style>
</head>
<body>
    <div class="box">
        <h2>安全性提示</h2>
        <p>{intro}</p>
        <p style="color:#888;font-size:12px;">目标：{url}</p>
        <a href="{url}" rel="nofollow" class="btn">继续访问</a>
    </div>
</body>
</html>
HTML;
    }
}
