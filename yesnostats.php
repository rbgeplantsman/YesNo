<?php
 //When included as part of a drupal module $manifestfile and $resultsfile is set in the module
 //When called as a Jquery enpoint dirctly the manifest file should be specified in the query
 //In both modes this output is intended to act as a service endpoint called within yesno.js
 //The service returns the url of the next image to return by selecting a random line in the manifest
 
 /**
  *TODO 
  *Work out how this will work with non drupal mode - the same issue needs to be address in the save  
  *Work out how to get the id of the current user in drupal
  */
 
  //Handle non drupal request
 if($_GET["resultsfile"] and $_GET["manifestfile"])
  getStats($_GET["manifestfile"],$_GET["resultsfile"]);
  
 function getStats($manifestfile,$resultsfile)
  {
   //At the moment just read the manifest into an array
   //$urls = file($manifestfile,FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
   //Filename will point to the text file but want to read the mfx file
   $folder = pathinfo($manifestfile,PATHINFO_DIRNAME);
   $base = pathinfo($manifestfile,PATHINFO_FILENAME);
   $mfx = "$folder/$base.mfx";
   $fp = @fopen($mfx,"r");
   
   //Read the first $headerlength bytes as the header
   $headerlength = 20; 
   list($reclen,$totrecs) = explode(":",fread($fp,$headerlength)); 
   fclose($fp);
      
   //Open and load the required
   if($resultsfile)
    {
     $doc = new DOMDocument;
     $ok = @$doc->load($resultsfile);
     if($ok)
      {
       $x = new DOMXpath($doc);
       $answerlist = $x->query("/responses/response");
       $answers = $answerlist->length;
       $contributions = $x->query("/responses/response[@userid='$userid']");
       $yes = $x->query("/responses/response[@userid='$userid' and @answer='1']");
       $no = $x->query("/responses/response[@userid='$userid' and @answer='-1']");
       $dn = $x->query("/responses/response[@userid='$userid' and @answer='0']");
       echo json_encode(array(total=>$totrecs,answers=>$answers,contributions=>$contributions->length,yes=>$yes->length,no=>$no->length,dontknow=>$dn->length));   
      }
     else 
      echo json_encode(array(total=>$totrecs,answers=>0,contributions=>0)); 
    } 
   else
    {
     echo json_encode(array(total=>$totrecs,answers=>0,contributions=>0));   
    }  
  } 
  
 //Used as part of a drupal call where the Drupal database is being used for storing the data 
 function getDbStats($nodeid,$userid)
  {
    try
     {
      $node = node_load($nodeid);
       if(!$node)
        {
          echo json_encode(array(status=>0,message=>"Request for stats is for an umknown node"));
          drupal_exit();
        }
      $m = field_get_items("node",$node,"yesno_subtask_milestone_value"); 
      $milestone = $m[0]["value"] == "" ? 0 : $m[0]["value"];
      
      $t = field_get_items("node",$node,"yesno_threshold");
      /*
       Cast this result to an int to ensure that the comparison in the query is correct - the query builder appears to examine the data type to decide whether or not
       to quote the value - probably does not matter in mysql but does not work in sqllite
      */ 
      $threshold = (int)$t[0]["value"];
           
      //Create a query to count the completed answers
      /*$query = db_select("yesno_responses",'r')
             ->fields('r', array("objectid"))
             ->condition("r.nodeid",$nodeid)
             ->groupBy("r.objectid");
      $query->havingCondition("rcount",$threshold,">=");
      $query->addExpression("count(r.id)","rcount");*/
      $query = db_select("yesno_response_summaries","r")
               ->fields('r', array("objectid"))
               ->condition("r.nodeid",$nodeid)
               ->condition("r.responsecount",$threshold,">=");
  
      //Get the total number of completed answers based on the threshold
      $completed = $query->countQuery()->execute()->fetchField();
      
      $result = $query->execute();
      
      //$sql = "select count(*) from yesno_responses where nodeid = :nid";
      //db_query($sql,array(":nid"=>$nodeid))->fetchField();
      
      //Cannot use the summaries table here because these stats are user specific and the summaries table is task specific     
      $query = db_select("yesno_responses","r")
                ->fields('r', array("answer","objectid"))
                ->condition("r.nodeid",$nodeid);          
       
       
       //Get the total number of answers
       $answers = $query->countQuery()->execute()->fetchField();
      
       $query->condition("r.userid",$userid);
       $contributions = $query->countQuery()->execute()->fetchField();
       
       $query->groupBy('answer');
       $query->addExpression("count(r.id)","rcount");
       $result = $query->execute();
       
       $yes = 0;
       $no = 0;
       $dn = 0;
       while($row = $result->fetchAssoc())
        {
         switch($row["answer"])
          {
           case -1: $no = $row["rcount"]; break;
           case 0: $dn = $row["rcount"]; break;
           case 1: $yes = $row["rcount"]; break;
          }
        }
    
      $contributors = db_select("yesno_responses","r")->fields("r",array("userid"))->distinct()->condition("r.nodeid",$nodeid)->countQuery()->execute()->fetchField();
  
      //Counting of records needs more thinking about - how do we count answers?
      $totrecs = db_select("yesno_manifest_entries","m")->condition("nodeid",$nodeid)->countQuery()->execute()->fetchField();
      echo json_encode(array(status=>1,total=>$totrecs,responses=>$answers,completed=>$completed,contributions=>$contributions,contributors=>$contributors,yes=>$yes,no=>$no,dontknow=>$dn,milestone=>$milestone,threshold=>$threshold));
     } 
    catch(PDException $e)
     {
      echo json_encode(array(status =>0, message=>"Could not parse data file! Reason: {$e->getMessage()}"));
     }
     
  }
 