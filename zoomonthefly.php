<?php
  require "zoomify.php";
  $z = new zoomifier;
    
  if(isset($_GET["base"]))
   $filename = $_GET["base"];
  
  $filename = str_replace("|","/",urldecode($filename));
  
  if(isset($_GET["tile"]))
   $tilename = $_GET["tile"];
  
  try
   {
    $z->getTile($filename,$tilename);
   }   
  catch(Exception $e)
   {
    //Make sure any locks are cleared after an exception - this is kind of belt and braces since errors should result in locks being cleared by the zoomify object
    $z->releaseLock();
    //drupal_set_message($e->getMessage(),"error");
   }