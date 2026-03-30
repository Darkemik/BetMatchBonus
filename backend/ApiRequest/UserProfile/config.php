<?php
/**
 * UserProfile Configuration File
 * Central configuration for all UserProfile features
 */

// ============================================================
// GENERAL SETTINGS
// ============================================================

// Enable/Disable UserProfile Module
const USERPROFILE_ENABLED = true;

// User Session Timeout (in minutes)
const SESSION_TIMEOUT = 30;

// ============================================================
// PAYMENT SETTINGS
// ============================================================

// Minimum Deposit Amount
const MIN_DEPOSIT_AMOUNT = 1;

// Maximum Deposit Amount  
const MAX_DEPOSIT_AMOUNT = 100000;

// Deposit Processing Time (in hours)
const DEPOSIT_PROCESSING_TIME = 24;

// Minimum Withdrawal Amount
const MIN_WITHDRAWAL_AMOUNT = 100;

// Maximum Withdrawal Amount
const MAX_WITHDRAWAL_AMOUNT = 50000;

// Withdrawal Processing Time (in business days)
const WITHDRAWAL_PROCESSING_TIME = 2;

// Supported Payment Methods for Deposits
const DEPOSIT_PAYMENT_METHODS = [
    'credit_card' => 'Hitelkártya',
    'debit_card' => 'Bankkártya',
    'bank_transfer' => 'Banki átutalás',
    'upi' => 'UPI',
    'wallet' => 'E-pénztárca'
];

// Supported Payment Methods for Withdrawals
const WITHDRAWAL_PAYMENT_METHODS = [
    'bank_transfer' => 'Banki átutalás',
    'upi' => 'UPI',
    'wallet' => 'E-pénztárca'
];

// ============================================================
// PASSWORD SETTINGS
// ============================================================

// Minimum Password Length
const MIN_PASSWORD_LENGTH = 8;

// Require Mixed Case (uppercase + lowercase)
const REQUIRE_MIXED_CASE = true;

// Require Numbers in Password
const REQUIRE_NUMBERS = false;

// Require Special Characters in Password
const REQUIRE_SPECIAL_CHARS = false;

// Password Expiration Days (0 = never expires)
const PASSWORD_EXPIRATION_DAYS = 0;

// ============================================================
// BONUS SETTINGS
// ============================================================

// Supported Bonus Types
const BONUS_TYPES = [
    'welcome' => 'Üdvözlő Bónusz',
    'deposit_match' => 'Befizetés Megerősítés',
    'free_bet' => 'Ingyenes Fogadás',
    'cashback' => 'Pénz Vissza',
    'seasonal' => 'Szezonális Bónusz',
    'referral' => 'Referral Bónusz',
    'loyalty' => 'Lojalitás Bónusz'
];

// Bonus Claim Deadline (in days after offer)
const BONUS_CLAIM_DEADLINE = 90;

// Default Wagering Requirement Multiplier
const DEFAULT_WAGERING_REQUIREMENT = 1.0;

// ============================================================
// ACTIVITY LOG SETTINGS
// ============================================================

// Log Activity Types
const LOG_ACTIVITY_TYPES = [
    'login' => 'Bejelentkezés',
    'logout' => 'Kijelentkezés',
    'bet' => 'Fogadás',
    'deposit' => 'Befizetés',
    'withdrawal' => 'Kifizetés',
    'bonus' => 'Bónusz',
    'profile_update' => 'Profil Frissítés',
    'password_change' => 'Jelszó Módosítás'
];

// Activity Log Retention (in days, 0 = forever)
const ACTIVITY_LOG_RETENTION = 365;

// Store IP Address in Log
const LOG_IP_ADDRESS = true;

// Store User Agent in Log
const LOG_USER_AGENT = true;

// ============================================================
// TRANSACTION SETTINGS
// ============================================================

// Transaction History Limit (per page)
const TRANSACTION_HISTORY_LIMIT = 50;

// Transaction Statuses
const TRANSACTION_STATUSES = [
    'pending' => 'Függőben',
    'completed' => 'Befejezve',
    'failed' => 'Sikertelen',
    'cancelled' => 'Visszavont'
];

// ============================================================
// UI/UX SETTINGS
// ============================================================

// Success Alert Auto-hide Time (in seconds)
const SUCCESS_ALERT_TIMEOUT = 5;

// Error Alert Sticky (never auto-hide)
const ERROR_ALERT_STICKY = true;

// Enable Dark Mode
const ENABLE_DARK_MODE = true;

// Default Theme
const DEFAULT_THEME = 'light'; // 'light' or 'dark'

// Page Size for Pagination
const PAGE_SIZE = 25;

// ============================================================
// EMAIL NOTIFICATIONS
// ============================================================

// Send Email on Deposit
const EMAIL_ON_DEPOSIT = true;

// Send Email on Withdrawal
const EMAIL_ON_WITHDRAWAL = true;

// Send Email on Profile Update
const EMAIL_ON_PROFILE_UPDATE = false;

// Send Email on Password Change
const EMAIL_ON_PASSWORD_CHANGE = true;

// Send Email on Bonus Claim
const EMAIL_ON_BONUS_CLAIM = true;

// Email Template Path
const EMAIL_TEMPLATE_PATH = '/backend/Templates/Emails/';

// ============================================================
// SECURITY SETTINGS
// ============================================================

// Enable CSRF Protection
const ENABLE_CSRF_PROTECTION = true;

// Enable Rate Limiting
const ENABLE_RATE_LIMITING = true;

// Rate Limit: Maximum Requests per Minute
const RATE_LIMIT_REQUESTS = 60;

// Rate Limit: Time Window (in seconds)
const RATE_LIMIT_WINDOW = 60;

// IP Whitelist (empty = no whitelist)
const IP_WHITELIST = [];

// IP Blacklist (empty = no blacklist)
const IP_BLACKLIST = [];

// ============================================================
// API SETTINGS
// ============================================================

// API Version
const API_VERSION = '1.0.0';

// Enable API Logging
const ENABLE_API_LOGGING = true;

// API Log Path
const API_LOG_PATH = '/logs/api/';

// ============================================================
// DATABASE SETTINGS
// ============================================================

// Database Character Set
const DB_CHARSET = 'utf8mb4';

// Database Collation
const DB_COLLATION = 'utf8mb4_hungarian_ci';

// ============================================================
// FEATURE FLAGS
// ============================================================

// Enable Two-Factor Authentication (2FA)
const ENABLE_2FA = false;

// Enable Biometric Login
const ENABLE_BIOMETRIC_LOGIN = false;

// Enable Profile Picture Upload
const ENABLE_PROFILE_PICTURE = false;

// Maximum Profile Picture Size (in MB)
const MAX_PROFILE_PICTURE_SIZE = 5;

// Enable Export Transactions (CSV, PDF)
const ENABLE_EXPORT = true;

// Enable Real-time Notifications
const ENABLE_REALTIME_NOTIFICATIONS = false;

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Get Payment Method Label
 * @param string $method_key Payment method key
 * @param bool $is_deposit Is this for deposit? (true) or withdrawal? (false)
 * @return string Payment method label
 */
function getPaymentMethodLabel($method_key, $is_deposit = true) {
    $methods = $is_deposit ? DEPOSIT_PAYMENT_METHODS : WITHDRAWAL_PAYMENT_METHODS;
    return $methods[$method_key] ?? $method_key;
}

/**
 * Get Bonus Type Label
 * @param string $bonus_type Bonus type key
 * @return string Bonus type label
 */
function getBonusTypeLabel($bonus_type) {
    return BONUS_TYPES[$bonus_type] ?? $bonus_type;
}

/**
 * Get Activity Type Label
 * @param string $activity_type Activity type key
 * @return string Activity type label
 */
function getActivityTypeLabel($activity_type) {
    return LOG_ACTIVITY_TYPES[$activity_type] ?? $activity_type;
}

/**
 * Get Transaction Status Label
 * @param string $status Status key
 * @return string Status label
 */
function getTransactionStatusLabel($status) {
    return TRANSACTION_STATUSES[$status] ?? $status;
}

function formatCurrency($amount, $currency = 'HUF') {
    $symbols = [
        'HUF' => 'Ft',
        'EUR' => '€',
    ];
    $symbol = $symbols[$currency] ?? $currency;

    // HUF-nal nincs tizedes (kerekitjuk egesz szamra)
    if ($currency === 'HUF') {
        return number_format($amount, 0, ',', '.') . ' ' . $symbol;
    }
    return $symbol . ' ' . number_format($amount, 2, ',', '.');
}
/**
 * Validate Email
 * @param string $email Email to validate
 * @return bool Is valid email?
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate Password Strength
 * @param string $password Password to validate
 * @return array Validation result
 */
function validatePasswordStrength($password) {
    $result = [
        'valid' => true,
        'errors' => [],
        'strength' => 'weak'
    ];

    // Check minimum length
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $result['valid'] = false;
        $result['errors'][] = 'A jelszó legalább ' . MIN_PASSWORD_LENGTH . ' karakter hosszú kell legyen';
    }

    // Check mixed case
    if (REQUIRE_MIXED_CASE) {
        $has_upper = preg_match('/[A-Z]/', $password);
        $has_lower = preg_match('/[a-z]/', $password);
        if (!$has_upper || !$has_lower) {
            $result['valid'] = false;
            $result['errors'][] = 'A jelszó kis és nagybetűket is kell tartalmaznia';
        }
    }

    // Check numbers
    if (REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
        $result['valid'] = false;
        $result['errors'][] = 'A jelszó számokat is kell tartalmaznia';
    }

    // Check special characters
    if (REQUIRE_SPECIAL_CHARS && !preg_match('/[!@#$%^&*]/', $password)) {
        $result['valid'] = false;
        $result['errors'][] = 'A jelszó speciális karaktereket is kell tartalmaznia';
    }

    // Determine strength
    if ($result['valid']) {
        $score = strlen($password) >= 12 ? 3 : (strlen($password) >= 10 ? 2 : 1);
        $score += preg_match('/[0-9]/', $password) ? 1 : 0;
        $score += preg_match('/[!@#$%^&*]/', $password) ? 1 : 0;
        $result['strength'] = $score >= 4 ? 'strong' : ($score >= 2 ? 'medium' : 'weak');
    }

    return $result;
}

?>
