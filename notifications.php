<?php
// --- Option 1: Email (using basic mail() function - Requires server setup) ---
// Note: mail() is often unreliable. Using PHPMailer with SMTP is recommended for production.
function sendConfirmationEmail($to, $subject, $message) {
    $headers = 'From: webmaster@example.com' . "\r\n" . // Replace with your sending address
               'Reply-To: webmaster@example.com' . "\r\n" .
               'X-Mailer: PHP/' . phpversion();
    // Basic mail sending. Returns true on success (queued), false on failure.
    // Doesn't guarantee delivery.
    // return mail($to, $subject, $message, $headers);

    // Placeholder: return true for testing without actual sending
     error_log("Simulating Email Send: To=$to, Subject=$subject"); // Log for debugging
     return true;
}

// --- Option 2: WhatsApp Click-to-Chat Link Generation ---
// This DOES NOT send automatically. It creates a link for the user (organizer) to click.
function generateWhatsAppLink($number, $message) {
    // Remove non-numeric characters from number (except +)
    $number = preg_replace('/[^\d+]/', '', $number);
    // Ensure number starts with country code, no leading 00 or + if already there
    // Basic cleanup - might need refinement based on your number formats
    if (substr($number, 0, 1) !== '+') {
       // Add your default country code if missing (e.g., assuming Indian numbers)
       if (strlen($number) == 10) {
           $number = '+91' . $number;
       } else {
           // Handle other cases or consider it invalid
       }
    }
    $number = ltrim($number, '+'); // Remove leading + for the wa.me link format

    $encodedMessage = urlencode($message);
    return "https://wa.me/" . $number . "?text=" . $encodedMessage;
}


// --- Option 3: SMS (Requires an SMS Gateway API like Twilio, Vonage etc.) ---
// This is a placeholder structure. You need to install the provider's SDK (e.g., via Composer)
// and replace with actual API calls.
/*
use Twilio\Rest\Client; // Example using Twilio

function sendSmsMessage($to, $message) {
    $sid = 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'; // Your Twilio Account SID
    $token = 'your_auth_token';           // Your Twilio Auth Token
    $twilio_number = "+15017122661";      // Your Twilio phone number

    // Ensure number format is E.164 (e.g., +14155552671)
    if (substr($to, 0, 1) !== '+') {
        // Add default country code if needed
        $to = '+91' . ltrim($to, '0'); // Example for India
    }

    try {
        $client = new Client($sid, $token);
        $client->messages->create(
            $to,
            [
                'from' => $twilio_number,
                'body' => $message
            ]
        );
        return true; // Message sent successfully
    } catch (Exception $e) {
        error_log('SMS Error: ' . $e->getMessage()); // Log the error
        return false; // Failed to send
    }
}
*/
/**
 * Sends a simple message using the Telegram Bot API via file_get_contents.
 * Requires 'allow_url_fopen = On' in php.ini (usually default in XAMPP).
 *
 * @param string $botToken Your Telegram Bot API Token.
 * @param string $chatId The target chat ID (your personal ID in this case).
 * @param string $messageText The plain text message content.
 * @return bool True on success (message sent), false on failure.
 */

function sendTelegramNotification($botToken, $chatId, $messageText) {
    // Basic URL encoding for the message text
    $encodedMessage = urlencode($messageText);
    // Construct URL with parameters
    //$apiUrl = "https://api.telegram.org/bot" . $botToken . "/sendMessage?chat_id=" . $chatId . "&text=" . $encodedMessage;

    //$apiUrl = "https://api.telegram.org/bot7617286814:AAFEZbrU5SEcDHYPtXBjlMz62o6qMfW7Uyc/sendMessage?chat_id=984369519&text=MurtazaGandhiSaysSalam"; //working
$apiUrl = "https://api.telegram.org/bot7617286814:AAFEZbrU5SEcDHYPtXBjlMz62o6qMfW7Uyc/sendMessage?chat_id=" . $chatId . "&text=".$encodedMessage;


    error_log("Telegram cURL: Attempting to call URL: " . $apiUrl); // Log URL


    


    $ch = curl_init(); // Initialize cURL session

    if ($ch === false) {
         error_log("Telegram cURL Error: Failed to initialize cURL session.");
         return false;
    }

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $apiUrl);             // Set the URL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);    // Return the response as a string instead of outputting it
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);             // Set timeout in seconds (adjust as needed)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);    // Verify the peer's SSL certificate
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);       // Verify the common name and that it matches the hostname provided
    // Explicitly set the CA bundle path (redundant if php.ini is correct, but good for debugging)
     $caPath = 'M:/XamppProg/php/extras/ssl/cacert.pem'; // Make sure this path is correct M:\XamppProg\php\extras\ssl
     if (file_exists($caPath)) {
          curl_setopt($ch, CURLOPT_CAINFO, $caPath);
          error_log("Telegram cURL: Using CAINFO path: " . $caPath);
     } else {
          error_log("Telegram cURL Warning: CAINFO file not found at: " . $caPath . " - relying on system certs.");
     }


    // Execute the cURL session
    $resultJson = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code
    $curlErrorNum = curl_errno($ch); // Get cURL error number (0 if no error)
    $curlErrorMsg = curl_error($ch); // Get cURL error message

    curl_close($ch); // Close cURL session

    // Check for cURL errors first
    if ($curlErrorNum > 0) {
        error_log("Telegram cURL Error (" . $curlErrorNum . "): " . $curlErrorMsg);
        return false;
    }

    // Check HTTP status code and response content
    error_log("Telegram cURL: HTTP Status Code: " . $httpCode);
    error_log("Telegram cURL: Raw Response: " . $resultJson);

    if ($httpCode == 200 && $resultJson) {
        $result = json_decode($resultJson, true);
        if (isset($result['ok']) && $result['ok'] === true) {
            error_log("Telegram cURL Success sending to " . $chatId);
            return true; // Success
        } else {
            $errorDescription = $result['description'] ?? 'Unknown API error in response';
            error_log("Telegram cURL API Error sending to " . $chatId . ": " . $errorDescription);
            return false; // API reported an error
        }
    } else {
        error_log("Telegram cURL Error: HTTP Code was " . $httpCode . " or empty response.");
        return false; // Request failed at HTTP level
    }
}
?>