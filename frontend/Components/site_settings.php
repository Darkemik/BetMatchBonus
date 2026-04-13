<?php
/**
 * Frontend site settings - kiírja a window.SITE_SETTINGS globális JS objektumot.
 * Használat: <?php include 'site_settings.php'; ?> a </body> előtt vagy a <head>-ben.
 * Szükséges: connect.php és settings_helper.php legyen betöltve.
 */
if (!function_exists('get_setting_int')) {
    $siteSettingsBase = dirname(dirname(__DIR__));
    require_once $siteSettingsBase . '/backend/connect.php';
    require_once $siteSettingsBase . '/backend/Auth/settings_helper.php';
}
?>
<script>
window.SITE_SETTINGS = {
    min_deposit: <?php echo get_setting_int('min_deposit', 3000); ?>,
    max_deposit: <?php echo get_setting_int('max_deposit', 600000); ?>,
    min_withdrawal: <?php echo get_setting_int('min_withdrawal', 6000); ?>,
    max_withdrawal: <?php echo get_setting_int('max_withdrawal', 50000); ?>,
    min_bet_amount: <?php echo get_setting_int('min_bet_amount', 100); ?>,
    daily_tip_multiplier: <?php echo get_setting_float('daily_tip_multiplier', 1.2); ?>,
    odds_pyramid_multiplier: <?php echo get_setting_float('odds_pyramid_multiplier', 1.3); ?>,
    min_pyramid_selections: <?php echo get_setting_int('min_pyramid_selections', 6); ?>,
    min_password_length: <?php echo get_setting_int('min_password_length', 7); ?>,
    min_user_age: <?php echo get_setting_int('min_user_age', 18); ?>,
    min_phone_length: <?php echo get_setting_int('min_phone_length', 11); ?>
};
</script>
