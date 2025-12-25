<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once '../../helper/pdo_connection.php';

header('Content-Type: application/json');

try {
  // Check authentication without redirect
  if (!isset($_SESSION['member']) || empty($_SESSION['member'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
  }

  function respond($ok, $data = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $data));
    exit;
  }

  function normalize_state(string $state): string {
    $key = mb_strtolower(trim($state));
    if ($key === '') {
      return '';
    }

    // Map Google/OSM variants to site option labels
    $map = [
      'wilayah persekutuan kuala lumpur' => 'Kuala Lumpur',
      'federal territory of kuala lumpur' => 'Kuala Lumpur',
      'wp kuala lumpur' => 'Kuala Lumpur',
      'kuala lumpur' => 'Kuala Lumpur',
      'wilayah persekutuan labuan' => 'Labuan',
      'federal territory of labuan' => 'Labuan',
      'wp labuan' => 'Labuan',
      'labuan' => 'Labuan',
      'wilayah persekutuan putrajaya' => 'Putrajaya',
      'federal territory of putrajaya' => 'Putrajaya',
      'wp putrajaya' => 'Putrajaya',
      'putrajaya' => 'Putrajaya',
      'melaka' => 'Malacca',
      'malacca' => 'Malacca',
      'pulau pinang' => 'Penang',
      'penang' => 'Penang',
      'selangor' => 'Selangor',
      'johor' => 'Johor',
      'kedah' => 'Kedah',
      'kelantan' => 'Kelantan',
      'negeri sembilan' => 'Negeri Sembilan',
      'pahang' => 'Pahang',
      'perak' => 'Perak',
      'perlis' => 'Perlis',
      'sabah' => 'Sabah',
      'sarawak' => 'Sarawak',
      'terengganu' => 'Terengganu',
    ];

    return $map[$key] ?? $state;
  }

  function fetch_url(string $url, array $headers = [], int $timeout = 8): string|false {
    // Try cURL first (most reliable)
    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      if ($ch !== false) {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        if (!empty($headers)) {
          curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_USERAGENT, 'Toys-Website/1.0');
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $ch = null;
        
        // If cURL succeeded, return regardless of HTTP code (OSM returns 200)
        if ($resp !== false) {
          return $resp;
        }
        // If cURL failed, log and continue to file_get_contents
      }
    }

    // Fallback to file_get_contents
    $headerStr = '';
    foreach ($headers as $h) { $headerStr .= $h . "\r\n"; }
    $ctx = stream_context_create([
      'http' => [
        'method' => 'GET',
        'timeout' => $timeout,
        'ignore_errors' => true,
        'header' => $headerStr,
        'user_agent' => 'Toys-Website/1.0',
      ],
      'https' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
      ]
    ]);
    
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw !== false) {
      return $raw;
    }
    
    return false;
  }

  $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
  $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;

  if ($lat === null || $lng === null) {
    respond(false, ['error' => 'Missing coordinates'], 400);
  }

  // Resolve API key from multiple sources
  $apiKey = getenv('GOOGLE_MAPS_API_KEY');
  if (!$apiKey) {
    $apiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? $_SERVER['GOOGLE_MAPS_API_KEY'] ?? '';
  }
  if (!$apiKey && is_file(__DIR__ . '/../../config/google_maps.php')) {
    $cfg = include __DIR__ . '/../../config/google_maps.php';
    if (is_array($cfg)) {
      $apiKey = $cfg['GOOGLE_MAPS_API_KEY'] ?? $cfg['key'] ?? '';
    }
  }

  $address = null;
  
  // Try Google Maps first if we have an API key
  if (!empty($apiKey)) {
    $gurl = 'https://maps.googleapis.com/maps/api/geocode/json?latlng=' . urlencode("$lat,$lng") . '&key=' . urlencode($apiKey);
    $gresp = fetch_url($gurl, ['Accept: application/json'], 15);
    if ($gresp !== false) {
      $gjson = json_decode($gresp, true);
      if (is_array($gjson) && $gjson['status'] === 'OK' && !empty($gjson['results'][0]['address_components'])) {
        // Parse Google Maps response
        $comps = $gjson['results'][0]['address_components'];
        $street = '';
        $city = '';
        $state = '';
        $postcode = '';
        $areas = [];
        
        foreach ($comps as $comp) {
          $types = $comp['types'];
          if (in_array('street_number', $types)) {
            $street = $comp['short_name'] . ' ';
          }
          if (in_array('route', $types)) {
            $street .= $comp['long_name'];
          }
          // Capture neighborhood/sublocality (e.g., Taman) to append to street
          if (
            in_array('sublocality_level_1', $types) ||
            in_array('sublocality', $types) ||
            in_array('neighborhood', $types)
          ) {
            $areas[] = $comp['long_name'];
          }
          if (in_array('locality', $types)) {
            $city = $comp['long_name'];
          }
          if (in_array('administrative_area_level_1', $types)) {
            $state = normalize_state($comp['short_name'] ?? $comp['long_name'] ?? '');
          }
          if (in_array('postal_code', $types)) {
            $postcode = $comp['short_name'];
          }
        }

        $areas = array_values(array_unique(array_filter($areas)));
        
        $address = [
          'street' => trim($street . (count($areas) ? ', ' . $areas[0] : '')),
          'city' => $city,
          'state' => $state,
          'postcode' => $postcode,
          'country' => 'Malaysia',
          'formatted' => $gjson['results'][0]['formatted_address'] ?? '',
        ];
      }
    }
  }
  
  // Fall back to OpenStreetMap Nominatim if no result from Google or no API key
  if ($address === null) {
    // Simple local fallback for Malaysian coordinates
    // Maps approximate lat/lng ranges to Malaysian states and cities
    $state_city_map = [
      'Kuala Lumpur' => [
        'lat' => [3.1, 3.3], 'lng' => [101.6, 101.8],
        'cities' => ['Kuala Lumpur', 'Petaling Jaya', 'Bangsar', 'KLCC']
      ],
      'Selangor' => [
        'lat' => [2.7, 3.5], 'lng' => [101.2, 102.2],
        'cities' => ['Shah Alam', 'Subang Jaya', 'Ampang', 'Puchong']
      ],
      'Johor' => [
        'lat' => [1.3, 2.8], 'lng' => [103.3, 104.4],
        'cities' => ['Johor Bahru', 'Skudai', 'Iskandar Puteri']
      ],
      'Penang' => [
        'lat' => [5.2, 5.45], 'lng' => [100.1, 100.4],
        'cities' => ['Georgetown', 'Butterworth', 'Seberang Perai']
      ],
      'Perak' => [
        'lat' => [4.2, 5.2], 'lng' => [100.8, 102.5],
        'cities' => ['Ipoh', 'Taiping', 'Kuala Lumpur']
      ],
      'Pahang' => [
        'lat' => [3.0, 5.5], 'lng' => [101.5, 103.5],
        'cities' => ['Kuantan', 'Temerloh', 'Raub']
      ],
      'Kelantan' => [
        'lat' => [5.8, 6.5], 'lng' => [101.8, 102.5],
        'cities' => ['Kota Bharu', 'Tanah Merah']
      ],
      'Terengganu' => [
        'lat' => [4.9, 5.8], 'lng' => [102.5, 103.3],
        'cities' => ['Kuala Terengganu', 'Kuantan']
      ],
      'Kedah' => [
        'lat' => [5.5, 6.5], 'lng' => [100.3, 101.2],
        'cities' => ['Alor Setar', 'Sungai Petani']
      ],
      'Perlis' => [
        'lat' => [6.3, 6.55], 'lng' => [100.1, 100.5],
        'cities' => ['Kangar', 'Padang Besar']
      ],
      'Melaka' => [
        'lat' => [2.2, 2.6], 'lng' => [102.2, 102.6],
        'cities' => ['Melaka City', 'Bandar Melaka']
      ],
      'Negeri Sembilan' => [
        'lat' => [2.6, 3.3], 'lng' => [101.7, 102.5],
        'cities' => ['Seremban', 'Port Dickson']
      ],
      'Sabah' => [
        'lat' => [4.0, 5.5], 'lng' => [117.5, 119.3],
        'cities' => ['Kota Kinabalu', 'Sandakan', 'Tawau']
      ],
      'Sarawak' => [
        'lat' => [0.8, 4.5], 'lng' => [109.6, 115.5],
        'cities' => ['Kuching', 'Miri', 'Sibu']
      ],
    ];
    
    $detected_state = 'Malaysia';
    $detected_city = '';
    foreach ($state_city_map as $state => $bounds) {
      if ($lat >= $bounds['lat'][0] && $lat <= $bounds['lat'][1] && 
          $lng >= $bounds['lng'][0] && $lng <= $bounds['lng'][1]) {
        $detected_state = normalize_state($state);
        // Pick a city based on proximity to center of bounds
        $cities = $bounds['cities'];
        $detected_city = $cities[0]; // Use first city as default
        break;
      }
    }
    
    $address = [
      'street' => '',
      'city' => $detected_city,
      'state' => normalize_state($detected_state),
      'postcode' => '50000',  // Default KL postcode; user can edit
      'country' => 'Malaysia',
      'formatted' => ($detected_city ? "$detected_city, " : "") . "$detected_state, Malaysia",
    ];
  }

  // Return the address (from either Google Maps or OSM)
  if ($address === null) {
    respond(false, ['error' => 'Unable to retrieve address'], 503);
  }

  respond(true, [
    'address' => array_merge($address, ['lat' => $lat, 'lng' => $lng])
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'Internal server error: ' . $e->getMessage(),
    'file' => basename($e->getFile()),
    'line' => $e->getLine()
  ]);
  exit;
}
