<?php
$remoteUrl = "https://copiaecola.net/mkmsgversion";
$localFile = __DIR__ . "/mkmsgversion";
$cacheFile = __DIR__ . "/.version_cache";
$cacheTime = 2 * 60 * 60; // 2 horas (em segundos)

/**
 * Lê versão local ou remota
 */
function readVersion($source)
{
    if (filter_var($source, FILTER_VALIDATE_URL)) {
        $ch = curl_init($source);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result ? trim($result) : null;
    }

    return file_exists($source) ? trim(file_get_contents($source)) : null;
}

/**
 * Obtém versão remota com cache
 */
function getCachedRemoteVersion($url, $cacheFile, $cacheTime)
{
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if (
            isset($cache["version"], $cache["time"]) &&
            time() - $cache["time"] < $cacheTime
        ) {
            return $cache["version"];
        }
    }

    $version = readVersion($url);

    if ($version) {
        file_put_contents(
            $cacheFile,
            json_encode([
                "version" => $version,
                "time" => time(),
            ])
        );
    }

    return $version;
}

$localVersion = readVersion($localFile);
$remoteVersion = getCachedRemoteVersion($remoteUrl, $cacheFile, $cacheTime);

$text = "Versão indisponível";
$style = "color: #6c757d";

$repoUrl = "https://github.com/usuariomega/mkmsg";

if (
    $localVersion &&
    $remoteVersion &&
    version_compare($remoteVersion, $localVersion, ">")
) {
    $text =
        'Versão: <span style="color:red;">' . htmlspecialchars($localVersion) .
        "</span>" . ' (Nova: <a href="' . $repoUrl .
        '" style="color:green;" target="_blank" rel="noopener noreferrer">' .
        htmlspecialchars($remoteVersion) . "</a>)";

} elseif ($localVersion) {
    $text = "v{$localVersion}";
}
?>
