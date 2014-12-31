<?php
error_reporting(0);

include_once("logger.php");
$config = parse_ini_file(__DIR__ . '/config.ini');


// Hande filesize larger than 2GB on windows
function filesize64($file)
{
    static $iswin;
    if (!isset($iswin)) {
        $iswin = (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN');
    }

    static $exec_works;
    if (!isset($exec_works)) {
        $exec_works = (function_exists('exec') && !ini_get('safe_mode') && @exec('echo EXEC') == 'EXEC');
    }

    // try a shell command
    if ($exec_works) {
        $cmd = ($iswin) ? "for %F in (\"$file\") do @echo %~zF" : "stat -c%s \"$file\"";
        @exec($cmd, $output);
        if (is_array($output) && ctype_digit($size = trim(implode("\n", $output)))) {
            return $size;
        }
    }

    // try the Windows COM interface
    if ($iswin && class_exists("COM")) {
        try {
            $fsobj = new COM('Scripting.FileSystemObject');
            $f = $fsobj->GetFile( realpath($file) );
            $size = $f->Size;
        } catch (Exception $e) {
            $size = null;
        }
        if (ctype_digit($size)) {
            return $size;
        }
    }

    // if all else fails
    return filesize($file);
}



// PHP version less than 5.4 DIE 
$e = "";
if (!defined('PHP_VERSION_ID')) {
    $version = explode('.', PHP_VERSION);
    define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
}

if (PHP_VERSION_ID < 50400) {
    $e = sprintf("PHP version 5.4 or higher is required, you are running version: %s", phpversion());
}


if ( !extension_loaded("xmlrpc") ){
    $e = sprintf("extension: %s is required, please enable in your php.ini file.", "xmlrpc");
}

if ( !empty($e)){
    echo $e;
    logger::error($e);
    return 1;
}




//c:\php54\php.exe -q c:\torrenthandler\console.php "%D" "%N" "%L" "%K" "%F" "%S"
//Input: "D:\ServerFolders\Torrents\!.Movies\7500.2014.BRRip.XViD-juggs[ETRG]" "7500.2014.BRRip.XViD-juggs[ETRG]" "!.Movies" "multi" "7500-ETRG.nfo"


logger::debug(sprintf( "handling file %s", $argv[1] ) );



$file = $argv[1];

if (empty($file)) {
    $e = 'error! you must supply a file';
} 

if (!is_file($file)) {
    $e = sprintf( "error! file %s doesnt exist", $file );
}
                                                                             

if ( !empty($e)){
    echo $e;
    logger::error($e);
    return 1;
}

require_once 'SubtitlesManager.php';


// fetch subtitles
$languages = explode(";", $config['lang']);
if ( is_array($languages) )
{
    for( $i = 0; $i < count($languages); $i++ )
    {
        $subtitleFile = preg_replace("/\\.[^.\\s]{3,4}$/", "", $file) . "." . $languages[$i] . '.srt';
        if ( !file_exists($subtitleFile))
        {
            $manager = new OpenSubtitles\SubtitlesManager($config['username'], $config['password'], $languages[$i]);
            $sub = $manager->getSubtitleUrls($file);
            if (!empty($sub) && !empty($sub[0])) {
                $langfile = $manager->downloadSubtitle($sub[0], $file, $languages[$i]);
                logger::info(sprintf("subtitlefile %s downloaded", $langfile ) );
                
            } else {
                logger::info(sprintf("couldnt find subtitle (%s) for torrent:%s", $languages[$i], $argv[2] ));
            }
        }
    }    
}




                  