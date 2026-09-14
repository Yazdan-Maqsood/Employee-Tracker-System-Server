<?php
/**
 * Rate Limiter with platform-specific limits
 */

function rateLimit($userId, $endpoint, $maxRequests = 2, $windowSeconds = 60, $platform = 'web') {
    $cacheDir = __DIR__ . '/../cache/rate_limits/';
    
    if (!file_exists($cacheDir)) {
        if (!mkdir($cacheDir, 0755, true)) {
            error_log("Rate limiter: Failed to create cache directory");
            return true;
        }
    }
    
    // ✅ Platform-specific limits
    $platformLimits = [
        'web' => [
            '/attendance/get_status' => ['max' => 4, 'window' => 60],
            '/attendance/track_location' => ['max' => 2, 'window' => 60],
        ],
        'mobile' => [
            '/attendance/get_status' => ['max' => 8, 'window' => 60],
            '/attendance/track_location' => ['max' => 4, 'window' => 60],
        ],
    ];
    
    // Use platform-specific limits if available
    if (isset($platformLimits[$platform][$endpoint])) {
        $maxRequests = $platformLimits[$platform][$endpoint]['max'];
        $windowSeconds = $platformLimits[$platform][$endpoint]['window'];
    }
    
    $safeEndpoint = str_replace(['/', '\\', '?', '&', '=', ' '], '_', $endpoint);
    $cacheFile = $cacheDir . 'rate_' . $userId . '_' . $safeEndpoint . '.json';
    
    $requests = [];
    if (file_exists($cacheFile)) {
        $content = @file_get_contents($cacheFile);
        if ($content !== false) {
            $requests = json_decode($content, true) ?: [];
        }
    }
    
    $now = time();
    $requests = array_filter($requests, function($timestamp) use ($now, $windowSeconds) {
        return ($now - $timestamp) < $windowSeconds;
    });
    
    if (count($requests) >= $maxRequests) {
        error_log("Rate limit exceeded: User $userId on $endpoint ($platform) - " . count($requests) . " requests in {$windowSeconds}s");
        return false;
    }
    
    $requests[] = $now;
    $requests = array_values($requests);
    if (count($requests) > $maxRequests * 2) {
        $requests = array_slice($requests, -$maxRequests);
    }
    
    @file_put_contents($cacheFile, json_encode($requests), LOCK_EX);
    return true;
}

function cleanupRateLimits($maxAgeHours = 24) {
    $cacheDir = __DIR__ . '/../cache/rate_limits/';
    if (!file_exists($cacheDir)) return;
    
    $files = glob($cacheDir . 'rate_*.json');
    $now = time();
    $maxAge = $maxAgeHours * 3600;
    
    foreach ($files as $file) {
        if (filemtime($file) < ($now - $maxAge)) {
            @unlink($file);
        }
    }
}
?>