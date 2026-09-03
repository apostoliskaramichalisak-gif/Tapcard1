<?php
/** Code by Apostolis Karamichalis — TapCard Pro v1.5.9. */
$directory=__DIR__; $wpLoad='';
for($level=0;$level<10;$level++){ $candidate=$directory.'/wp-load.php'; if(is_readable($candidate)){ $wpLoad=$candidate; break; } $parent=dirname($directory); if($parent===$directory) break; $directory=$parent; }
if($wpLoad===''){ $directory=dirname(__DIR__,5); $candidate=$directory.'/wp-load.php'; if(is_readable($candidate)) $wpLoad=$candidate; }
if($wpLoad===''){http_response_code(500);exit;}
require_once $wpLoad;
$base=plugins_url('settings-pwa/', dirname(__FILE__).'/../tapcard-pro.php'); $base=rtrim($base,'/').'/'; $start=$base.'index.php';
$name='TapCard Settings'; if(is_user_logged_in()){ $u=wp_get_current_user(); if($u&&$u->display_name) $name='TapCard — '.$u->display_name; }
$icon192=plugins_url('assets/icon-192.png',dirname(__FILE__).'/../tapcard-pro.php'); $icon512=plugins_url('assets/icon-512.png',dirname(__FILE__).'/../tapcard-pro.php');
header('Content-Type: application/manifest+json; charset=utf-8'); header('Cache-Control:no-store,max-age=0');
echo wp_json_encode(['id'=>$base,'name'=>$name,'short_name'=>'TapCard','description'=>'TapCard profile management application','start_url'=>$start,'scope'=>$base,'display'=>'standalone','display_override'=>['standalone','minimal-ui'],'prefer_related_applications'=>false,'orientation'=>'portrait-primary','background_color'=>'#f8fafc','theme_color'=>'#111827','icons'=>[['src'=>$icon192,'sizes'=>'192x192','type'=>'image/png','purpose'=>'any maskable'],['src'=>$icon512,'sizes'=>'512x512','type'=>'image/png','purpose'=>'any maskable']]],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
