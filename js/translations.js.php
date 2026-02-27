<?php
/**
 * JavaScript Translations
 * Generates a JavaScript file with translations using PHP's t() function
 */

// Load configuration and translations
$config = require_once '../config.php';
require_once '../includes/translations.php';

// Set content type to JavaScript
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

?>
/**
 * Translations Object for JavaScript
 * Generated from PHP translation system
 */
var translations = {
    // Common
    notice: <?php echo json_encode(t('notice')); ?>,
    ok: <?php echo json_encode(t('ok')); ?>,
    cancel: <?php echo json_encode(t('cancel')); ?>,
    verify: <?php echo json_encode(t('verify')); ?>,
    
    // Email verification
    email_address: <?php echo json_encode(t('email_address')); ?>,
    enter_your_email: <?php echo json_encode(t('enter_your_email')); ?>,
    enter_email: <?php echo json_encode(t('enter_email')); ?>,
    enter_valid_email: <?php echo json_encode(t('enter_valid_email')); ?>,
    email_verification_required: <?php echo json_encode(t('email_verification_required')); ?>,
    email_does_not_match: <?php echo json_encode(t('email_does_not_match')); ?>,
    
    // Code verification
    verification_code: <?php echo json_encode(t('verification_code')); ?>,
    enter_verification_code: <?php echo json_encode(t('enter_verification_code')); ?>,
    enter_code: <?php echo json_encode(t('enter_code')); ?>,
    code_sent_to_email: <?php echo json_encode(t('code_sent_to_email')); ?>,
    code_does_not_match: <?php echo json_encode(t('code_does_not_match')); ?>,
    invalid_code: <?php echo json_encode(t('invalid_code')); ?>,
    
    // Game actions
    confirm_delete_game: <?php echo json_encode(t('confirm_delete_game')); ?>,
    confirm_edit_game: <?php echo json_encode(t('confirm_edit_game')); ?>,
    enter_email_for_creating: <?php echo json_encode(t('enter_email_for_creating')); ?>,
    
    // Player actions
    confirm_resign: <?php echo json_encode(t('confirm_resign')); ?>,
    confirm_resignation: <?php echo json_encode(t('confirm_resignation')); ?>,
    enter_email_for_joining: <?php echo json_encode(t('enter_email_for_joining')); ?>,
    
    // Poll actions
    confirm_delete_poll: <?php echo json_encode(t('confirm_delete_poll')); ?>,
    enter_email_for_poll: <?php echo json_encode(t('enter_email_for_poll')); ?>,
    
    // Confirmations
    confirm_deletion: <?php echo json_encode(t('confirm_deletion')); ?>,
    confirm_delete: <?php echo json_encode(t('confirm_delete')); ?>,
    
    // Errors
    verification_failed: <?php echo json_encode(t('verification_failed')); ?>,
    error_occurred: <?php echo json_encode(t('error_occurred')); ?>
};

// Alias for shorter access
var t = translations;
