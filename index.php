<?php
/** Code by Apostolis Karamichalis. */
$directory=__DIR__; $wpLoad='';
for($level=0;$level<10;$level++){ $candidate=$directory.'/wp-load.php'; if(is_readable($candidate)){ $wpLoad=$candidate; break; } $parent=dirname($directory); if($parent===$directory) break; $directory=$parent; }
if($wpLoad===''){ $directory=dirname(__DIR__,5); $candidate=$directory.'/wp-load.php'; if(is_readable($candidate)) $wpLoad=$candidate; }
if($wpLoad===''){ http_response_code(500); exit('WordPress unavailable'); }
require_once $wpLoad;
if(!class_exists('TCP_Plugin')){ http_response_code(500); exit('TapCard unavailable'); }
TCP_Plugin::instance()->render_user_app();
