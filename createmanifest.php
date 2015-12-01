<?php
  
 //Read the file contents into an array
 //Work through the array and work out the longest line - set this as the record length
 /**
  *Converts and uploaded text file into a fixed record length file in non Drupal mode
  *Loads the manifest data into the data base when in Drupal mode
  */
 function convertfile($filename)
  {
     $urls = file($filename,FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
     $len = 0;
     foreach($urls as $url)
      {
        if(strlen($url) > $len)
         $len = strlen($url);
      }
     
     //now output the file with an mfx extension and with the recordlength appended to the filename
     $base = pathinfo($filename,PATHINFO_FILENAME);
     $folder = pathinfo($filename,PATHINFO_DIRNAME);
     //Create the file
     
     $fp = fopen("{$folder}/{$base}.mfx","w");
     //First record is 20 bytes contains the record length and the number of entries
     fwrite($fp, str_pad($len.":".count($urls),20));
     
     foreach($urls as $url)
      {
       fwrite($fp, str_pad($url,$len));
      } 
     fclose($fp); 
  }
 
 /**
  *Loads a uploaded text file into the manifest_entries table
  *Loads the manifest data into the data base when in Drupal mode
  */
 
 function storeManifest($filename,$nodeid)
  {
   $urls = file($filename,FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
   
   //Delete all entries with nodeid
   db_delete('yesno_manifest_entries')->condition('nodeid', $nodeid)->execute();
   
   
   //Try blocking this onto sets of 100 values
   foreach($urls as $line)
    {
     set_time_limit(20); // Keep the script alove while this happens because this can take a long time
     list($url,$objectid) = explode(",",$line);
     db_insert('yesno_manifest_entries')->fields(array(
                                                         'nodeid'=>$nodeid,
                                                         'url'=>$url,
                                                         'objectid'=>$objectid,
                                                         ))->execute();
    }
  }