<!-- AI Chatbot -->
<div class="chatbot-toggle" id="chatbotToggle" title="BMB Asszisztens">
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
        <button class="chatbot-suggestion" data-question="Hogyan fogadhatok?" data-i18n="chatbot.howToBet">🎯 Hogyan fogadhatok?</button>
        <button class="chatbot-suggestion" data-question="Milyen sportokra fogadhatok?" data-i18n="chatbot.sports">🏆 Sportágak</button>
        <button class="chatbot-suggestion" data-question="Milyen bónuszok vannak?" data-i18n="chatbot.bonuses">🎁 Bónuszok</button>
        <button class="chatbot-suggestion" data-question="Hogyan fizethetek be?" data-i18n="chatbot.depositQ">💳 Befizetés</button>
        <button class="chatbot-suggestion" data-question="Hogyan kérhetek kifizetést?" data-i18n="chatbot.withdrawalQ">💰 Kifizetés</button>
        <button class="chatbot-suggestion" data-question="Hol találom az élő meccseket?" data-i18n="chatbot.liveMatchesQ">⚽ Élő meccsek</button>
        <button class="chatbot-suggestion" data-question="Mi az az eSport?" data-i18n="chatbot.esportQ">🎮 eSport</button>
    </div>

    <div class="chatbot-input-area">
        <input type="text" class="chatbot-input" id="chatbotInput" placeholder="Írj egy kérdést..." data-i18n-placeholder="chatbot.inputPlaceholder" autocomplete="off" maxlength="500">
        <button class="chatbot-send" id="chatbotSend" title="Küldés" data-i18n-title="chatbot.send">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<?php
$chatbotPath = $_SERVER['PHP_SELF'];
$chatbotPrefix = '../../';
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
<link rel="stylesheet" href="<?php echo $chatbotPrefix; ?>css/Chatbot/chatbot.css">
<script src="<?php echo $chatbotPrefix; ?>js/Chatbot/chatbot.js"></script>