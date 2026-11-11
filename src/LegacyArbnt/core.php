<?php
declare(strict_types=1);
function arbnt_data_dir(): string {
 $root=defined('ARBNT_PUBLIC_ROOT')?ARBNT_PUBLIC_ROOT:dirname(__DIR__);
 $pointer=$root.'/.arbnt-storage.php';
 $webroot=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));
 $base=$webroot&&($root===$webroot||str_starts_with($root,$webroot.'/'))?$webroot:$root;
 $path=dirname($base).'/arbnt-private';
 if(is_file($pointer)){
  $chosen=require $pointer;
  if(!is_string($chosen)||!in_array($chosen,[$path,$root.'/storage'],true))throw new RuntimeException('Invalid storage pointer');
  $path=$chosen;
 }
 if (!is_dir($path)) throw new RuntimeException('Storage is not initialized');
 return $path;
}
function arbnt_lock($handle,int $mode,int $timeoutMs=2000):void{
 $deadline=hrtime(true)+$timeoutMs*1000000;
 while(!flock($handle,$mode|LOCK_NB)){
  if(hrtime(true)>=$deadline)throw new RuntimeException('Storage lock timeout');
  usleep(1000);
 }
}
function arbnt_storage_guard(int $mode=LOCK_SH){
 $f=(defined('ARBNT_PUBLIC_ROOT')?ARBNT_PUBLIC_ROOT:dirname(__DIR__)).'/.arbnt-migration.lock';
 if(is_link($f)||(file_exists($f)&&!is_file($f)))throw new RuntimeException('Unsafe storage migration lock');
 $h=@fopen($f,'c+b');if(!$h)throw new RuntimeException('Storage migration lock unavailable');
 try{@chmod($f,0600);arbnt_lock($h,$mode);}catch(Throwable $e){fclose($h);throw $e;}
 return $h;
}
function arbnt_storage_release($h):void{flock($h,LOCK_UN);fclose($h);}
function arbnt_storage_status():array{
 $root=defined('ARBNT_PUBLIC_ROOT')?ARBNT_PUBLIC_ROOT:dirname(__DIR__);$current=arbnt_data_dir();$webroot=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));$base=$webroot&&($root===$webroot||str_starts_with($root,$webroot.'/'))?$webroot:$root;$outside=dirname($base).'/arbnt-private';
 return ['mode'=>$current===$outside?'outside':'inside','can_migrate'=>$current!==$outside,'path_label'=>$current===$outside?'خارج مجلد الموقع':'storage داخل الموقع'];
}
function arbnt_storage_migrate():array{
 $guard=arbnt_storage_guard(LOCK_EX);
 try{
  $root=defined('ARBNT_PUBLIC_ROOT')?ARBNT_PUBLIC_ROOT:dirname(__DIR__);$source=arbnt_data_dir();$webroot=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));$base=$webroot&&($root===$webroot||str_starts_with($root,$webroot.'/'))?$webroot:$root;$target=dirname($base).'/arbnt-private';
  if($source===$target)return arbnt_storage_status();
  if($source!==$root.'/storage')throw new RuntimeException('Unsupported storage source');
  if(is_link($target)||is_link($source))throw new RuntimeException('Symlinks not permitted');
  if(is_dir($target)){
   $entries=array_diff(scandir($target)?:[],['.','..']);
   if($entries)throw new RuntimeException('Destination already contains files');
  }elseif(!@mkdir($target,0700,true))throw new RuntimeException('Hosting does not permit outside storage');
  $items=array_diff(scandir($source)?:[],['.','..']);
  foreach($items as $file){
   if(!preg_match('/^(?:[a-z]+\\.json|[a-z]+\\.lock|rate-[a-f0-9]{64}\\.lock|security\\.log|installed\\.lock)$/',$file))continue;
   $src=$source.'/'.$file;$dst=$target.'/'.$file;
   if(!is_file($src)||is_link($src))throw new RuntimeException('Unsafe storage file');
   if(!@copy($src,$dst))throw new RuntimeException('Copy failed');
   @chmod($dst,0600);
   if(hash_file('sha256',$src)!==hash_file('sha256',$dst))throw new RuntimeException('Copy verification failed');
  }
  foreach(['config.json','users.json','installed.lock'] as $required)if(!is_file($target.'/'.$required))throw new RuntimeException('Missing required storage data');
  $pointer=$root.'/.arbnt-storage.php';$tmp=$root.'/.arbnt-storage-'.bin2hex(random_bytes(8)).'.tmp';
  $payload='<?php return '.var_export($target,true).';';
  if(@file_put_contents($tmp,$payload,LOCK_EX)!==strlen($payload))throw new RuntimeException('Cannot write storage pointer');
  @chmod($tmp,0600);
  if(!@rename($tmp,$pointer)){@unlink($tmp);throw new RuntimeException('Cannot activate new storage');}
  return arbnt_storage_status();
 }finally{arbnt_storage_release($guard);}
}
function arbnt_config(): array {
 $f=arbnt_data_dir().'/config.json';
 if(!is_file($f))throw new RuntimeException('Invalid configuration');
 return arbnt_decode_file($f);
}
function arbnt_decode_file(string $file):array{
 if(is_link($file)||(file_exists($file)&&!is_file($file)))throw new RuntimeException('Unsafe storage file');
 if(!file_exists($file))return [];
 $json=@file_get_contents($file);
 if($json===false)throw new RuntimeException('Cannot read storage data');
 $data=json_decode($json,true,64,JSON_THROW_ON_ERROR);
 if(!is_array($data))throw new UnexpectedValueException('Invalid storage data');
 return $data;
}
function arbnt_read(string $name): array {
 if(!preg_match('/^[a-z]+$/',$name))throw new InvalidArgumentException('Invalid collection');
 $guard=arbnt_storage_guard();
 try{
  $f=arbnt_data_dir().'/'.$name.'.json';
  return arbnt_decode_file($f);
 }finally{arbnt_storage_release($guard);}
}
function arbnt_write(string $name, callable $change): array {
 if(!preg_match('/^[a-z]+$/',$name))throw new InvalidArgumentException('Invalid collection');
 $guard=arbnt_storage_guard();
 try{$path=arbnt_data_dir();$lockPath=$path.'/'.$name.'.lock';$dataPath=$path.'/'.$name.'.json';
 if(is_link($lockPath)||(file_exists($lockPath)&&!is_file($lockPath)))throw new RuntimeException('Unsafe storage lock');
 $lock=@fopen($lockPath,'c+b');if(!$lock)throw new RuntimeException('Storage busy');
 try {
  @chmod($lockPath,0600);arbnt_lock($lock,LOCK_EX);
  $old=arbnt_decode_file($dataPath);$new=$change($old);
  if(!is_array($new))throw new RuntimeException('Invalid data');
  $json=json_encode($new,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR,64);
  $tmp=$path.'/.arbnt-'.$name.'-'.bin2hex(random_bytes(12)).'.tmp';
  $stream=@fopen($tmp,'x+b');if(!$stream)throw new RuntimeException('Storage unavailable');
  try {
   if(!@chmod($tmp,0600))throw new RuntimeException('Cannot protect storage data');
   $offset=0;$length=strlen($json);
   while($offset<$length){$written=fwrite($stream,substr($json,$offset));if($written===false||$written===0)throw new RuntimeException('Write failed');$offset+=$written;}
   if(!fflush($stream)||(function_exists('fsync')&&!fsync($stream)))throw new RuntimeException('Write failed');
   if(!rename($tmp,$dataPath))throw new RuntimeException('Commit failed');
  } finally {fclose($stream);if(is_file($tmp))unlink($tmp);}
  return $new;
 }finally{flock($lock,LOCK_UN);fclose($lock);}
 }finally{arbnt_storage_release($guard);}
}
function arbnt_json(int $code,array $payload): never {
 http_response_code($code);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
 echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
}
