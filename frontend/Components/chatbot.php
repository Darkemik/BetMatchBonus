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
                <span class="chatbot-name">BMB Asszisztens</span>
                <span class="chatbot-status"><span class="chatbot-status-dot"></span> Online</span>
            </div>
        </div>
        <div class="chatbot-header-actions">
            <button class="chatbot-header-btn" id="chatbotClear" title="Beszélgetés törlése">
                <i class="fas fa-trash-alt"></i>
            </button>
            <button class="chatbot-header-btn" id="chatbotClose" title="Bezárás">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <div class="chatbot-messages" id="chatbotMessages"></div>

    <div class="chatbot-suggestions" id="chatbotSuggestions">
        <button class="chatbot-suggestion" data-question="Hogyan fogadhatok?">🎯 Hogyan fogadhatok?</button>
        <button class="chatbot-suggestion" data-question="Milyen bónuszok vannak?">🎁 Bónuszok</button>
        <button class="chatbot-suggestion" data-question="Hogyan fizethetek be?">💳 Befizetés</button>
        <button class="chatbot-suggestion" data-question="Hogyan kérhetek kifizetést?">💰 Kifizetés</button>
        <button class="chatbot-suggestion" data-question="Hol találom az élő meccseket?">⚽ Élő meccsek</button>
        <button class="chatbot-suggestion" data-question="Mi az az eSport?">🎮 eSport</button>
    </div>

    <div class="chatbot-input-area">
        <input type="text" class="chatbot-input" id="chatbotInput" placeholder="Írj egy kérdést..." autocomplete="off" maxlength="500">
        <button class="chatbot-send" id="chatbotSend" title="Küldés">
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