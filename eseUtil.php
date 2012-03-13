<?php


function eseFile($filename)
{
  $content = array();
  try{
    $f = fopen($filename , "r");
    flock($f, LOCK_SH);
    fseek($f, 0);
    while($content[] = fgets($f));
    flock($f, LOCK_UN);
    fclose($f);
    
    unset($content[count($content)-1]);
  }
  catch(Exception $e){
    return FALSE;
  }
  return $content;
}
