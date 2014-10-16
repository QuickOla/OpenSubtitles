<?php
    class logger {
        private static $file = "/app.log";

        public static function error($string){
            self::write($string,0);
        }
        
        public static function warning($string){
            self::write($string,1);
        }
        
        public static function info($string){
            self::write($string,2);
        }
        
        public static function debug($string){
            self::write($string,3);
        }

        private static function write($string, $type){
            // 0 = ERROR, 1 = WARNING, 2 = INFO, 3 = DEBUG
            $config = parse_ini_file(__DIR__ . '/config.ini');

            if ( !isset($config["error_level"]) || empty($config["error_level"]) ){
                return;
            }
            
            // rename log if new date
            if ( file_exists( __DIR__  . logger::$file ) ){
                $mtime = filemtime(__DIR__  . logger::$file);
                if ( date("Ymd", $mtime) != date("Ymd", time() ) ){
                    @rename( __DIR__  . logger::$file, __DIR__  . "/app." . date("Ymd", $mtime) . ".log" );
                }
            }

            if ( $type <= $config["error_level"] && $type >= 0 ){

                $levels = array("[ERROR]","[WARNING]", "[INFO]", "[DEBUG]");
                $string = date("H:i:s d.m.Y", time()) . chr(9) . $levels[$type] . chr(9). $string . chr(13) . chr(10);

                file_put_contents(__DIR__  . logger::$file, $string, FILE_APPEND);
            }
        }

    }
?>
