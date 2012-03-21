<?php
$hoge = array("hoge");
array_shift($hoge);
var_dump($hoge);
echo (count($hoge) === 0);
