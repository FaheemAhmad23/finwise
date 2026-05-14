<?php
/**
 * MFA Tools - Email Engine
 * 
 * Pure PHP SMTP client with encryption, template rendering,
 * routing rules, and logging. No external dependencies.
 */

// Encryption key derived from DB_PASS
define('EMAIL_ENCRYPTION_KEY', hash('sha256', DB_PASS . '_MFA_EMAIL_ENC_KEY_2026', true));
define('EMAIL_ENCRYPTION_METHOD', 'aes-256-cbc');

/**
 * Encrypt a password for storage
 */
function encryptEmailPassword($plaintext) {
    if (empty($plaintext)) return '';
    $ivLength = openssl_cipher_iv_length(EMAIL_ENCRYPTION_METHOD);
    $iv = openssl_random_pseudo_bytes($ivLength);
    $encrypted = openssl_encrypt($plaintext, EMAIL_ENCRYPTION_METHOD, EMAIL_ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

/**
 * Decrypt a stored password
 */
function decryptEmailPassword($ciphertext) {
    if (empty($ciphertext)) return '';
    $data = base64_decode($ciphertext);
    if ($data === false) return '';
    $parts = explode('::', $data, 2);
    if (count($parts) !== 2) return '';
    $iv = $parts[0];
    $encrypted = $parts[1];
    $decrypted = openssl_decrypt($encrypted, EMAIL_ENCRYPTION_METHOD, EMAIL_ENCRYPTION_KEY, 0, $iv);
    return $decrypted !== false ? $decrypted : '';
}

/**
 * Get an email account by ID
 */
function getEmailAccount($accountId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM email_accounts WHERE id = ?");
        $stmt->execute([$accountId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get all active email accounts
 */
function getActiveEmailAccounts() {
    try {
        $pdo = getDB();
        return $pdo->query("SELECT * FROM email_accounts WHERE is_active = 1 ORDER BY label ASC")->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Resolve routing rule: feature_key → email_account
 * Returns the account row or null
 */
function resolveEmailRoute($featureKey) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT r.*, a.* FROM email_routing_rules r 
                               LEFT JOIN email_accounts a ON r.email_account_id = a.id
                               WHERE r.feature_key = ? AND r.is_enabled = 1");
        $stmt->execute([$featureKey]);
        $row = $stmt->fetch();
        if ($row && !empty($row['email_account_id']) && $row['is_active']) {
            return $row;
        }
        // Fallback: if routing rule exists but no account assigned, use first active account
        if ($row) {
            $fallback = $pdo->query("SELECT * FROM email_accounts WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch();
            if ($fallback) {
                error_log("Email routing fallback: feature '$featureKey' has no assigned account, using account #{$fallback['id']} ({$fallback['email_address']})");
                $fallback['email_account_id'] = $fallback['id'];
                return $fallback;
            }
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Log an email to email_logs
 */
function logEmail($featureKey, $accountId, $from, $to, $subject, $status, $error = null, $response = null, $htmlBody = null, $textBody = null, $cc = null, $bcc = null) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO email_logs (feature_key, email_account_id, from_email, to_email, subject, status, error_message, provider_response, html_body, text_body, cc, bcc) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$featureKey, $accountId, $from, $to, $subject, $status, $error, $response, $htmlBody, $textBody, $cc, $bcc]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Email log error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Render an email template with variable replacement
 * Returns ['subject' => ..., 'html' => ..., 'text' => ...]
 */
function renderEmailTemplate($templateKey, $vars = []) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE template_key = ? AND is_active = 1");
        $stmt->execute([$templateKey]);
        $tpl = $stmt->fetch();
        if (!$tpl) return null;
        
        // Add default vars
        $vars['site_name'] = $vars['site_name'] ?? SITE_NAME;
        $vars['created_at'] = $vars['created_at'] ?? date('Y-m-d H:i:s');
        $vars['year'] = $vars['year'] ?? date('Y');
        $vars['site_url'] = $vars['site_url'] ?? getSiteBaseUrl();
        $vars['logo_url'] = $vars['logo_url'] ?? (getSiteBaseUrl() . '/assets/images/MFA%20logo%20PNG.PNG');
        // WhatsApp number
        if (!isset($vars['whatsapp_number'])) {
            $waNum = function_exists('getWhatsAppNumber') ? getWhatsAppNumber() : '+447490916612';
            $vars['whatsapp_number'] = $waNum;
            $vars['whatsapp_number_clean'] = preg_replace('/[^0-9]/', '', $waNum);
        } else {
            $vars['whatsapp_number_clean'] = $vars['whatsapp_number_clean'] ?? preg_replace('/[^0-9]/', '', $vars['whatsapp_number']);
        }
        // Support email
        $vars['support_email'] = $vars['support_email'] ?? (function_exists('getSetting') ? getSetting('contact_email', 'support@mfatools.net') : 'support@mfatools.net');
        // Date
        $vars['date'] = $vars['date'] ?? date('M d, Y');
        // WhatsApp prefill message (default generic)
        if (!isset($vars['whatsapp_prefill_message'])) {
            $vars['whatsapp_prefill_message'] = rawurlencode('Hi MFA Tools, I need assistance.');
        }
        
        $subject = $tpl['subject'];
        $html = $tpl['html_body'];
        $text = $tpl['text_body'] ?? '';
        
        // Replace all {var} placeholders
        foreach ($vars as $key => $value) {
            $subject = str_replace('{' . $key . '}', $value ?? '', $subject);
            $html = str_replace('{' . $key . '}', $value ?? '', $html);
            $text = str_replace('{' . $key . '}', $value ?? '', $text);
        }
        
        // Remove any remaining unreplaced variables
        $subject = preg_replace('/\{[a-zA-Z_]+\}/', '', $subject);
        $html = preg_replace('/\{[a-zA-Z_]+\}/', '', $html);
        $text = preg_replace('/\{[a-zA-Z_]+\}/', '', $text);
        
        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    } catch (Exception $e) {
        error_log("Template render error: " . $e->getMessage());
        return null;
    }
}

/**
 * Send email using a specific email account's SMTP credentials
 * 
 * @param int $accountId Email account ID
 * @param string|array $to Recipient(s)
 * @param string $subject Email subject
 * @param string $htmlBody HTML body
 * @param string $textBody Plain text body (optional)
 * @param string $featureKey Feature key for logging
 * @param string $cc CC recipients (optional)
 * @param string $bcc BCC recipients (optional)
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmailViaAccount($accountId, $to, $subject, $htmlBody, $textBody = '', $featureKey = 'manual', $cc = '', $bcc = '', $attachments = []) {
    $account = getEmailAccount($accountId);
    if (!$account) {
        $err = 'Email account not found (ID: ' . $accountId . ')';
        logEmail($featureKey, $accountId, '', is_array($to) ? implode(',', $to) : $to, $subject, 'failed', $err);
        return ['success' => false, 'message' => $err];
    }
    
    if (!$account['is_active']) {
        $err = 'Email account is disabled: ' . $account['email_address'];
        logEmail($featureKey, $accountId, $account['email_address'], is_array($to) ? implode(',', $to) : $to, $subject, 'failed', $err);
        return ['success' => false, 'message' => $err];
    }
    
    $smtpPassword = decryptEmailPassword($account['smtp_password']);
    $toStr = is_array($to) ? implode(',', $to) : $to;
    
    try {
        $result = smtpSend(
            $account['smtp_host'],
            $account['smtp_port'],
            $account['smtp_encryption'],
            $account['smtp_username'],
            $smtpPassword,
            $account['smtp_timeout'],
            $account['from_name'],
            $account['email_address'],
            $account['reply_to'],
            $toStr,
            $cc,
            $bcc,
            $subject,
            $htmlBody,
            $textBody,
            $attachments
        );
        
        logEmail($featureKey, $accountId, $account['email_address'], $toStr, $subject, 'sent', null, $result, $htmlBody, $textBody, $cc, $bcc);
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        $err = $e->getMessage();
        logEmail($featureKey, $accountId, $account['email_address'], $toStr, $subject, 'failed', $err, null, $htmlBody, $textBody, $cc, $bcc);
        return ['success' => false, 'message' => $err];
    }
}

/**
 * Send email using a routing rule (feature key)
 */
function sendEmailByFeature($featureKey, $to, $subject, $htmlBody, $textBody = '', $cc = '', $bcc = '', $attachments = []) {
    $route = resolveEmailRoute($featureKey);
    if (!$route) {
        $err = "No active routing rule or email account for feature: $featureKey";
        logEmail($featureKey, null, '', is_array($to) ? implode(',', $to) : $to, $subject, 'failed', $err);
        return ['success' => false, 'message' => $err];
    }
    
    return sendEmailViaAccount($route['email_account_id'], $to, $subject, $htmlBody, $textBody, $featureKey, $cc, $bcc, $attachments);
}

/**
 * Send email using a template + routing rule
 */
function sendTemplateEmail($featureKey, $templateKey, $to, $vars = [], $cc = '', $bcc = '', $attachments = []) {
    $rendered = renderEmailTemplate($templateKey, $vars);
    if (!$rendered) {
        $err = "Template not found or inactive: $templateKey";
        logEmail($featureKey, null, '', is_array($to) ? implode(',', $to) : $to, '', 'failed', $err);
        return ['success' => false, 'message' => $err];
    }
    
    return sendEmailByFeature($featureKey, $to, $rendered['subject'], $rendered['html'], $rendered['text'], $cc, $bcc, $attachments);
}

/**
 * Pure PHP SMTP send function
 * Supports PLAIN, LOGIN, and CRAM-MD5 authentication
 * Supports TLS/SSL encryption
 */
function smtpSend($host, $port, $encryption, $username, $password, $timeout, $fromName, $fromEmail, $replyTo, $to, $cc, $bcc, $subject, $htmlBody, $textBody = '', $attachments = []) {
    $log = [];
    $socket = null;
    
    try {
        // Build connection string
        $socketHost = $host;
        if ($encryption === 'ssl') {
            $socketHost = 'ssl://' . $host;
        }
        
        // Connect
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);
        
        $socket = stream_socket_client(
            $socketHost . ':' . $port,
            $errno, $errstr, $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$socket) {
            throw new Exception("Could not connect to SMTP server: $errstr ($errno)");
        }
        
        stream_set_timeout($socket, $timeout);
        
        // Read greeting
        $response = smtpReadResponse($socket);
        $log[] = "S: $response";
        if (substr($response, 0, 3) !== '220') {
            throw new Exception("Unexpected greeting: $response");
        }
        
        // EHLO
        smtpCommand($socket, "EHLO " . gethostname(), $log);
        
        // STARTTLS if needed
        if ($encryption === 'tls') {
            smtpCommand($socket, "STARTTLS", $log, '220');
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                throw new Exception("Failed to enable TLS encryption");
            }
            // Re-EHLO after STARTTLS
            smtpCommand($socket, "EHLO " . gethostname(), $log);
        }
        
        // AUTH LOGIN
        smtpCommand($socket, "AUTH LOGIN", $log, '334');
        smtpCommand($socket, base64_encode($username), $log, '334');
        smtpCommand($socket, base64_encode($password), $log, '235');
        
        // MAIL FROM
        smtpCommand($socket, "MAIL FROM:<$fromEmail>", $log, '250');
        
        // RCPT TO (all recipients)
        $allRecipients = array_filter(array_map('trim', explode(',', $to . ',' . $cc . ',' . $bcc)));
        foreach ($allRecipients as $rcpt) {
            if (!empty($rcpt)) {
                smtpCommand($socket, "RCPT TO:<$rcpt>", $log, '250');
            }
        }
        
        // DATA
        smtpCommand($socket, "DATA", $log, '354');
        
        // Build message
        $boundary = 'MFA_' . md5(uniqid(time()));
        $message = '';
        $message .= "From: " . ($fromName ? "\"$fromName\" <$fromEmail>" : $fromEmail) . "\r\n";
        $message .= "To: $to\r\n";
        if (!empty($cc)) $message .= "Cc: $cc\r\n";
        if (!empty($replyTo)) $message .= "Reply-To: $replyTo\r\n";
        $message .= "Subject: " . smtpEncodeHeader($subject) . "\r\n";
        $message .= "Date: " . date('r') . "\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "X-Mailer: MFA-Tools-Email-Engine/1.0\r\n";
        $message .= "Message-ID: <" . uniqid('mfa_') . "@" . gethostname() . ">\r\n";
        
        if (!empty($attachments)) {
            // multipart/mixed (body + attachments)
            $mixedBoundary = 'MFA_MIXED_' . md5(uniqid(time()));
            $altBoundary = 'MFA_ALT_' . md5(uniqid(time() . 'alt'));
            
            $message .= "Content-Type: multipart/mixed; boundary=\"$mixedBoundary\"\r\n\r\n";
            
            // Body part (multipart/alternative inside mixed)
            $message .= "--$mixedBoundary\r\n";
            if (!empty($textBody) && !empty($htmlBody)) {
                $message .= "Content-Type: multipart/alternative; boundary=\"$altBoundary\"\r\n\r\n";
                $message .= "--$altBoundary\r\n";
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
                $message .= quoted_printable_encode($textBody) . "\r\n";
                $message .= "--$altBoundary\r\n";
                $message .= "Content-Type: text/html; charset=UTF-8\r\n";
                $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
                $message .= quoted_printable_encode($htmlBody) . "\r\n";
                $message .= "--$altBoundary--\r\n";
            } elseif (!empty($htmlBody)) {
                $message .= "Content-Type: text/html; charset=UTF-8\r\n";
                $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
                $message .= quoted_printable_encode($htmlBody) . "\r\n";
            } else {
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
                $message .= quoted_printable_encode($textBody) . "\r\n";
            }
            
            // Attachment parts
            foreach ($attachments as $att) {
                $attFilename = $att['filename'] ?? 'attachment';
                $attMime = $att['mime'] ?? 'application/octet-stream';
                $attContent = $att['content'] ?? '';
                
                $message .= "--$mixedBoundary\r\n";
                $message .= "Content-Type: $attMime; name=\"$attFilename\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n";
                $message .= "Content-Disposition: attachment; filename=\"$attFilename\"\r\n\r\n";
                $message .= chunk_split(base64_encode($attContent)) . "\r\n";
            }
            
            $message .= "--$mixedBoundary--\r\n";
        } elseif (!empty($textBody) && !empty($htmlBody)) {
            // Multipart alternative (no attachments)
            $message .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n";
            $message .= "--$boundary\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $message .= quoted_printable_encode($textBody) . "\r\n";
            $message .= "--$boundary\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $message .= quoted_printable_encode($htmlBody) . "\r\n";
            $message .= "--$boundary--\r\n";
        } elseif (!empty($htmlBody)) {
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $message .= quoted_printable_encode($htmlBody) . "\r\n";
        } else {
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $message .= quoted_printable_encode($textBody) . "\r\n";
        }
        
        // Ensure no bare dots at line start (escape them)
        $message = str_replace("\r\n.\r\n", "\r\n..\r\n", $message);
        
        // Send message
        fwrite($socket, $message);
        
        // End DATA with \r\n.\r\n
        smtpCommand($socket, "\r\n.", $log, '250');
        
        // QUIT
        smtpCommand($socket, "QUIT", $log);
        
        fclose($socket);
        return implode("\n", $log);
        
    } catch (Exception $e) {
        if ($socket) {
            try { fwrite($socket, "QUIT\r\n"); fclose($socket); } catch (Exception $ex) {}
        }
        throw new Exception($e->getMessage() . "\nLog: " . implode("\n", $log));
    }
}

/**
 * Send an SMTP command and read the response
 */
function smtpCommand($socket, $command, &$log, $expectedCode = null) {
    $logCmd = $command;
    // Mask passwords in log
    if (preg_match('/^[A-Za-z0-9+\/=]{10,}$/', $command) && !preg_match('/^(EHLO|HELO|MAIL|RCPT|DATA|QUIT|STARTTLS|AUTH)/i', $command)) {
        $logCmd = '***MASKED***';
    }
    $log[] = "C: $logCmd";
    
    fwrite($socket, $command . "\r\n");
    $response = smtpReadResponse($socket);
    $log[] = "S: $response";
    
    if ($expectedCode !== null && substr($response, 0, strlen($expectedCode)) !== $expectedCode) {
        throw new Exception("SMTP Error: Expected $expectedCode, got: $response");
    }
    
    return $response;
}

/**
 * Read multi-line SMTP response
 */
function smtpReadResponse($socket) {
    $response = '';
    while (true) {
        $line = fgets($socket, 512);
        if ($line === false) break;
        $response .= $line;
        // Multi-line responses have '-' at position 3, last line has space
        if (isset($line[3]) && $line[3] !== '-') break;
    }
    return trim($response);
}

/**
 * Encode a subject header for UTF-8
 */
function smtpEncodeHeader($text) {
    if (preg_match('/[^\x20-\x7E]/', $text)) {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
    return $text;
}

/**
 * Test SMTP connection for an email account
 */
function testSmtpConnection($accountId, $testRecipient = null) {
    $account = getEmailAccount($accountId);
    if (!$account) return ['success' => false, 'message' => 'Account not found'];
    
    $password = decryptEmailPassword($account['smtp_password']);
    
    try {
        if (!empty($testRecipient)) {
            // Send a real test email
            $result = smtpSend(
                $account['smtp_host'],
                $account['smtp_port'],
                $account['smtp_encryption'],
                $account['smtp_username'],
                $password,
                $account['smtp_timeout'],
                $account['from_name'],
                $account['email_address'],
                $account['reply_to'],
                $testRecipient,
                '', '',
                'SMTP Test from ' . SITE_NAME,
                '<div style="font-family:Arial,sans-serif;padding:20px;"><h2 style="color:#ff5f1f;">SMTP Test Successful!</h2><p>This is a test email from <strong>' . SITE_NAME . '</strong> Email Center.</p><p>Account: ' . htmlspecialchars($account['email_address']) . '</p><p>Sent at: ' . date('Y-m-d H:i:s') . '</p></div>',
                'SMTP Test Successful! Account: ' . $account['email_address'] . ' Sent at: ' . date('Y-m-d H:i:s')
            );
            logEmail('smtp_test', $accountId, $account['email_address'], $testRecipient, 'SMTP Test', 'sent', null, $result);
            return ['success' => true, 'message' => 'Test email sent successfully to ' . $testRecipient];
        } else {
            // Just test connection + auth
            $socketHost = $account['smtp_host'];
            if ($account['smtp_encryption'] === 'ssl') {
                $socketHost = 'ssl://' . $socketHost;
            }
            
            $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
            $socket = stream_socket_client($socketHost . ':' . $account['smtp_port'], $errno, $errstr, $account['smtp_timeout'], STREAM_CLIENT_CONNECT, $context);
            
            if (!$socket) throw new Exception("Connection failed: $errstr ($errno)");
            
            stream_set_timeout($socket, $account['smtp_timeout']);
            $greeting = smtpReadResponse($socket);
            
            fwrite($socket, "EHLO " . gethostname() . "\r\n");
            smtpReadResponse($socket);
            
            if ($account['smtp_encryption'] === 'tls') {
                fwrite($socket, "STARTTLS\r\n");
                $resp = smtpReadResponse($socket);
                if (substr($resp, 0, 3) !== '220') throw new Exception("STARTTLS failed: $resp");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fwrite($socket, "EHLO " . gethostname() . "\r\n");
                smtpReadResponse($socket);
            }
            
            fwrite($socket, "AUTH LOGIN\r\n");
            $resp = smtpReadResponse($socket);
            if (substr($resp, 0, 3) !== '334') throw new Exception("AUTH failed: $resp");
            
            fwrite($socket, base64_encode($account['smtp_username']) . "\r\n");
            $resp = smtpReadResponse($socket);
            if (substr($resp, 0, 3) !== '334') throw new Exception("Username rejected: $resp");
            
            fwrite($socket, base64_encode($password) . "\r\n");
            $resp = smtpReadResponse($socket);
            if (substr($resp, 0, 3) !== '235') throw new Exception("Authentication failed: $resp");
            
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            
            return ['success' => true, 'message' => 'SMTP connection and authentication successful'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Test IMAP/POP3 connection
 */
function testIncomingConnection($accountId) {
    $account = getEmailAccount($accountId);
    if (!$account) return ['success' => false, 'message' => 'Account not found'];
    if (!$account['incoming_enabled']) return ['success' => false, 'message' => 'Incoming mail not enabled for this account'];
    
    $password = decryptEmailPassword($account['incoming_password']);
    $host = $account['incoming_host'];
    $port = $account['incoming_port'];
    $type = $account['incoming_type'];
    $encryption = $account['incoming_encryption'];
    
    try {
        $socketHost = $host;
        if ($encryption === 'ssl') {
            $socketHost = 'ssl://' . $host;
        }
        
        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
        $socket = stream_socket_client($socketHost . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        
        if (!$socket) throw new Exception("Connection failed: $errstr ($errno)");
        stream_set_timeout($socket, 15);
        
        $greeting = fgets($socket, 512);
        
        if ($type === 'imap') {
            // IMAP login
            if ($encryption === 'tls') {
                fwrite($socket, "a001 STARTTLS\r\n");
                $resp = fgets($socket, 512);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
            
            $username = $account['incoming_username'];
            fwrite($socket, "a002 LOGIN \"$username\" \"$password\"\r\n");
            $resp = '';
            while (true) {
                $line = fgets($socket, 512);
                if ($line === false) break;
                $resp .= $line;
                if (preg_match('/^a002 /m', $line)) break;
            }
            
            if (strpos($resp, 'a002 OK') === false) {
                throw new Exception("IMAP login failed: " . trim($resp));
            }
            
            fwrite($socket, "a003 LOGOUT\r\n");
            fclose($socket);
            return ['success' => true, 'message' => 'IMAP connection and login successful'];
            
        } else {
            // POP3 login
            if ($encryption === 'tls') {
                fwrite($socket, "STLS\r\n");
                $resp = fgets($socket, 512);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
            
            fwrite($socket, "USER " . $account['incoming_username'] . "\r\n");
            $resp = fgets($socket, 512);
            if (substr(trim($resp), 0, 3) !== '+OK') throw new Exception("POP3 USER failed: " . trim($resp));
            
            fwrite($socket, "PASS $password\r\n");
            $resp = fgets($socket, 512);
            if (substr(trim($resp), 0, 3) !== '+OK') throw new Exception("POP3 authentication failed: " . trim($resp));
            
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            return ['success' => true, 'message' => 'POP3 connection and login successful'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get site base URL
 */
function getSiteBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}
?>
