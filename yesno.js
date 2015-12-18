 /**
 *Jquery widget which drives all yes no module scoring activities
 *Wrapped in a self invoking function to ensure $ is available within the widget 
 */
(function($) 
 {
  $.widget( "yesno.yesnoform",
          { 
            options:{
               title: "No title",
               drupalnode: "", //The config for the question when served via drupal is stored as a drupal node. This is the id of that node
               loggedin: "", //If the user is not logged in no stats are shown
               manifest: "", //This must be set explicitly for non drupal operation - it can be ignored when operating in drupal since this info is in the drupal config
                             //This will probably be superceded by a non drupla configuration module
               resultsfile: "",//This must be set explicitly for non-drupal operations otherwise can be ignored               
               
               /**
                *Controls the aspect ratio of the widget on screen. This is defined as width / height - defaults to 0.69
                *This works well for most herbarium specimen images but should be set to match the most common aspect ratio of the images
                *in the manifest
                */
               aspectratio: 0.69,
               
               /**
                * View mode specifies the mechanism by which images are obtained and presented on screen 
                * Mode 1 - No zooming except screen zooming on mobile devices is available. The image presented as a bakground
                *          image using css with a size setting of contain - While this will work for any size of image, when it is intended
                *          to serve the images via mobile devices this option should not be used since the image shoulb scaled on the server side
                *          so as to minimise bandwidth consumption.
                *          
                * Mode 2 - Zooming and panning is presented using the zoomify viewer. In this mode the urls in the manifest should point to the 
                *          Zoomify stack to be presented.
                * 
                * Mode 3 - local caching and sizing (here local means as part of the drupal module implemntation). Acts similarly to Mode one but the images are
                *          scaled and cached by the module. Zoomification is enabled by on the fly stack creation
                */
               viewmode: 1, //Default to view mode 1
               objectid : "", //When this is passed this viewer is in review mode and will only load the specified image and will not show the yes no don't know and help buttons  
             },
          _create:function()
                    {
                     var self = this
                                          
                     self.element.wrapInner($("<div></div>",{id:"yesno-help"}))
                     self.element.prepend($("<div></div>").html(self.options.title))
                     //Hidden container for the interface elements - ie image and yes no dont know buttons
                     
                     if(!self.options.objectid) 
                      {
                       var begin = $("<input></input>",{id:"yesno-begin",type:"button",value:"Begin"}).addClass("yesno-button")
                       self.element.append(begin)
                      
                      
                       self._resetInterface(true)
                                        
                     
                       begin.on("click",function()
                                        {
                                          $("#yesno-help,#yesno-begin").hide(); 
                                          $("#yesno-interface").show()
                                          $("#main-content").width(self.img.width())
                                        })
                       self._nextimage()
                      }
                     else
                      {
                       self._resetInterface(false)
                       self._getimageforobject()
                      }  
                     
                     //$(window).on("orientationchange",function(){alert("Round we go")})
                     $(window).on("resize",function(){
                                                       clearTimeout(self.resizetimer)
                                                       self.resizetimer = setTimeout(function(){self._resetInterface();self._showImage()},250)
                                                     })
                    },
                    
            _resetInterface: function(hide)
                                {
                                 wheight = $(window).height()
                                 wwidth = $(window).width()
                                 var self = this
                                 if(self.interfacepositioner)
                                   self.interfacepositioner.remove()
                                  
                                 self.interfacepositioner = $("<div></div>",{id:"yesno-interfacepositioner"})
                                 self.interface = $("<div></div>",{id:"yesno-interface"})
                                 var cb = $("<div></div>",{id:"yesno-closeviewer",title:"Quit this question"}).on("click",function(){self._quit()})
                                 var cbtext = $("<div></div>").css("padding-left","0.3em")
                                 cb.append(cbtext)
                                 self.interface.append(cb)
                                 
                                 if(hide)
                                  self.interface.hide()
                                 self.interfacepositioner.append(self.interface)
                                 $('body').append(self.interfacepositioner)
                                 self.interface.css("background-color","white")//.css("overflow","hidden")
                                 //Create the stats block
                                 self.stats = $("<div></div>")
                                 
                                 //Object id will only be set if in review mode where we will not be displaying stats
                                 if(!self.options.objectid)
                                  {
                                     self.stats.attr("id","yesno-statscontainer")
                                     self.question = $("<div></div>",{id:"yesno-interfacetitle", class:"yesno-interfacetitle"}).html($("#page-title").text())
                                     self.stats.append(self.question)
                                     //Add in the progress bars container
                                     self.progbars = $("<div></div>",{id:"yesno-progressbarscontainer"})
                                     self.stats.append(self.progbars)
                                     //Add in the view stats button container and button
                                     var c = $("<div></div>",{id:"yesno-progressviewbuttoncontainer"})
                                     c.append($("<div></div>",{id:"yesno-viewstats-button",title:"View stats. for this question"}).addClass("yesno-button").addClass("yesno-statsbutton").on("click",function(){self._getstats(true)}))
                                     c.append($("<div></div>",{id:"yesno-helpbutton",title:"Help"}).addClass("yesno-button").addClass("yesno-statsbutton").on("click",function(){self._showhelp()}))
                                     self.stats.append(c)
                                   }  
                                  self.interface.append(self.stats)
                                     
                                     /*
                                     self.stats.append($("<div></div>",{id:"yesno-total"}).addClass("stat").html("<label>Total number of records</label><span></span>"))
                                     self.stats.append($("<div></div>",{id:"yesno-answers"}).addClass("stat").html("<label>Total number of answers</label><span></span>"))
                                     self.stats.append($("<div></div>",{id:"yesno-contribution"}).addClass("stat").html("<label>Your contribution</label><span></span><label> answers</label>"))
                                     */
                                 if(!self.options.objectid)
                                  {    
                                     self.progbars.append($("<div></div>",{id:"yesno-overallprogress",title:"Proportion of overall dataset completed"}).addClass("yesno-progress"))
                                     if(self.options.loggedin) 
                                      self.progbars.append($("<div></div>",{id:"yesno-personalprogress",title:"Progress towards your next milestone"}).addClass("yesno-progress"))
                                     else
                                      self.progbars.append($("<div>You are not logged in - your personal stats cannot be recorded</div>").addClass("yesno-interfacetitle"))                                                                                         
                                  }
                                                                   
                                 //The image element used to show the image 
                                 self.img = $("<div></div>",{id:"yesno-image"})
                                 self.interface.append(self.img)
                                 
                                 
                                 self.buttons = $("<div></div>").addClass("yesno-yesnobuttoncontainer")
                                 if(!self.options.objectid)
                                  {
                                   var s = $("<div></div>").addClass("yesno-buttonspacer");
                                   var b = $("<div></div>",{id:"yesno-yesbutton",title:"Yes"}).addClass("yesno-yesno-button").addClass("yesno-button").on("click",function(){self._saveresult(1)})
                                   self.buttons.append(b).append(s.clone())
                                   var b = $("<div></div>",{id:"yesno-dnbutton",title:"Don't know"}).addClass("yesno-yesno-button").addClass("yesno-button").on("click",function(){self._saveresult(0)})
                                   self.buttons.append(b)
                                   self.buttons.append(b).append(s.clone())
                                   var b = $("<div></div>",{id:"yesno-nobutton",title:"No"}).addClass("yesno-yesno-button").addClass("yesno-button").on("click",function(){self._saveresult(-1)})
                                   self.buttons.append(b)
                                  } 
                                 //var b = $("<div></div>",{id:"yesno-quitbutton",title:"Quit"}).addClass("yesno-yesno-button").addClass("yesno-button").on("click",function(){self._quit()})
                                 //self.buttons.append(b)                                            
                                 self.interface.append(self.buttons)
                                 //12 here is 2 * border width
                                 while(((self.interface.height() * this.options.aspectratio) > wwidth - 12) || (self.interface.height() > wheight - 20))
                                  {
                                   self.interface.height(self.interface.height() - 1)
                                  }
                                 var imageheight = self.interface.height() - self.buttons.height() - self.stats.height();  
                                 var interfacewidth = (self.interface.height() * this.options.aspectratio)
                                 self.interface.width(interfacewidth)
                                 imagewidth = Math.floor(imageheight * this.options.aspectratio)
                                 self.img.height(imageheight).width(imagewidth)
                                 self.interfacepositioner.css("margin-left",((wwidth - 12)- interfacewidth)/2 + "px") 
                                },
                            
            _saveresult:function(answer)
                          {
                           var self = this
                           //Save ths current zoomify position - this will allow it to be reset if the same image is viewed again in the same question category
                           //Not necessarily a good idea where should I save this data?
                           /*if(self.options.viewmode == 2)
                            {
                             console.log("X= " + Z.imageX)
                             console.log("Y= " + Z.imageY)
                             console.log("Z= " + Z.imageZ)
                            }*/
                           
                           //Hide the yes no buttons to prevent resubmission while the next image is being prepared - use visibility not display so that layout is not altered
                           $("#yesno-yesbutton, #yesno-nobutton, #yesno-dnbutton").css("visibility","hidden")
                            
                           var url = "saveanswer.php"
                           if(self.options.drupalnode)
                             url = "../yesnosaveanswer/" + self.options.drupalnode  + "/" + self.currentobjectid + "/" + answer
                           else
                            url += "?filename="+self.options.resultsfile + "&objectid=" + self.currentobjectid + "&answer=" + answer   
                           $.ajax({
                                   url: url,
                                   dataType: "json",
                                   success: function(data)
                                              {
                                                if(data.status)
                                                 {
                                                  if(data.status > 0)
                                                   self._nextimage(data.newmilestone)
                                                  else
                                                   {
                                                     alert("Your answer has been successfully recorded however a system error has occurred. A messagae has been sent to the system administrator in relation to this error")
                                                   } 
                                                 }
                                                else
                                                 {
                                                  alert("Sorry but for technical reasons we could not save your answer!")
                                                  console.log(data.message)
                                                 }                                    
                                              }  
                                  })
                           
                          },        
            _nextimage: function(congratulate)
                          {
                            var self = this
                            
                            //Fire off an asynchronous request to clear up any zoom on the fly cache since it is unlikely that the same image will be viewed multiple times
                            self._clearZoomCache()                             
                            
                            var url = "nextimage.php"
                            if(self.options.drupalnode)
                             url = "../yesnonextimage/" + self.options.drupalnode 
                            else
                             url += "?filename="+self.options.manifest 
                            $.ajax(
                                   {
                                    url: url,
                                    dataType: "json",
                                    success: function(data)
                                                  {
                                                    /**
                                                     *Status 0 = request failed
                                                     *Status 1 = request successful
                                                     *Status 2 = request failed because task is complete
                                                     */
                                                    switch(data.status)
                                                     {
                                                      case 0: alert(data.message); break;
                                                      case 1: self.currenturl = data.url
                                                                self.currentobjectid = data.objectid
                                                                self._showImage()
                                                                self._getstats(false,congratulate)
                                                                //Show the yes no buttons to
                                                                $("#yesno-yesbutton, #yesno-nobutton, #yesno-dnbutton").css("visibility","visible")
                                                                break;
                                                       case 2: self._endtask(); break         
                                                     } 
                                                  }
                                   })
                                   
                            //self.interface.position({my:"center",at:"center",of:window})       
                          } ,
             _getimageforobject: function()
                                        {
                                          var self = this
                                          var url = "nextimage.php"
                                          if(self.options.drupalnode)
                                            url = "../yesnonextimage/" + self.options.drupalnode + "/" + self.options.objectid 
                                          else
                                           url += "?objectid="+self.options.objectid 
                                          $.ajax(
                                                 {
                                                  url: url,
                                                  dataType: "json",
                                                  success: function(data)
                                                               {
                                                                 switch(data.status)
                                                                   {
                                                                      case 0: alert(data.message); self._quit(); break;
                                                                      default:  self.currenturl = data.url
                                                                                self.currentobjectid = data.objectid
                                                                                self._showImage()
                                                                                break;
                                                                    }       
                                                               }
                                                 })
                                        },                                     
             _showImage: function()
                            {
                             var self = this
                             switch(self.options.viewmode)
                                                    {
                                                      case 1:   //Standard image no zooming
                                                                $("#yesno-image").css("background-image","url(" + self.currenturl + ")")
                                                                break
                                                      case 2:   //Zoomify
                                                                if(typeof Z == 'undefined')
                                                                 $("#yesno-image").html("The zoomify viewer is not available")
                                                                else
                                                                 {
                                                                   $("#yesno-image").empty()
                                                                   Z.showImage("yesno-image", self.currenturl,"zSkinPath=/zoomify/Assets/Skins/Default")
                                                                   Z.initialize().done = false
                                                                 }  
                                                                break 
                                                      case 3:   //Zoomify on the fly
                                                                if(typeof Z == 'undefined')
                                                                 {
                                                                  $("#yesno-image").html("The zoomify viewer is not available")
                                                                 } 
                                                                else
                                                                 {
                                                                   $("#yesno-image").empty()
                                                                   var base = encodeURIComponent(self.currenturl)
                                                                   if(self.options.drupalnode)
                                                                    url = "../yesnozoomify/" + base.replace(/\%2F/g,"|")
                                                                   else 
                                                                    url = "/yesno/zoomonthefly.php?base=" + base + "&tile=" 
                                                                   Z.showImage("yesno-image", url,"zSkinPath=/zoomify/Assets/Skins/Default")
                                                                   Z.initialize().done = false
                                                                 }            
                                                    }  
                            },
             _clearZoomCache: function()
                                 {
                                   var self = this
                                   if((self.options.viewmode == 3) && self.currenturl)
                                     {
                                      var base = encodeURIComponent(self.currenturl)
                                      var url = "/yesno/clearzoomcache.php?base=" + base
                                      if(self.options.drupalnode)
                                       url = "../yesnoclearzoomcache/" + base.replace(/\%2F/g,"|")
                                      $.ajax({
                                              url:url,
                                              dataType: "json"
                                             })
                                     } 
                                 },               
                                         
             _getstats: function(showstats,congratulate)
                         {
                            var self = this
                            var url = "yesnostats.php"
                            if(self.options.drupalnode)
                             url = "../yesnostats/" + self.options.drupalnode
                            else
                             url += "?manifestfile=" + self.options.manifest + "&resultsfile=" + self.options.resultsfile   
                            $.ajax(
                                   {
                                    url: url,
                                    dataType: "json",
                                    success: function(data)
                                                 {
                                                   self.currentstats = data
                                                   var prog = $("<div></div>",{id:"yesno-overallprogrss-indicator"}).addClass("yesno-progress-indicator").css("width", ((data.completed/data.total) * 100) + "%")
                                                   $("#yesno-overallprogress").empty().append(prog)
                                                   if(data.milestone >0)
                                                    {
                                                     if(self.options.loggedin)
                                                      prog = $("<div></div>",{id:"yesno-personalprogress-indicator"}).addClass("yesno-progress-indicator").css("width", (((data.contributions%data.milestone) / data.milestone) * 100) + "%")
                                                     else
                                                      prog = $("<div>You are not logged in - your personal stats will not be updated</div>")
                                                     $("#yesno-personalprogress").empty().append(prog)
                                                    }
                                                   else
                                                    {
                                                     //No milestone value has been set so hide the personal progress
                                                     $("#yesno-personalprogress").css("display","none")
                                                    }  
                                                   //self.interface.position({my:"center",at:"center",of:window})
                                                   //Show the stats window to congratulate the user when a new mile stone is reached
                                                   if(congratulate)
                                                    self._showstats(true)
                                                   else
                                                    {
                                                     if(showstats)
                                                      self._showstats(false)
                                                    } 
                                                    
                                                 }
                                   }) 
                         },
              _showhelp: function()
                         {
                           var self = this
                           var viewer = $("<div></div>",{class:"yesno-helpviewer"})
                           viewer.html($("#yesno-help").html())
                           $("body").append(viewer)
                           viewer.position({my:"center",at:"center",of:self.interface})
                           var cb = $("<div></div>").attr("id","yesno-closehelpviewer").on("click",function(){$(this).parent().remove()})
                           var cbtext = $("<div></div>").css("padding-left","0.3em")
                           cb.append(cbtext)
                           viewer.append(cb) 
                         },
                           
              _showstats: function(congratulate)
                           {
                             var self = this
                             var viewer = $("<div></div>",{class:"yesno-helpviewer"})
                             
                             if(congratulate)
                              {
                               var c = $("<div></div>",{id:"yesno-statsviewer-congrats"}).addClass("yesno-statsviewer-block")
                               var t = $("<div>Congratulations and Thank You!</div>").addClass("yesno-statsviewer-ctitle") 
                               c.append(t)
                               var m = $("<div>You have just completed a new milestone for this project!</div>").addClass("yesno-statsviewer-cinfo")
                               c.append(m)
                               viewer.append(c)
                              }
                             
                             //Can only show personal stats if you are logged in 
                             if(self.options.loggedin)
                              {
                               var p = $("<div></div>",{id:"yesno-statsviewer-personalstats"}).addClass("yesno-statsviewer-block")
                               p.append($("<div></div>").html("Your contribution to this task").addClass("yesno-statsviewer-block-title"))
                               p.append($("<div></div>").html("Total number of responses: " + self.currentstats.contributions).addClass("yesno-statsviewer-block-info"))                                                    
                               p.append($("<div></div>").html("Number of yes reponses: " + self.currentstats.yes).addClass("yesno-statsviewer-block-info"))
                               p.append($("<div></div>").html("Number of no responses: " + self.currentstats.no).addClass("yesno-statsviewer-block-info"))
                               p.append($("<div></div>").html("Number of don't know responses: " + self.currentstats.dontknow).addClass("yesno-statsviewer-block-info"))
                               //Only show the progress towards a milestone if a milestone has been specified
                               if(self.currentstats.milestone)
                                {
                                 p.append($("<div></div>").html("Number of milestones reached: " + Math.floor(self.currentstats.contributions / self.currentstats.milestone)).addClass("yesno-statsviewer-block-info"))
                                 p.append($("<div></div>").html("Number of responses required to reach your next milestone: " + (self.currentstats.milestone - (self.currentstats.contributions % self.currentstats.milestone))).addClass("yesno-statsviewer-block-info"))
                                } 
                               viewer.append(p)
                              } 
                             
                             var o = $("<div></div>",{id:"yesno-statsviewer-overallstats"}).addClass("yesno-statsviewer-block")
                             o.append($("<div></div>").html("Overall task progress by all contributors").addClass("yesno-statsviewer-block-title"))
                             o.append($("<div></div>").html("Total number of images in the current task: " + self.currentstats.total).addClass("yesno-statsviewer-block-info"))
                             o.append($("<div></div>").html("Total number of responses: " + self.currentstats.responses).addClass("yesno-statsviewer-block-info"))
                             pctcomplete =((self.currentstats.completed/self.currentstats.total) * 100)
                             if(pctcomplete >= 1 )  pctcomplete = pctcomplete.toFixed(0)
                             else
                              {
                               if(pctcomplete > 0)
                                pctcomplete = "< 1"
                              }
                             o.append($("<div></div>").html("Total number completed questions: " + self.currentstats.completed + " (" + pctcomplete + "% complete)").addClass("yesno-statsviewer-block-info"))
                             o.append($("<div></div>").html("Number of responses required to complete a question: " + self.currentstats.threshold).addClass("yesno-statsviewer-block-info"))
                             o.append($("<div></div>").html("(A question can be configured in such a way that multiple responses must be obtained for an image before the answer is considered complete. By doing this it is possble to examine the consistency of responses in order to assess their reliability)").addClass("yesno-statsviewer-block-help"))
                             viewer.append(o)
                             o.append($("<div></div>").html("Number of contributors to this task: " + self.currentstats.contributors).addClass("yesno-statsviewer-block-info"))
                             
                             $("body").append(viewer)
                             viewer.position({my:"center",at:"center",of:self.interface})
                             var cb = $("<div></div>").attr("id","yesno-closehelpviewer").on("click",function(){$(this).parent().remove()})
                             var cbtext = $("<div></div>").css("padding-left","0.3em")
                             cb.append(cbtext)
                             viewer.append(cb)
                           },
              _endtask: function()
                          {
                           //Display a message indicating that all the questions have been answered - then quit when the message is closed
                           var self = this
                           var viewer = $("<div></div>",{class:"yesno-helpviewer"})
                           var c = $("<div></div>",{id:"yesno-statsviewer-congrats"}).addClass("yesno-statsviewer-block")
                           var t = $("<div>Congratulations and Thank You!</div>").addClass("yesno-statsviewer-ctitle") 
                           c.append(t)
                           var m = $("<div>All the images in the '" + self.question.text() + "' project have now be processed.</div>").addClass("yesno-statsviewer-cinfo")
                           c.append(m)
                           var m = $("<div>The '" + self.question.text() + "' project is now closed.</div>").addClass("yesno-statsviewer-cinfo")
                           c.append(m)
                           viewer.append(c) 
                           $("body").append(viewer)
                           viewer.position({my:"center",at:"center",of:self.interface})
                           var cb = $("<div></div>").attr("id","yesno-closehelpviewer").on("click",function(){$(this).parent().remove(),self._quit()})
                           var cbtext = $("<div></div>").css("padding-left","0.3em")
                           cb.append(cbtext)
                           viewer.append(cb)
                          },             
              _quit: function()
                         {
                          this._clearZoomCache()
                          this.interface.remove()
                          if(!this.options.objectid) 
                           window.location = "../yesnolist"
                         }                   
                                 
          }              
 )})(jQuery)