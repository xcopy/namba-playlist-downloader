<?php

function write($text, $foreground = null)
{
    $foreground_colors = [
        'black'        => '0;30',
        'dark_gray'    => '1;30',
        'blue'         => '0;34',
        'light_blue'   => '1;34',
        'green'        => '0;32',
        'light_green'  => '1;32',
        'cyan'         => '0;36',
        'light_cyan'   => '1;36',
        'red'          => '0;31',
        'light_red'    => '1;31',
        'purple'       => '0;35',
        'light_purple' => '1;35',
        'brown'        => '0;33',
        'yellow'       => '1;33',
        'light_gray'   => '0;37',
        'white'        => '1;37',
    ];

    if ($foreground !== null) {
        $text = "\033[".$foreground_colors[$foreground]."m".$text."\033[0m";
    }

    fwrite(STDOUT, $text.PHP_EOL);
}

function request($url)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    if (! curl_errno($ch)) {
        if (curl_getinfo($ch)['http_code']) {
            throw new \Exception('Playlist not found');
        }
    }

    return curl_exec($ch);
}

$error_handler = function (Exception $e)
{
    $message = vsprintf('%s [%s]: %s [%d]', [
        get_class($e),
        $e->getCode(),
        strip_tags($e->getMessage()),
        $e->getLine()
    ]);

    write($message, 'red');
};

set_time_limit(0);

# set exception/error handler
set_exception_handler($error_handler);
set_error_handler($error_handler);

# PHP 5.x only
if (PHP_MAJOR_VERSION < 5) {
    throw new \Exception('This script works only in PHP version 5.x');
}

# define playlist ID
$playlist_id = (int) $argv[1];

# validate it
if ($playlist_id <= 0) {
    throw new \Exception('Incorrect playlist ID');
}

$url = sprintf('http://music.namba.kg/popup_player.php?playlist=%d', $playlist_id);

if ($html = request($url)) {
    preg_match("@<h3><a[^>]+>(.+)</a></h3>@is", $html, $title);
    preg_match("@flashVars = (.+);@", $html, $data);

    $directory = trim($title[1]);
    $json = json_decode($data[1]);

    // create destination directory
    if (!is_dir($directory)) {
        mkdir($directory);
        write("Directory \"$directory\" created", 'green');
    }

    foreach ($json->playlist as $track) {
        if ($html = request($track->url)) {
            preg_match('@<p>Для скачивания файла перейдите по ссылке:</p>\s+<a href="(.+?)"[^>]+>.+?</a>@is', $html, $match);

            if (!empty($match)) {
                write("Downloading \"{$track->file_name}\"", 'brown');
                $cmd = sprintf('wget -O "%s" "%s"', addslashes($directory.'/'.trim($track->file_name)), $match[1]);

                // download track
                shell_exec($cmd);

                write('Done!', 'green');
            }
        }
    }
}
