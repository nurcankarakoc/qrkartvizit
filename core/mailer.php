<?php
// core/mailer.php
require_once __DIR__ . '/db.php';

/**
 * Zerosoft Mailer - Handles email notifications.
 * For production, use PHPMailer or a professional mail service.
 * This is a flexible wrapper that logs to file as fallback.
 */
function qrk_send_email(string $to, string $subject, string $message_html): bool
{
    $from = env_value('MAIL_FROM', 'noreply@zerosoft.com.tr');
    $from_name = env_value('MAIL_FROM_NAME', 'Zerosoft QR');
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . $from_name . ' <' . $from . '>',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // In a real environment, you'd use mail() or an SMTP library here.
    // For now, let's log the email to assets/logs/emails.log so we can verify it.
    
    $log_dir = __DIR__ . '/../assets/logs';
    if (!is_dir($log_dir) && !mkdir($log_dir, 0755, true) && !is_dir($log_dir)) {
        error_log('qrk_mailer: Log dizini oluşturulamadı: ' . $log_dir);
    }

    $log_entry  = "--- EMAIL AT " . date('Y-m-d H:i:s') . " ---\n";
    $log_entry .= "To: $to\n";
    $log_entry .= "Subject: $subject\n";
    $log_entry .= "Body:\n$message_html\n";
    $log_entry .= "------------------------------------------\n\n";

    if (is_dir($log_dir)) {
        file_put_contents($log_dir . '/emails.log', $log_entry, FILE_APPEND | LOCK_EX);
    }

    // Gerçek gönderim — hata durumunda error_log'a yaz
    try {
        $result = mail($to, $subject, $message_html, implode("\r\n", $headers));
        if (!$result) {
            error_log('qrk_mailer: mail() başarısız — alıcı: ' . $to . ' | konu: ' . $subject);
        }
        return $result;
    } catch (Throwable $e) {
        error_log('qrk_mailer exception: ' . $e->getMessage() . ' — alıcı: ' . $to);
        return false;
    }
}

function qrk_send_welcome_email(string $to, string $name): bool
{
    $subject = "Hoş Geldiniz! Hesabınız Başarıyla Oluşturuldu";
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;'>
        <div style='background: #0A2F2F; padding: 2rem; text-align: center;'>
            <h1 style='color: #A6803F; margin: 0;'>Zerosoft QR</h1>
        </div>
        <div style='padding: 2rem;'>
            <h2 style='color: #0A2F2F;'>Merhaba $name,</h2>
            <p>Zerosoft QR Kartvizit platformuna hoş geldiniz! Hesabınız başarıyla oluşturulmuştur.</p>
            <p>Artık panelinize giriş yaparak dijital profilinizi düzenleyebilir ve siparişinizin durumunu anlık olarak takip edebilirsiniz.</p>
            <div style='text-align: center; margin: 2rem 0;'>
                <a href='" . env_value('APP_URL') . "/auth/login.php' style='background: #A6803F; color: white; padding: 1rem 2rem; border-radius: 8px; text-decoration: none; font-weight: bold;'>Hemen Giriş Yap</a>
            </div>
            <p>Herhangi bir sorunuz olursa destek ekibimize ulaşmaktan çekinmeyin.</p>
            <p>Keyifli kullanımlar dileriz!</p>
        </div>
        <div style='background: #f8fafc; padding: 1rem; text-align: center; color: #64748b; font-size: 0.8rem;'>
            © " . date('Y') . " Zerosoft Teknoloji. Tüm hakları saklıdır.
        </div>
    </div>";
    
    return qrk_send_email($to, $subject, $body);
}

function qrk_send_password_reset_email(string $to, string $token): bool
{
    $reset_url = env_value('APP_URL') . "/auth/reset-password.php?token=" . $token;
    $subject = "Şifre Sıfırlama Talebi";
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;'>
        <div style='background: #0A2F2F; padding: 2rem; text-align: center;'>
            <h1 style='color: #A6803F; margin: 0;'>Zerosoft QR</h1>
        </div>
        <div style='padding: 2rem;'>
            <h2 style='color: #0A2F2F;'>Şifre Sıfırlama</h2>
            <p>Hesabınız için şifre sıfırlama talebinde bulundunuz. Aşağıdaki butona tıklayarak yeni şifrenizi belirleyebilirsiniz:</p>
            <div style='text-align: center; margin: 2rem 0;'>
                <a href='$reset_url' style='background: #A6803F; color: white; padding: 1rem 2rem; border-radius: 8px; text-decoration: none; font-weight: bold;'>Şifremi Sıfırla</a>
            </div>
            <p>Bu bağlantı 1 saat boyunca geçerlidir. Eğer bu talebi siz yapmadıysanız, lütfen bu e-postayı dikkate almayın.</p>
        </div>
        <div style='background: #f8fafc; padding: 1rem; text-align: center; color: #64748b; font-size: 0.8rem;'>
            © " . date('Y') . " Zerosoft Teknoloji. Tüm hakları saklıdır.
        </div>
    </div>";
    
    return qrk_send_email($to, $subject, $body);
}
