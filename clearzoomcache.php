<?php
  if(isset($_GET["base"]))
   $filename = $_GET["base"];
   
  $filename = str_replace("|","/",urldecode($filename));
  
  require "zoomify.php";
   $z = new zoomifier(); 
  
  $z->delCacheEntry($filename);
  