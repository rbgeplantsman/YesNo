<?php
 //When included as part of a drupal module $file name is set in the module
 //When called as a Jquery enpoint dirctly the manifest file should be specified in the query
 //In both modes this output is intended to act as a service endpoint called within yesno.js
 //The service returns the url of the next image to return by selecting a random line in the manifest
 
 /**
  *TODO - This needs to be converted to read the next image from the DB when in drupal mode - this operation should take into account any response threshhold for 
  *       the question and should pick a random value from the manifest entries that have not yet reached their threshold - speed may be an issue here
  *       To get a single random value use limit based on a count of the available manifest entries 
  **/
 
 
 //Handle a non Drupal request
 if($_GET["filename"])
  {
   if($_GET["objectid"])
    getImageForObject($_GET["filename"],$_GET["objectid"]);
   else 
    getNextImageId($_GET["filename"]);
  }  

  
 /**
  *Gets the url for the next image to display by retrieving a random entry from a fixed length record manifest file
  *Used when operating without a DB
  **/
 function getNextImageId($filename)
  {
   //Filename will point to the text file but want to read the mfx file
   $folder = pathinfo($filename,PATHINFO_DIRNAME);
   $base = pathinfo($filename,PATHINFO_FILENAME);
   
   //At the moment just read the manifest into an array
   //$urls = file($filename,FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
   
   $mfx = "$folder/$base.mfx";
   
   $fp = fopen($mfx,"r");
   
   
   
   //Read the first $headerlength bytes as the header
   $headerlength = 20; 
   list($reclen,$count) = explode(":",fread($fp,$headerlength));
   
   //select a random element in the array
   $ix = mt_rand(1,$count) -1;
   
   //Move to the record at position ix - adding in $headerlength to account for the header length
   $fpos = ($reclen * $ix) + $headerlength;
   fseek($fp,$fpos);
   
   list($url,$objectid) = explode(",",trim(fread($fp,$reclen)));
   //Remove any enclosing quotes which may be present with a csv upload
   $url = trim($url, "\"");
   
   $r = json_encode(array(status=>1,"url"=>$url,"objectid"=>$objectid));
   echo $r;
   exit;
 }

/**
  *Gets the url for the next image to display by retrieving a random entry from the manifest entries table
  *Used when operating within Drupal when question subsetting is in place
  *nodeid is the node id for the current question
  *threshold is the number of answers required before the manifest entry is considered no longer to be a candidate
 **/ 
function getNextDbImageId($nodeid,$threshold = 1)
 {
  try
   {
    
      $query = db_select("yesno_manifest_entries","m")
               ->fields("m",array("url","objectid"))
               ->condition(db_or()->condition("s.responsecount",$threshold,"<")->isNull("s.responsecount"))
               ->condition("m.nodeid",$nodeid);
      $query->leftJoin("yesno_response_summaries","s","m.objectidhash = s.objectidhash and m.nodeid = s.nodeid"); 
      //Count the available candidates
      $numcandidates = $query->countQuery()->execute()->fetchField();         
      if($numcandidates > 0)
       { 
        //Pick a random number within the range of available candidates
        $ix = mt_rand(1,$numcandidates) -1;
        
        //Get the data for the selected row
        $result = $query->range($ix,1)->execute()->fetchAssoc();
        $r = json_encode(array(status=>1,"url"=>$result["url"],"objectid"=>$result["objectid"]));
       }
      else
       {
        //This indicates that the task is complete
        $r = json_encode(array(status=>2));
       }  
    }
   catch(PDOException $e)
    {
     $r = json_encode(array(status=>0,message=>"Problem looking up next image! Reason: {$e->getMessage()}"));
    }
  echo $r;      
  exit;  
 } 
 
 function getImageForObject($filename,$objectid)
  {
   //No idea how this will work efficiently - only method at the moment is a sequential search of the file!!!
  } 
 
 /**
  *Gets the url for the image to display as specified by the objectid
  *Used when operating within Drupal when question subsetting is in place 
  */
 function getImageForDbObject($nodeid,$objectid)
  {
   
   //only take the first 10 characters to ensure that there is no integer overflow - without this the number id converted to a float and bad things happen?
   $idhash = hexdec(substr(sha1($objectid), 0, 10));
    
   $result = db_select("yesno_manifest_entries","m")
               ->fields("m",array("url","objectid"))
               ->condition("m.nodeid",$nodeid)
               ->condition("m.objectidhash",$idhash)
               ->execute();
   //Loop through the result set and make sure we handle any collisions in the hash correctl            
   foreach($result as $row)
    {
     if($row->objectid == $objectid)
      {
       $url = $row->url;
       break;
      }  
    }            
   
   if($url)
    echo json_encode(array(status=>1,"url"=>$url,"objectid"=>$objectid));
   else
    echo json_encode(array(status=>0,message=>"unable to locate an image for the object with id: $objectid and hash $idhash"));  
  }