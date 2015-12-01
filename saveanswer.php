<?php
 //When called as a Jquery endpoint directly the manifest file should be specified in the query
 //In both modes this output is intended to act as a service endpoint called within yesno.js
 
 if($_GET["filename"])
  $filename = $_GET["filename"];
 
 //Object id is the id of the object in the image - this will allow this result to be tied back to other records pertaining to the same object in other systems
 if($_GET["objectid"])
  $objectid = $_GET["objectid"];
 
 
 //Object id is the id of the object in the image - this will allow this result to be tied back to other records pertaining to the same object in other systems
 if($_GET["answer"])
  $answer = $_GET["answer"];
 
 //This should also add in the user id in order to support stats and the like
  
 if(!$filename)
  {
   echo json_encode(array(status => 0, message => "No filename in request!"));
   exit;
  }
  
 if(!$objectid)
  {
   echo json_encode(array(status => 0, message => "No object id in request!"));
   exit;
  } 
 
 if(! isset($answer))
  {
   echo json_encode(array(status => 0, message => "No answer in request!"));
   exit;
  }
 
 //Handle a non drupal request
 if($_GET["filename"] and $objectid and $answer)
  saveanswer($filename,$objectid,$answer,"");
 
 
 function saveanswer($filename,$objectid,$answer,$userid)
  { 
     if(!file_exists($filename))
      file_put_contents($filename,"<?xml version=\"1.0\" ?>\n<responses></responses>");
      
     libxml_use_internal_errors(true); 
      
     $doc = new DOMDocument;
     $result = $doc->load($filename);
     if(!$result)
      {
       $message = "";
       foreach (libxml_get_errors() as $error) 
          {
            $message .=  $error->message;
            ///$and = "\nand ";
          }
       libxml_clear_errors();
       echo json_encode(array(status =>0, message=>"Could not parse data file! Reason: $message"));
       exit;
      }
     //$xpath = new DOMXPath($doc);
     $responsenode = $doc->documentElement->appendChild($doc->createElement("response"));
     $responsenode->setAttribute("objectid",$objectid);
     $responsenode->setAttribute("answer",$answer);
     $responsenode->setAttribute("userid",$userid);
     $doc->save($filename);
     
     echo json_encode(array(status=>1));
  }  
  
  