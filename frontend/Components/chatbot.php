<!-- AI Chatbot v2.0 -->
<?php
// Chatbot ki/bekapcsolás ellenőrzése
if (!function_exists('get_setting_int')) {
    require_once dirname(dirname(__DIR__)) . '/backend/connect.php';
    require_once dirname(dirname(__DIR__)) . '/backend/Auth/settings_helper.php';
}
if (get_setting_int('chatbot_enabled', 1) === 0): ?>
<!-- Chatbot kikapcsolva -->
<?php else: ?>
<div class="chatbot-toggle" id="chatbotToggle" title="BMB Asszisztens" data-i18n-title="chatbot.name">
    <i class="fas fa-robot"></i>
    <span class="chatbot-notification" id="chatbotNotification">1</span>
</div>

<div class="chatbot-window" id="chatbotWindow">
    <div class="chatbot-header">
        <div class="chatbot-header-left">
            <div class="chatbot-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="chatbot-header-info">
                <span class="chatbot-name" data-i18n="chatbot.name">BMB Asszisztens</span>
                <span class="chatbot-status"><span class="chatbot-status-dot"></span> <span data-i18n="chatbot.online">Online</span></span>
            </div>
        </div>
        <div class="chatbot-header-actions">
            <button class="chatbot-header-btn" id="chatbotClear" title="Beszélgetés törlése" data-i18n-title="chatbot.clearChat">
                <i class="fas fa-trash-alt"></i>
            </button>
            <button class="chatbot-header-btn" id="chatbotClose" title="Bezárás" data-i18n-title="chatbot.close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <div class="chatbot-messages" id="chatbotMessages"></div>

    <div class="chatbot-suggestions" id="chatbotSuggestions">
        <button class="chatbot-suggestion" data-question="How can I bet?" data-question-hu="Hogyan fogadhatok?" data-question-en="How can I bet?" data-i18n="chatbot.howToBet">🎯 Hogyan fogadhatok?</button>
        <button class="chatbot-suggestion" data-question="What sports can I bet on?" data-question-hu="Milyen sportokra fogadhatok?" data-question-en="What sports can I bet on?" data-i18n="chatbot.sports">🏆 Sportágak</button>
        <button class="chatbot-suggestion" data-question="What bonuses are available?" data-question-hu="Milyen bónuszok vannak?" data-question-en="What bonuses are available?" data-i18n="chatbot.bonuses">🎁 Bónuszok</button>
        <button class="chatbot-suggestion" data-question="How can I make a deposit?" data-question-hu="Hogyan fizethetek be?" data-question-en="How can I make a deposit?" data-i18n="chatbot.depositQ">💳 Befizetés</button>
        <button class="chatbot-suggestion" data-question="#commands" data-question-hu="#parancsok" data-question-en="#commands" data-i18n="chatbot.cmds">⌨️ Parancsok</button>
        <button class="chatbot-suggestion" data-question="#summary" data-question-hu="#összegzés" data-question-en="#summary" data-i18n="chatbot.summaryQ">📋 Összegzés</button>
        <button class="chatbot-suggestion" data-question="#live" data-question-hu="#élő" data-question-en="#live" data-i18n="chatbot.liveQ">🔴 Élő meccsek</button>
    </div>

    <div class="chatbot-input-area">
        <input type="text" id="chatbotInput" class="chatbot-input" placeholder="Írj üzenetet vagy #parancsot..." data-i18n-placeholder="chatbot.inputPlaceholderCommand" autocomplete="off" maxlength="500">
        <button id="chatbotSend" class="chatbot-send" title="Küldés" data-i18n-title="chatbot.send"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<?php
$chatbotPath = $_SERVER['PHP_SELF'];
$chatbotPrefix = '../../';
$chatbotAssetVersion = '20260419-0318';
if (strpos($chatbotPath, '/frontend/Help/') !== false ||
    strpos($chatbotPath, '/frontend/MainMenu/') !== false ||
    strpos($chatbotPath, '/frontend/Live/') !== false ||
    strpos($chatbotPath, '/frontend/Esport/') !== false ||
    strpos($chatbotPath, '/frontend/Bonus/') !== false ||
    strpos($chatbotPath, '/frontend/UserProfile/') !== false ||
    strpos($chatbotPath, '/frontend/Admin/') !== false) {
    $chatbotPrefix = '../../';
} else {
    $chatbotPrefix = '../';
}
?>
<?php include __DIR__ . '/site_settings.php'; ?>
<link rel="stylesheet" href="<?php echo $chatbotPrefix; ?>css/Chatbot/chatbot.css?v=<?php echo $chatbotAssetVersion; ?>">
<script src="<?php echo $chatbotPrefix; ?>js/Chatbot/chatbot_v2.js?v=<?php echo $chatbotAssetVersion; ?>"></script>
<?php endif; ?>