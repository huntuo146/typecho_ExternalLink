<?php
namespace TypechoPlugin\ExternalLink;

use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Action extends Widget
{
    public function render()
    {
        // 1. 解析参数
        $urlParam = $this->request->get('url');
        $targetUrl = '/';
        $valid = false;

        if (!empty($urlParam)) {
            $decoded = base64_decode($urlParam);
            $targetUrl = rawurldecode($decoded);
            if (filter_var($targetUrl, FILTER_VALIDATE_URL)) {
                $valid = true;
            }
        }

        // 2. 获取配置
        $options = Widget::widget('Widget_Options');
        $pluginOpts = $options->plugin('ExternalLink');
        
        $pageTitle = htmlspecialchars($pluginOpts->pageTitle ? $pluginOpts->pageTitle : '外链跳转提示');
        
        // 安全处理 URL 显示
        $safeUrlDisplay = htmlspecialchars($targetUrl);
        // 如果 URL 无效，回退到首页
        $finalLink = $valid ? $targetUrl : $options->siteUrl;
        
        // 3. 模板引擎逻辑
        if ($pluginOpts->mode === 'custom' && !empty($pluginOpts->customHtml)) {
            // --- 自定义模板模式 ---
            
            // 准备变量替换表
            $vars = [
                '{url}'   => $finalLink,           // 实际跳转链接
                '{title}' => $pageTitle,           // 页面标题
                '{intro}' => $pluginOpts->alertText // 提示语（虽然自定义模式通常自己写死，但也提供变量）
            ];
            
            // 简单的模板替换
            $html = str_replace(array_keys($vars), array_values($vars), $pluginOpts->customHtml);
            
            // 输出
            echo $html;
            
        } else {
            // --- 默认模板模式 (内置优化版) ---
            $intro = $pluginOpts->alertText ? nl2br($pluginOpts->alertText) : '您正在离开本站，前往第三方网站。';
            
            ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        :root { --primary: #3b82f6; --bg: #f8fafc; --card: #ffffff; --text: #1e293b; --sub: #64748b; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: var(--bg); display: flex; align-items: center; justify-content: center; height: 100vh; color: var(--text); }
        .card { background: var(--card); width: 90%; max-width: 480px; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1); text-align: center; animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .icon-circle { width: 80px; height: 80px; background: #eff6ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; color: var(--primary); font-size: 32px; }
        h1 { font-size: 20px; font-weight: 700; margin: 0 0 12px; }
        .desc { color: var(--sub); font-size: 15px; line-height: 1.6; margin-bottom: 24px; }
        .url-box { background: #f1f5f9; padding: 12px; border-radius: 8px; color: var(--sub); font-size: 13px; word-break: break-all; margin-bottom: 32px; border: 1px solid #e2e8f0; font-family: monospace; }
        .btn-group { display: flex; gap: 12px; justify-content: center; }
        .btn { padding: 12px 24px; border-radius: 8px; font-size: 15px; font-weight: 600; text-decoration: none; transition: all 0.2s; cursor: pointer; flex: 1; display: flex; align-items: center; justify-content: center; }
        .btn-primary { background: var(--primary); color: white; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.5); }
        .btn-primary:hover { background: #2563eb; transform: translateY(-1px); }
        .btn-secondary { background: white; color: var(--sub); border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #f8fafc; color: var(--text); border-color: #cbd5e1; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-circle">
            <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
        </div>
        
        <h1><?php echo $pageTitle; ?></h1>
        
        <div class="desc">
            <?php echo $intro; ?>
        </div>

        <?php if ($valid): ?>
            <div class="url-box"><?php echo $safeUrlDisplay; ?></div>
            <div class="btn-group">
                <a href="javascript:window.close();" class="btn btn-secondary">关闭页面</a>
                <a href="<?php echo $finalLink; ?>" rel="nofollow noopener" class="btn btn-primary">继续访问 &rarr;</a>
            </div>
        <?php else: ?>
            <div class="url-box" style="color:red;">目标链接无效或已损坏</div>
            <a href="<?php echo $options->siteUrl; ?>" class="btn btn-primary">返回首页</a>
        <?php endif; ?>
    </div>
</body>
</html>
            <?php
        }
    }
}
