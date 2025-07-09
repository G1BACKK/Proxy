<?php
/* PROXY SERVER SECTION */
if (isset($_GET['proxy'])) {
    // Set headers and enable error reporting
    header('Content-Type: text/html');
    header('X-Proxy-Server: PHP-Proxy/1.0');
    
    if (isset($_SERVER['HTTP_REFERER'])) {
        header("Access-Control-Allow-Origin: ".$_SERVER['HTTP_REFERER']);
    }
    
    // Debug mode - uncomment next 2 lines for detailed errors
    // ini_set('display_errors', 1);
    // error_reporting(E_ALL);

    $url = isset($_GET['url']) ? urldecode($_GET['url']) : '';
    
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        header("HTTP/1.1 400 Bad Request");
        die(json_encode([
            'error' => 'Invalid URL',
            'url' => htmlspecialchars($url),
            'timestamp' => time()
        ]));
    }

    try {
        // Enhanced request headers
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        ];

        $options = [
            'http' => [
                'method' => "GET",
                'header' => implode("\r\n", $headers),
                'timeout' => 15,  // 15 second timeout
                'ignore_errors' => true  // Get content even if HTTP status code is bad
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];

        $context = stream_context_create($options);
        $content = file_get_contents($url, false, $context);
        
        if ($content === FALSE) {
            $error = error_get_last();
            throw new Exception($error['message'] ?? "Unknown error fetching URL");
        }

        // Get HTTP response code
        $http_response_header = $http_response_header ?? [];
        $status_line = $http_response_header[0] ?? '';
        preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
        $status_code = $match[1] ?? 200;

        if ($status_code >= 400) {
            throw new Exception("Remote server returned HTTP $status_code");
        }

        // Content modifications
        $replacements = [
            '/window\.top\.location/' => 'window.location',
            '/parent\.location/' => 'window.location',
            '/top\.location/' => 'window.location',
            '/X-Frame-Options: [^\r\n]+/i' => '',
            '/<meta[^>]+http-equiv=["\']X-Frame-Options["\'][^>]*>/i' => ''
        ];

        $content = preg_replace(array_keys($replacements), array_values($replacements), $content);

        // Remove Content-Security-Policy headers
        $content = preg_replace('/Content-Security-Policy: [^\r\n]+/i', '', $content);

        echo $content;
        exit();
        
    } catch (Exception $e) {
        header("HTTP/1.1 500 Server Error");
        die(json_encode([
            'error' => 'Proxy error',
            'message' => $e->getMessage(),
            'timestamp' => time()
        ]));
    }
}
?>
