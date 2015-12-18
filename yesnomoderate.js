 /**
 *Jquery widget which drives all yes no module moderating activities
 *Wrapped in a self invoking function to ensure $ is available within the widget 
 */
(function($) 
 {
  $.widget( "yesno.yesnomoderate",
     {
       options:{
                 drupalnode: "",
                 viewmode: 1,
               },
       _create: function()
                   {
                     var self = this
                     //Bind the event handler to the Accept and reject buttons
                     $(".yesno-button-accept",this.element).on("click", function(e){self._accept(e)})
                     $(".yesno-button-reject",this.element).on("click", function(e){self._reject(e)})
                     $(".yesno-button-review",this.element).on("click", function(e){self._review(e)})
                     
                     //Bind the eevnt handler to the response radio buttons 
                     $("input",this.element).on("click",function(e){self._radio_click(e)})
                   },
      
      //Changes the appearance of a radio button in response to a click - helps hightligh which option is current selected for acceptance                             
       _radio_click: function(e)
                         {
                          //Remove all the type classes
                          $(".yesno-moderate-entry-element",this.element).removeClass("yesno-moderate-label-type1").removeClass("yesno-moderate-label-type-1").removeClass("yesno-moderate-label-type0")
                          var t = $(e.target) 
                          console.log(t.val())
                          var newclass = "yesno-moderate-label-type" + t.val() 
                          console.log("ne class = " + newclass)
                          t.parent().addClass(newclass)
                         },
                                               
       _accept: function(e)
                   {
                    var self = this
                    var t = $(e.target)
                    
                    //Make sure that there is something selected
                    var selection = $(".yesno-moderate-entry-element input:checked", this.element)
                    
                    
                    if(!selection.length)
                     alert("Since there is an equal number of counts for each category you must manually select a response before you can accept it")
                    else 
                     {
                       var url = "../yesnoacceptanswer/" + self.options.drupalnode  + "/" + $(this.element).attr("data-objectid") + "/" + selection.val()
                       var acceptlabel = ""
                       switch(selection.val())
                        {
                         case "1": acceptlabel = "Yes"; break;
                         case "0": acceptlabel = "Don't know"; break;
                         case "-1": acceptlabel = "No"; break;
                        }
                       $.ajax({
                                url: url,
                                dataType: "json",
                                success: function(data)
                                            {
                                              switch(data.status)
                                               {
                                                 case 0: alert(data.message); break;
                                                 case 1: //Remove the buttons and replace with some text
                                                         t.parent().empty().append("<span>Answer accepted as " + acceptlabel + "</span>") 
                                                 case -1: alert("Your accept request has been partially successful. The local records have been updated but the autoupdate of the external data base has failed for the following reason." + data.message)        
                                                          t.parent().empty().append("<span>Answer accepted as " + acceptlabel + "</span>")   
                                               }
                                            }
                               })
                     }
                   },
                         
       _reject: function(e,ok)        
                    {
                     var self = this
                     if(!ok)
                      {
                       var dlg = $("<div>You are about to reject an answer. If you continue all existing responses for this answer for this object will be erased and the object will once more become available for proceessing through the main interface</div>").dialog({
                                                       title: "Are you sure you wish to reject this answer",
                                                       buttons: [
                                                                  {text: "Yes", click:function(){
                                                                                                 dlg.dialog("destroy")
                                                                                                 self._reject(e,true)
                                                                                               }},
                                                                  {text: "No",click:function(){dlg.dialog("destroy")}} 
                                                                ]
                                                     }).append("<div style=\"margin-top: 5px;\">Do you wish to continue?</div>")
                       return;                              
                     }                               
                     
                     //If we get this far we have been given the OK to continue by the moderator
                     var url = "../yesnorejectanswer/" + self.options.drupalnode  + "/" + $(this.element).attr("data-objectid")
                     var t = $(e.target)
                     $.ajax({
                              url: url,
                              dataType: "json",
                              success: function(data)
                                         {
                                                switch(data.status)
                                                 {
                                                   case 0: alert(data.message); break;
                                                   case 1: //Remove the buttons and replace with some text
                                                           var objectid = $(self.element).attr("data-objectid")
                                                           t.parents(".yesno-moderate-entry").empty().append("<span>Answer for " + objectid + " rejected</span>") 
                                                 }       
                                         } 
                            })
                     
                    },
                               
       _review: function(e)
                         {
                           var self = this
                           //This should instantiate a yesno interface in review mode and destroy on exit
                           var interface =  $("<div id=\"yesno-interfacecontainer\"></div>")
                           $("body").append(interface)
                           interface.yesnoform({
                                                objectid:  $(this.element).attr("data-objectid"),
                                                drupalnode: self.options.drupalnode,
                                                viewmode: self.options.viewmode,
                                                aspectratio: self.options.aspectratio
                                               })
                           
                         }                            
                                               
     })
 })(jQuery)
 