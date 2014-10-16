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


logger::debug(sprintf( "handling dir:%s torrent:%s label:%s", $argv[1], $argv[2], $argv[3] ) );




// Check if folder exists
if ( !is_dir($argv[1]) ){
    $e = sprintf( "error! folder %s doesnt exist", $argv[1] );
    echo $e;
    logger::warning($e);
    return 1;
}



$extensions = array("txt", "nfo", "mkv", "avi", "mp4" );
$file = "";
// Loop trough all files in folder
$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($argv[1]), RecursiveIteratorIterator::SELF_FIRST);
foreach($objects as $name => $object){
    $size = filesize64($name);
    $ext = $object->getExtension();
    
    logger::debug(sprintf( "file:%s size:%s ext:%s", $name, $size, $ext ) );
    
    if ( $config["delete_extras"] == true && !$object->isDir() ){
        if ( $size < 70000000 && in_array($ext, $extensions) ){
            logger::debug(sprintf("delete file:%s", $name));
            @unlink($name);
            if ( file_exists($name)){
                logger::error(sprintf("couldnt delete file:%s",$name));
            }
        }
    }
    
    // Guess this is the file to check for subtitles
    if ( $size > 70000000 && empty($file) ){
        logger::debug(sprintf("finding subtitles for file:%s", $name));
        $file = $name;
    }
}



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



$manager = new OpenSubtitles\SubtitlesManager($config['username'], $config['password'], $config['lang']);
$sub = $manager->getSubtitleUrls($file);
if (!empty($sub) && !empty($sub[0])) {
    $file = $manager->downloadSubtitle($sub[0], $file);
    logger::info(sprintf("subtitlefile %s downloaded", $file ) );
} else {
    logger::info(sprintf("couldnt find subtitles for torrent:%s", $argv[2] ));
}




// Handle folder and move to correct place based on settings
$output_dirs = $config["output_dir"];

if ( !isset($output_dirs) || !is_array($output_dirs) ){
    logger::info( sprintf("no output dirs defined for label: %s", $argv[3] ) );
}else{
    if ( !array_key_exists($argv[3], $output_dirs )){
        logger::info( sprintf("no output dirs defined for label: %s", $argv[3] ) );
    }else{
        $new_folder = str_replace( "\\", "/", $output_dirs[$argv[3]] . "\\" . $argv[2] );
        logger::debug( sprintf("output dir for label: %s defined as: %s", $argv[3], $new_folder ) );
        
        logger::debug(sprintf("moving folder:%s to %s", $argv[1],$new_folder ));
        if ( !rename(str_replace("\\", "/", $argv[1]), $new_folder) ){
            $e = sprintf("could not move folder:%s to %s", $argv[1],$new_folder );
            echo $e;
            logger::error($e);
            return 1;
        }
    }          
}
