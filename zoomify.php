<?php
 //This class creates and stores scaled images, bands and tiles for zoomify on the fly
 //Although the class will cache bands and tiles it will not cache scaled images or the full image in order to avoid excessive disk space usage although this may be at the expense of the performance 
 Class zoomifier
   {
    Private $scaleData;
    Private $originalWidth;
    Private $originalHeight;
    Private $tileGroupMappings;
    Private $saveToLocation;// Temp area for files generated during zoomify
    Private $numberOfTiles;
    Private $tileSize;
    Private $imagefilename;
    Private $cacheroot;
        
    function zoomifier($cacheroot = "./zoomcache")
     {
        $this->tileGroupMappings = array();
        $this->locks = array();
        $this->cacheroot = $cacheroot;
        //Does the cache folder exist
        if(!file_exists($cacheroot))
         {
          if(!mkdir($cacheroot));
           {
            $this->releaseLock();
            throw new Exception("Could not create zoomify cache folder at $cacheroot!");
           }
         } 
     }
    
    
    //Retrieves or creates the requested tile from the stack
    function getTile($imageFilename, $tileName, $passedTileSize = 256)
     {
       $cachename = md5($imageFilename);
       
       $this->saveToLocation = "{$this->cacheroot}/$cachename";
       
       $this->scaleData = array();
      //Assume for now that all the file names will be unique
      //Since the file names will be urls will cache them under the hash of the url 
      $cachename = md5($imageFilename);
      $this->imagefilename = $imageFilename;
      
      $this->tileSize = $passedTileSize;
      
      
      //$this->createLock(1); 
      //Does the tile exist - Need to think about tile groups here - for now I will ignore them 
      if(file_exists("{$this->saveToLocation}/$tileName"))
       {
        $this->releaseLock();
        if(@readfile("{$this->saveToLocation}/$tileName") == FALSE)
         {
          $this->releaseLock();
          throw new Exception("Could not read the cached tile file!");
         } 
        exit;
       }
      else
       {
        //Does the xml file exist
        if(!file_exists("{$this->saveToLocation}/ImageProperties.xml"))
         { 
           try
            { 
              $this->getImageMetaData();
              $this->getTiers();
              //Work out tile and tile group information
              //$this->preProcess();
              $this->saveXMLOutput();
            }
           catch(Exception $e)
            {
             $this->releaseLock();
             throw $e;
            }   
         } 
        else
         {
           //Yes - all the initial information required can be garnered from the previously saved XML file
           $props = new DOMDocument();
           if(!@$props->load("{$this->saveToLocation}/ImageProperties.xml"))
            {
             $this->releaseLock();
             throw new Exception("Could not read Image properties file");
            }
            
           $pnode = $props->documentElement;
           $this->originalWidth = $pnode->getAttribute("WIDTH");
           $this->originalHeight = $pnode->getAttribute("HEIGHT");
           $this->tileSize = $pnode->getAttribute("TILESIZE");
           
           if(!$this->originalWidth or !$this->originalHeight or !$this->tileSize)
            {
             $this->releaseLock();
             throw new Exception("Missing value in Image properties file");
            }
           
           try
            {
             //Work out the scaling data
             $this->getImageMetaData();
            }
           catch(Exception $e)
            {
             $this->releaseLock();
             throw($e);
            }  
         }
        
        if(trim($tileName,"/") == "ImageProperties.xml")
          {
            $this->releaseLock();
            if(!@readfile("{$this->saveToLocation}/ImageProperties.xml"))
             {
               $this->releaseLock();
               throw new Exception("Could not read image properties file!");
             }
            exit;
          }

        //Parse the tile name 
        list($tier, $column, $row) = explode("-",pathinfo($tileName,PATHINFO_FILENAME));
        
        $this->waitfortier($tier);
        
        //Does the strip exist
        if(!file_exists("{$this->saveToLocation}/band$tier-$row.jpg"))
         {
          try
           { 
            $band = $this->getBand($tier,$row);
            //Use this for a higher level of pre-emptive cacheing at the row level
            //$band = $this->getBandsForTier($tier,$row);
           }
          catch(Exception $e)
           {
             $this->releaseLock();           
             throw $e;
           }  
         }
        else
         {
          //This should never be needed if the locking is working
          if(!$band = imagecreatefromjpeg("{$this->saveToLocation}/band$tier-$row.jpg"))
           {
            $this->releaseLock();
            throw new Exception("Could not load band image for tile extraction!");
           }
         } 
        
        try
         {   
          $this->getTileFromBand($band,$tier,$row,$column);
          //$this->getTilesFromBand($band,$tier,$column,$row);
         }
        catch(Exception $e)
         {
           $this->releaseLock();
           throw $e;
         }  
       } 
       //Release all locks
       $this->releaseLock();
     }
    
    //This will process an entire band into tiles - but is too slow for on the fly operation
    function getTilesFromBand(&$band,$tier,$targetcolumn,$row)
     {
       $this->createLock(3);
       //The band may be shorter than tile size
       $currentTileHeight  = imagesy($band);
       $bandWidth = imagesx($band);
       $numtiles = ceil($bandWidth/$this->tileSize);
       for($i = 0; $i < $numtiles; $i++)
        {
          $thisLeft = $this->tileSize * $i;
          //Check to make sure that the tile does not exceed the right edge of the band image
          $cropWidth = $thisLeft + $this->tileSize;
          if ($cropWidth > $bandWidth)
           $cropWidth = $bandWidth - $thisLeft;
          
          if(!$tile = imagecreatetruecolor($cropWidth,$currentTileHeight))
           {
            throw new Exception("Could not create tile image!");
           }
           
          if(!imagecopy($tile, $band, 0, 0, $thisLeft, 0, $cropWidth, $currentTileHeight))
           {
            throw new Exception("Could not copy data into the tile image!");
           }
           
          $cachedfile =  $this->getTileFileName($tier, $i, $row);
          if(!imagejpeg($tile,"{$this->saveToLocation}/$cachedfile"))
           {
            throw new Exception("Could not copy save tile image to cache");
           }
           
          if($i == $targetcolumn)
           {
            if(!@readfile("{$this->saveToLocation}/$cachedfile"))
             {
              throw new Exception("Could not read tile image from cache");
             }
           }  
          else
           imagedestroy($tile);       
        }
     }
    
    
    //Cuts the specified tile from the band
    function getTileFromBand(&$band,$tier,$row,$column)
     {
       //Create a level 3 block
       $this->createLock(3);
       $currentTileHeight  = imagesy($band);
       $bandWidth = imagesx($band);
       $tileSize = $this->tileSize;
        
       $thisLeft = $column * $tileSize;
       
       //Check to make sure that the tile does not exceed the right edge of the band image
       $cropWidth = $thisLeft + $tileSize;
       if ($cropWidth > $bandWidth)
        $tileSize -= ($cropWidth - $bandWidth);
        
       if(!$tile = imagecreatetruecolor($tileSize,$currentTileHeight))
        {
         throw new Exception("Could not create tile image");
        }
       //$tile = imagecreatetruecolor(256,204);
       //Extract the tile from the band
       
       if(!imagecopy($tile, $band, 0, 0, $thisLeft, 0, $tileSize, $currentTileHeight))
        {
         throw new Exception("Could not copy data into tile image");
        }
        
       //Return the image data as a jpeg after saving it to the cache
       //For now I will igonore the tile group stuff
       $cachedfile =  $this->getTileFileName($tier, $column, $row);
       
       if(!imagejpeg($tile,"{$this->saveToLocation}/$cachedfile"))
        {
         throw new Exception("Could not save tile image to cache");
        }
        
       if(!readfile("{$this->saveToLocation}/$cachedfile"))
        {
         throw new Exception("Could not read tile image from cache");
        }
     }
    
    //Splits the base image into bands at the appropriate scaling for the tier and returns the target band for further processing
    //Too slow for on the fly working
    function getBandsForTier($tier,$targetrow)
     {
      $this->createLock(2);
      //rescale the base image
      $destwidth = $this->scaleData[$tier]["width"];
      $destheight = $this->scaleData[$tier]["height"];
      
      
      $tierimage = $this->getTier($tier);
       
      $numrows = ceil($destheight / $this->tileSize);
      for($i = 0; $i < $numrows; $i++)
       {
         $band = getBand($tier,$i);
          
         if($i == $targetrow)
          $targetband = $band;
         else
          imagedestroy($band); 
       }
      imagedestroy($tierimage);
      return $targetband; 
     }
    
    //Cuts out a scaled band from the tier image suitable for subsequent extraction of tiles
    function getBand($tier,$row)
     {
       //Create a level 2 block
       $this->createLock(2);
       $tierheight = $this->scaleData[$tier]["height"];
       $bandtop = $row * $this->tileSize;
       $bandheight = $this->tileSize;
       if(($bandheight  + $bandtop) > $tierheight)
        $bandheight = $tierheight - $bandtop;
       
       $tierimage = $this->getTier($tier);
        
       $destwidth = $this->scaleData[$tier]["width"];
       $scaledheight = $this->scaleData[$tier]["height"];
       
       if(!$band = imagecreatetruecolor($destwidth, $bandheight))
          {
           throw new Exception("Could not create image for band");
          } 
          
       if(!imagecopy($band, $tierimage, 0, 0, 0, $bandtop, $destwidth, $bandheight))
        {
         throw new Exception("Could not copy data into image for band");
        }
         
       try
        { 
         $this->saveBand($band, $tier, $row);
        }
       catch(Exception $e)
        {
         throw($e);
        }  
       return $band;
     }
    
    //Checks for a cached version of the base image
    function getBaseImage()
     {
       //Cache the base image
       if(!file_exists("{$this->saveToLocation}/baseimage.jpg"))
        {
         $this->baseimage = imagecreatefromstring(file_get_contents($this->imagefilename));
         imagejpeg($this->baseimage,"{$this->saveToLocation}/baseimage.jpg");
        } 
       $this->baseimage = imagecreatefromjpeg("{$this->saveToLocation}/baseimage.jpg"); 
     }
   
    function getTiers()
     {
      if(!$this->baseimage)
       return;
     
      for($i = count($this->scaleData) - 2; $i >= 0; $i--)
       {
         $tier = $this->getTier($i);
         imagedestroy($this->baseimage);
         $this->baseimage = $tier;
       }
     }
    
    //Checks for a cached version of a tier source and creates a scaled version of the base image if needed
    function getTier($tier)
     {
       $toptier = count($this->scaleData) - 1;
       
       //First check to see if there is a locally cached version of the tier
       if(file_exists("{$this->saveToLocation}/$tier.jpg"))
        {
         return imagecreatefromjpeg("{$this->saveToLocation}/$tier.jpg");
        }
        
       if(!$this->baseimage)
        $this->getBaseImage();
       
       if($tier != $toptier)
        {   
           $destwidth = $this->scaleData[$tier]["width"];
           $destheight = $this->scaleData[$tier]["height"];
           $srcwidth = $this->scaleData[$tier + 1]["width"];
           $srcheight = $this->scaleData[$tier + 1]["height"];
           $tierimage = imagecreatetruecolor($destwidth,$destheight);
           //$quality = 2;
           //if($tier == 0)
            $quality = 1;
            
           //if(!$this->fastimagecopyresampled($tierimage,$this->baseimage,0,0,0,0,$destwidth,$destheight,$this->originalWidth,$this->originalHeight,$quality))
           if(!$this->fastimagecopyresampled($tierimage,$this->baseimage,0,0,0,0,$destwidth,$destheight,$srcwidth,$srcheight,$quality))
            {
             throw new Exception("Could not create image for tier!");
            }
        
           imagejpeg($tierimage,"{$this->saveToLocation}/$tier.jpg");
           return $tierimage;
         }
        else
         return $this->baseimage; 
     }
    
    //Gets the scaling for each tier in the stack 
    function getImageMetadata()
     {
      if(!file_exists($this->saveToLocation))
       {
        if(!@mkdir($this->saveToLocation))
         {
           throw new Exception("Could not create image folder in cache!");
         }
        }
             
      //No data has been obtained from the imageproperties file 
      if(!$this->originalWidth)
       {
        $this->getBaseImage();
        $this->originalWidth = imagesx($this->baseimage);
        $this->originalHeight = imagesy($this->baseimage);
       } 
       
      // get scaling information
      $scale_width  = $this->originalWidth;
      $scale_height = $this->originalHeight;
      $this->scaleData[] = array("width"=>$scale_width, "height"=>$scale_height);
      $this->numberOfTiles = ceil($scale_width/$this->tileSize) * ceil($scale_height/$this->tileSize); 
      While (($scale_width > $this->tileSize) or ($scale_height > $this->tileSize))
       { 
          $scale_width = (int)($scale_width / 2);
          $scale_height = (int)($scale_height / 2);
          $this->scaleData[] = array("width"=>$scale_width, "height"=>$scale_height);
          $this->numberOfTiles += ceil($scale_width/$this->tileSize) * ceil($scale_height/$this->tileSize);
       }
      //Reverse the scale data array so that element 0 maps to tier 0
      $this->scaleData = array_reverse($this->scaleData);
     }
    
    //Constructs a tile file name from the tier, column and row ids 
    function getTileFileName($tier, $columnNumber, $rowNumber)
     {
        // get the name of the file the tile will be saved as """
        return "$tier-$columnNumber-$rowNumber.jpg";
     }
   
   //Saves a band to the cache
   function saveBand(&$image, $tier, $thisRow )
     {
        $tile_file = "{$this->saveToLocation}/band-$tier-$thisRow.jpg";
        if(!imagejpeg($image,$tile_file))
         {
          throw new Exception("Could not save image for band");
         }
     }    
    
   function saveXMLOutput()
    { 
      //save xml metadata about the tiles
      if(@file_put_contents("{$this->saveToLocation}/ImageProperties.xml", "<IMAGE_PROPERTIES WIDTH=\"{$this->originalWidth}\" HEIGHT=\"{$this->originalHeight}\" NUMTILES=\"{$this->numberOfTiles}\" NUMIMAGES=\"1\" VERSION=\"1.8\" TILESIZE=\"{$this->tileSize}\" />") == FALSE)
       throw new Exception("Could not save image properties file!");
    }
   
   function waitforTier($tier)
    {
      while(!file_exists("{$this->saveToLocation}/$tier.jpg"))
         {
          //Sleep for a random time
          set_time_limit(30);
          usleep(rand(10, 500));
         }
    }
   
   //Delete a temp cache folder
   function delCacheEntry($dir)
    {
     $this->delTree($this->cacheroot."/".md5($dir));
    }
    
    //Delete a folder tree 
    function delTree($dir) 
     {
       if(!$dir)
        return;

       $files = scandir($dir);
       if($files)
        {
         foreach ($files as $file) 
          {
            if($file <> "." and $file <> "..")
             {
              is_dir("$dir/$file") ? delTree("$dir/$file") : unlink("$dir/$file");
             } 
          }
        }  
      return rmdir($dir);
     }
   
    //Creates a lock to prevent simultaneous tile requests negating the cache
    function createLock($level)
     {
        //Does the folder in the cache exist?
        if(!file_exists($this->saveToLocation))
         if(!@mkdir($this->saveToLocation))
          {
            throw new Exception("Could not create image folder in cache!");
          }
          
        //Create a lock file or wait for the file to clear
        $sleeptime = 0;
        
        $maxsleeptime = 10 * 1000 * 1000; //10 Seconds
        $lockfile = "{$this->saveToLocation}/level$level.lock";
        while(file_exists($lockfile))
         {
          //Sleep for a random time in microseconds
          set_time_limit(30);
          $thissleeptime = rand(10, 500); 
          usleep(rand(10, 500));
          $sleeptime += $thissleeptime;
          if($sleeptime >= 10 * 1)
          @unlink($lockfile); 
         }
        
        if(@file_put_contents($lockfile,"") === FALSE)
         throw new Exception("Could not create level$level lock file with name $lockfile!");
         
        $this->locks[$level] = $lockfile;
        //Release the lock from the preveious level allowing other scripts to move to their next level
        $this->releaseLock($level - 1);
     } 
   
    //Releases a previously created lock 
    function releaseLock($level = "")
     {
       if($level === 0)
        return;
        
       if($level)
        {
         if(isset($this->locks[$level]))
          {
           if(file_exists($this->locks[$level]))
            @unlink($this->locks[$level]);
            unset($this->locks[$level]);
          }  
        }
       else
        {
         //Clear all the locks
         foreach($this->locks as $level=>$lockfile)
          {
            if(file_exists($lockfile))
             @unlink($lockfile);
          }
         $this->locks = array(); 
        }  
     }
     
    function fastimagecopyresampled (&$dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h, $quality = 3) 
     {
      // Plug-and-Play fastimagecopyresampled function replaces much slower imagecopyresampled.
      // Just include this function and change all "imagecopyresampled" references to "fastimagecopyresampled".
      // Typically from 30 to 60 times faster when reducing high resolution images down to thumbnail size using the default quality setting.
      // Author: Tim Eckel - Date: 09/07/07 - Version: 1.1 - Project: FreeRingers.net - Freely distributable - These comments must remain.
      //
      // Optional "quality" parameter (defaults is 3). Fractional values are allowed, for example 1.5. Must be greater than zero.
      // Between 0 and 1 = Fast, but mosaic results, closer to 0 increases the mosaic effect.
      // 1 = Up to 350 times faster. Poor results, looks very similar to imagecopyresized.
      // 2 = Up to 95 times faster.  Images appear a little sharp, some prefer this over a quality of 3.
      // 3 = Up to 60 times faster.  Will give high quality smooth results very close to imagecopyresampled, just faster.
      // 4 = Up to 25 times faster.  Almost identical to imagecopyresampled for most images.
      // 5 = No speedup. Just uses imagecopyresampled, no advantage over imagecopyresampled.
    
      if (empty($src_image) || empty($dst_image) || $quality <= 0) { return false; }
      if ($quality < 5 && (($dst_w * $quality) < $src_w || ($dst_h * $quality) < $src_h)) 
       {
        $temp = imagecreatetruecolor ($dst_w * $quality + 1, $dst_h * $quality + 1);
        imagecopyresized ($temp, $src_image, 0, 0, $src_x, $src_y, $dst_w * $quality + 1, $dst_h * $quality + 1, $src_w, $src_h);
        imagecopyresampled ($dst_image, $temp, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $dst_w * $quality, $dst_h * $quality);
        imagedestroy ($temp);
       }
      else imagecopyresampled ($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
       return true;
    }  
}