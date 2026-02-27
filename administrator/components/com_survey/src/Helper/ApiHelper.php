<?php
namespace Kma\Component\Survey\Administrator\Helper;
defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;

class ApiHelper
{
    private const BASE_URL = 'http://42.112.213.87/hvmmapi/api';
    private const API_USER = 'actvn';
    private const API_PASS = 'actvn@a123';

    private static $bearerToken = null;

    /**
     * Decode JWT token payload (without signature verification)
     * 
     * @param string $jwt JWT token
     * 
     * @return array|false Decoded payload or false on failure
     * @since 1.0.0
     */
    private static function decodeJwtPayload($jwt)
    {
        $parts = explode('.', $jwt);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        // Decode the payload (second part)
        $payload = $parts[1];
        
        // Add padding if needed for base64 decoding
        $payload = str_pad($payload, ceil(strlen($payload) / 4) * 4, '=');
        
        $decoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $payload));
        
        if ($decoded === false) {
            return false;
        }
        
        return json_decode($decoded, true);
    }
    
    /**
     * Check if token is expired
     * 
     * @param string $jwt JWT token
     * 
     * @return bool True if expired, false otherwise
     * @since 1.0.0
     */
    private static function isTokenExpired(string $jwt): bool
    {
        $payload = self::decodeJwtPayload($jwt);
        
        if (!$payload || !isset($payload['exp'])) {
            return true;
        }
        
        return time() >= $payload['exp'];
    }
    
    /**
     * Get Bearer token for API access
     * 
     * @return string|false Bearer token or false on failure
     * @since 1.0.0
     */
    private static function getBearerToken(): bool|string
    {
        // Check if we have a cached token and it's not expired
        if (self::$bearerToken !== null && !self::isTokenExpired(self::$bearerToken)) {
            return self::$bearerToken;
        }
        
        try {
            // Create HTTP client
            $http = HttpFactory::getHttp();
            
            // Prepare token request URL
            $tokenUrl = self::BASE_URL . '/CTT_Token/LayChiTiet';
            $tokenUrl .= '?strUser=' . urlencode(self::API_USER);
            $tokenUrl .= '&strPass=' . urlencode(self::API_PASS);
            
            // Make GET request for token
            $response = $http->get($tokenUrl);
            
            if ($response->code === 200) {
                $responseData = json_decode($response->body, true);
                
                // Handle the specific API response structure
                if (isset($responseData['Success']) && $responseData['Success'] === true) {
                    if (!empty($responseData['Data'])) {
                        // The token is in the 'Data' field (JWT format)
                        self::$bearerToken = $responseData['Data'];
                        
                        // Log token info for debugging
                        $payload = self::decodeJwtPayload(self::$bearerToken);
                        if ($payload) {
                            $expiry = isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : 'Unknown';
                            Log::add('Bearer token obtained. Expires: ' . $expiry, Log::INFO, 'api');
                        }
                        
                        return self::$bearerToken;
                    }
                } else {
                    // Log the error message if available
                    $errorMessage = $responseData['Message'] ?? 'Unknown error';
                    Log::add('API token request failed: ' . $errorMessage, Log::ERROR, 'api');
                }
            }
            
            Log::add('Failed to get Bearer token. HTTP Code: ' . $response->code, Log::ERROR, 'api');
            return false;
            
        } catch (Exception $e) {
            Log::add('Error getting Bearer token: ' . $e->getMessage(), Log::ERROR, 'api');
            return false;
        }
    }
    
    /**
     * Make authenticated API request
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array $data Request data for POST requests
     * 
     * @return array|false Response data or false on failure
     * @since 1.0.0
     */
    private static function makeAuthenticatedRequest($endpoint, $method = 'POST', $data = []): bool|array
    {
        $token = self::getBearerToken();
        
        if (!$token) {
            return false;
        }
        
        try {
            $http = HttpFactory::getHttp();
            
            // Prepare headers
            $headers = [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];
            
            $url = self::BASE_URL . $endpoint;
            
            // Make request based on method
            if (strtoupper($method) === 'POST') {
                $response = $http->post($url, json_encode($data), $headers);
            } else {
                $response = $http->get($url, $headers);
            }
            
            if ($response->code === 200) {
                $responseData = json_decode($response->body, true);
                
                // Handle the API's standard response format
                if (isset($responseData['Success']) && $responseData['Success'] === true) {
                    // Return the actual data from the 'Data' field
                    return isset($responseData['Data']) ? $responseData['Data'] : $responseData;
                } else {
                    // Log API error message
                    $errorMessage = $responseData['Message'] ?? 'API request unsuccessful';
                    Log::add('API request failed: ' . $errorMessage, Log::ERROR, 'api');
                    return false;
                }
            }
            
            Log::add('API request failed. HTTP Code: ' . $response->code . ' Response: ' . $response->body, Log::ERROR, 'api');
            return false;
            
        } catch (Exception $e) {
            Log::add('Error making API request: ' . $e->getMessage(), Log::ERROR, 'api');
            return false;
        }
    }
    
    /**
     * Get list of teachers
     * 
     * @return array|false Teacher data or false on failure
     * @since 1.0.0
     */
    public static function getTeachers(): bool|array
    {
        return self::makeAuthenticatedRequest('/ThongTin/getTeachers');
    }
    

    /**
     * Get students by class ID
     * 
     * @param string $classId Class ID
     * 
     * @return array|false Student data or false on failure
     * @since 1.0.0
     */
    public static function getStudentsByClassId($classId): bool|array
    {
        $endpoint = '/ThongTin/getStudentByClassId?classId=' . urlencode($classId);
        return self::makeAuthenticatedRequest($endpoint);
    }
    
    /**
     * Get schedules for a semester
     * 
     * @param string $schoolYear School year (e.g., '2022_2023')
     * @param int $semester Semester number
     * 
     * @return array|false Schedule data or false on failure
     * @since 1.0.0
     */
    public static function getSchedules($schoolYear = '2022_2023', $semester = 1): bool|array
    {
        $endpoint = '/ThongTin/getSchedules?schoolyear=' . $schoolYear . '&semester=' . $semester;
        return self::makeAuthenticatedRequest($endpoint);
    }
    
    /**
     * Get students by student class ID
     * 
     * @param string $studentClass Student class ID (e.g., 'CHAT9')
     * 
     * @return array|false Student data or false on failure
     * @since 1.0.0
     */
    public static function getStudentsByStudentClassId($studentClass): bool|array
    {
        $endpoint = '/ThongTin/getStudentByStudentClassId?studentClass=' . urlencode($studentClass);
        return self::makeAuthenticatedRequest($endpoint);
    }
    
    /**
     * Get list of student classes (administrative classes)
     * 
     * @return array|false Student class data or false on failure
     * @since 1.0.0
     */
    public static function getStudentClasses(): bool|array
    {
        return self::makeAuthenticatedRequest('/ThongTin/getStudentClass');
    }
    

    public static function getClasses(): bool|array
    {
        return self::makeAuthenticatedRequest('/ThongTin/getClasses');
    }
}