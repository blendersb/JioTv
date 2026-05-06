<?php
header("Content-Type: text/plain");

// Load config
require_once 'jitendraunatti.php';

// ----------------------
// Helper: Safe fetch
// ----------------------
function fetchContent($url) {
    $context = stream_context_create([
        "http" => [
            "timeout" => 5,
            "header" => "User-Agent: Mozilla/5.0\r\n"
        ]
    ]);
    return @file_get_contents($url, false, $context);
}

// ----------------------
// Base URL
// ----------------------
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$directory = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$baseUrl = $protocol . "://" . $host . $directory . "/";

// ----------------------
// Load Data
// ----------------------
$jioResponse = fetchContent($SCARLET_WITCH['api_endpoint']['live_channels']);
$jioData = json_decode($jioResponse, true);
$jioChannels = $jioData['result'] ?? [];

$zeeResponse = fetchContent($SCARLET_WITCH['zee_api']['web_api']);
$zeeChannels = json_decode($zeeResponse, true) ?? [];

$categories = $SCARLET_WITCH['channelCategoryMapping'] ?? [];
$languages = $SCARLET_WITCH['languageIdMapping'] ?? [];

$zeeLangMap = [
    "hi" => "Hindi", "en" => "English", "mr" => "Marathi",
    "ta" => "Tamil", "te" => "Telugu", "kn" => "Kannada",
    "ml" => "Malayalam", "bn" => "Bengali", "gu" => "Gujarati",
    "pa" => "Punjabi", "or" => "Odia", "bh" => "Bhojpuri", "ur" => "Urdu"
];

// ----------------------
// Output Header
// ----------------------
echo '#EXTM3U x-tvg-url="https://tsepg.cf/epg.xml.gz"' . PHP_EOL;

// Track duplicates
$seen = [];

// ----------------------
// Jio Channels
// ----------------------
foreach ($jioChannels as $channel) {
    $id = $channel['channel_id'] ?? '';
    $name = $channel['channel_name'] ?? 'Unknown';
    $logo = "https://jiotvimages.cdn.jio.com/dare_images/images/" . ($channel['logoUrl'] ?? '');
    $catId = $channel['channelCategoryId'] ?? '';
    $langId = $channel['channelLanguageId'] ?? '';

    $group = ($categories[$catId] ?? 'General') . " (JioTV)";
    $lang = $languages[$langId] ?? 'Hindi';
    if ($lang !== "Bengali") continue;
    $playbackUrl = $baseUrl . "live.m3u8?id=" . $id . "&token=" . $SCARLET_WITCH['JITENDRA_UNIVERSE']['token'];

    if (!in_array($playbackUrl, $seen)) {
        $seen[] = $playbackUrl;

        printf(
            '#EXTINF:-1 tvg-id="%s" tvg-logo="%s" group-title="%s" tvg-language="%s", %s' . PHP_EOL,
            $id, $logo, $group, $lang, $name
        );
        echo $playbackUrl . PHP_EOL;
    }
}

// ----------------------
// Addon Playlists
// ----------------------
foreach ($SCARLET_WITCH['addon_service'] as $addon) {
    $content = fetchContent($addon);

    if ($content) {
        $lines = explode("\n", $content);

        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);

            if ($line === "#EXTM3U") continue;

            if (strpos($line, "#EXTINF") === 0) {
                echo $line . PHP_EOL;

                if (isset($lines[$i + 1])) {
                    $url = trim($lines[$i + 1]);

                    if (!in_array($url, $seen)) {
                        $seen[] = $url;
                        echo $url . PHP_EOL;
                    }
                    $i++;
                }
            }
        }
    }
}

// ----------------------
// IPTV-ORG Bengali Playlist
// ----------------------
$iptvOrgUrl = "https://iptv-org.github.io/iptv/languages/ben.m3u";
$iptvContent = fetchContent($iptvOrgUrl);

if ($iptvContent) {
    $lines = explode("\n", $iptvContent);

    for ($i = 0; $i < count($lines); $i++) {
        $line = trim($lines[$i]);

        if ($line === "#EXTM3U") continue;

        if (strpos($line, "#EXTINF") === 0) {

            // Tag group
            $line = str_replace(
                'group-title="',
                'group-title="Bengali (IPTV-ORG) | ',
                $line
            );

            echo $line . PHP_EOL;

            if (isset($lines[$i + 1])) {
                $url = trim($lines[$i + 1]);

                if (!in_array($url, $seen)) {
                    $seen[] = $url;
                    echo $url . PHP_EOL;
                }
                $i++;
            }
        }
    }
}

// ----------------------
// Zee Channels
// ----------------------
foreach ($zeeChannels as $zee) {
    $name = $zee['name'] ?? 'Zee Channel';
    $logo = $zee['logo'] ?? '';
    $group = ($zee['genres'] ?? 'Entertainment') . " (Zee5)";
    $langCode = $zee['languages'] ?? 'hi';
    $lang = $zeeLangMap[$langCode] ?? "Hindi";

    $rawLink = $zee['link'] ?? '';
    $playbackUrl = $baseUrl . "live.m3u8?id=" . $rawLink . "&token=" . $SCARLET_WITCH['JITENDRA_UNIVERSE']['token'];

    if (!in_array($playbackUrl, $seen)) {
        $seen[] = $playbackUrl;

        printf(
            '#EXTINF:-1 tvg-id="%s" tvg-logo="%s" group-title="%s" tvg-language="%s", %s' . PHP_EOL,
            $zee['id'] ?? '',
            $logo,
            $group,
            $lang,
            $name
        );
        echo $playbackUrl . PHP_EOL;
    }
}
?>
