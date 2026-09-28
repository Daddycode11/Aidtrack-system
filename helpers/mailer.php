<?php
require_once __DIR__ . '/../config.php';

function send_verification_email(mysqli $mysqli, int $userId, string $email, string $name): bool
{
    if (!defined('MAIL_USER') || !defined('MAIL_APP_PASS') || !defined('APP_URL')
        || MAIL_USER === '' || MAIL_APP_PASS === '' || APP_URL === '') {
        error_log('Verification email is not configured.');
        return false;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');

    $stmt = $mysqli->prepare("UPDATE users
        SET verification_token_hash = ?, verification_expires = ?
        WHERE id = ? AND email = ? AND role IN ('admin', 'super_admin')");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssis', $tokenHash, $expires, $userId, $email);
    $updated = $stmt->execute() && $stmt->affected_rows === 1;
    $stmt->close();
    if (!$updated) {
        return false;
    }

    try {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException('Composer dependencies are not installed.');
        }
        require_once $autoload;
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            throw new RuntimeException('PHPMailer is not installed.');
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USER;
        $mail->Password = MAIL_APP_PASS;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_USER, 'AIDTRACK');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $link = rtrim(APP_URL, '/') . '/verify.php?token=' . rawurlencode($token);
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $mail->Subject = 'Verify your AIDTRACK admin email';
        $mail->Body = '<p>Hello ' . $safeName . ',</p>'
            . '<p>Verify your email address to activate admin sign-in:</p>'
            . '<p><a href="' . $safeLink . '">Verify email address</a></p>'
            . '<p>This link expires in 24 hours. If you did not expect this email, you can ignore it.</p>';
        $mail->AltBody = "Hello {$name},\n\nVerify your email address using this link (expires in 24 hours):\n{$link}";
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Could not send AIDTRACK verification email: ' . $e->getMessage());
        $clear = $mysqli->prepare('UPDATE users SET verification_token_hash = NULL, verification_expires = NULL WHERE id = ? AND verification_token_hash = ?');
        if ($clear) {
            $clear->bind_param('is', $userId, $tokenHash);
            $clear->execute();
            $clear->close();
        }
        return false;
    }
}