<?php
/**
 * Same-origin weather proxy for the dashboard greeting pill.
 *
 * The visitor's browser cannot reliably reach third-party forecast/geocode hosts
 * (CORS, network policy, and a Permissions-Policy that disables browser GPS), but
 * this server can. The pill therefore makes a single same-origin request here and
 * we resolve location + conditions upstream. Read-only: no database, no session,
 * no authentication, no writes. Only fixed upstream URLs are ever requested; the
 * client may supply numeric lat/lon (from GPS) which is range-validated, so there
 * is no SSRF surface.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

const USM_WX_UA = 'USMSmart/1.0 https://usm.my admin@usm.my';
const USM_WX_FALLBACK = array('lat' => 5.3565, 'lon' => 100.3025, 'place' => 'Kampus USM');

function wx_http(string $url): ?array {
    $ctx = stream_context_create(array('http' => array(
        'timeout' => 7,
        'follow_location' => 1,
        'max_redirects' => 4,
        'ignore_errors' => true,
        'header' => 'User-Agent: ' . USM_WX_UA . "\r\nAccept: application/json\r\n",
    )));
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
        return null;
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : null;
}

function wx_num($value): ?float {
    return is_numeric($value) ? (float)$value : null;
}

function wx_place(float $lat, float $lon): string {
    $geo = wx_http('https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=' . $lat . '&longitude=' . $lon . '&localityLanguage=ms');
    if ($geo) {
        foreach (array('city', 'locality', 'principalSubdivision') as $key) {
            if (!empty($geo[$key]) && is_string($geo[$key])) {
                return $geo[$key];
            }
        }
    }
    return 'Lokasi anda';
}

/** Open-Meteo (primary): WMO code + metric units already match the pill contract. */
function wx_open_meteo(float $lat, float $lon): ?array {
    $url = 'https://api.open-meteo.com/v1/forecast?latitude=' . $lat . '&longitude=' . $lon
        . '&current=temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m&timezone=auto';
    $j = wx_http($url);
    $c = isset($j['current']) && is_array($j['current']) ? $j['current'] : null;
    if (!$c || !isset($c['temperature_2m'])) {
        return null;
    }
    return array(
        'temp' => (int)round((float)$c['temperature_2m']),
        'feels' => (int)round((float)($c['apparent_temperature'] ?? $c['temperature_2m'])),
        'humidity' => (int)round((float)($c['relative_humidity_2m'] ?? 0)),
        'wind' => (int)round((float)($c['wind_speed_10m'] ?? 0)),
        'code' => (int)($c['weather_code'] ?? 3),
        'time' => isset($c['time']) ? (string)$c['time'] : null,
    );
}

/** met.no (fallback): map its symbol_code onto the same WMO-style codes. */
function wx_metno_code(string $symbol): int {
    $symbol = preg_replace('/_(day|night|polartwilight)$/', '', $symbol) ?? $symbol;
    $map = array(
        'clearsky' => 0, 'fair' => 1, 'partlycloudy' => 2, 'cloudy' => 3, 'fog' => 45,
        'drizzle' => 51, 'lightrain' => 61, 'rain' => 63, 'heavyrain' => 65,
        'lightrainshowers' => 80, 'rainshowers' => 80, 'heavyrainshowers' => 81,
        'lightsleet' => 66, 'sleet' => 66, 'heavysleet' => 67,
        'lightsnow' => 71, 'snow' => 73, 'heavysnow' => 75,
        'lightsnowshowers' => 85, 'snowshowers' => 85, 'heavysnowshowers' => 86,
    );
    if (strpos($symbol, 'thunder') !== false) {
        return 95;
    }
    return $map[$symbol] ?? 3;
}

function wx_metno(float $lat, float $lon): ?array {
    $url = 'https://api.met.no/weatherapi/locationforecast/2.0/compact?lat=' . $lat . '&lon=' . $lon;
    $j = wx_http($url);
    $series = $j['properties']['timeseries'] ?? null;
    if (!is_array($series) || empty($series[0]['data'])) {
        return null;
    }
    $data = $series[0]['data'];
    $instant = $data['instant']['details'] ?? array();
    if (!isset($instant['air_temperature'])) {
        return null;
    }
    $symbol = '';
    foreach (array('next_1_hours', 'next_6_hours', 'next_12_hours') as $window) {
        if (!empty($data[$window]['summary']['symbol_code'])) {
            $symbol = (string)$data[$window]['summary']['symbol_code'];
            break;
        }
    }
    $temp = (float)$instant['air_temperature'];
    return array(
        'temp' => (int)round($temp),
        'feels' => (int)round($temp),
        'humidity' => (int)round((float)($instant['relative_humidity'] ?? 0)),
        'wind' => (int)round((float)($instant['wind_speed'] ?? 0) * 3.6),
        'code' => wx_metno_code($symbol),
        'time' => isset($series[0]['time']) ? (string)$series[0]['time'] : null,
    );
}

// 1) Coordinates: trust validated client GPS, else geolocate the server egress IP
//    (on the main web the visitor is on this machine), else campus fallback.
$lat = isset($_GET['lat']) ? wx_num($_GET['lat']) : null;
$lon = isset($_GET['lon']) ? wx_num($_GET['lon']) : null;
$place = null;
$source = 'gps';

if ($lat === null || $lon === null || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    $lat = null;
    $lon = null;
    $source = 'ip';
    $ipGeo = wx_http('https://ipwho.is/');
    if ($ipGeo && ($ipGeo['success'] ?? true) !== false
        && isset($ipGeo['latitude'], $ipGeo['longitude'])
        && is_numeric($ipGeo['latitude']) && is_numeric($ipGeo['longitude'])) {
        $lat = (float)$ipGeo['latitude'];
        $lon = (float)$ipGeo['longitude'];
        foreach (array('city', 'region', 'country') as $key) {
            if (!empty($ipGeo[$key]) && is_string($ipGeo[$key])) {
                $place = $ipGeo[$key];
                break;
            }
        }
    }
}

if ($lat === null || $lon === null) {
    $lat = USM_WX_FALLBACK['lat'];
    $lon = USM_WX_FALLBACK['lon'];
    $place = USM_WX_FALLBACK['place'];
    $source = 'campus';
}

if ($place === null || $place === '') {
    $place = wx_place($lat, $lon);
}

// 2) Forecast: Open-Meteo, then met.no. If both fail, report an error so the pill
//    can reuse a stale cache or hide rather than show wrong data.
$data = wx_open_meteo($lat, $lon);
$provider = 'open-meteo';
if ($data === null) {
    $data = wx_metno($lat, $lon);
    $provider = 'met.no';
}
if ($data === null) {
    echo json_encode(array('error' => 'weather_unavailable'));
    exit;
}

echo json_encode(array(
    'lat' => $lat,
    'lon' => $lon,
    'place' => $place,
    'source' => $source,
    'provider' => $provider,
    'data' => $data,
), JSON_UNESCAPED_UNICODE);