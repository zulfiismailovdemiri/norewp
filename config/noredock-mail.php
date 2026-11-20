<?php
/**
 * Plugin Name: noredock Mail
 * Description: Sends all WordPress e-mails to the Mailpit service started by noredock (SMTP_HOST / SMTP_PORT).
 * Version: 1.0
 */

if (!getenv('SMTP_HOST')) {
    return;
}

add_action('phpmailer_init', function ($mailer) {
    $mailer->isSMTP();
    $mailer->Host = getenv('SMTP_HOST');
    $mailer->Port = (int) getenv('SMTP_PORT');
    $mailer->SMTPAuth = false;
    $mailer->SMTPAutoTLS = false;
});

// WordPress would use wordpress@localhost, which mailers reject.
add_filter('wp_mail_from', fn ($from) => getenv('MAIL_FROM') ?: $from);
