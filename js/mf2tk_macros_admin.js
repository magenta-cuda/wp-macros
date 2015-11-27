jQuery(document).ready(function(){
    // wire up the "Save as Template" button
    jQuery("button#tti_iii-save_as_template").click(function(){
        // use AJAX to request the server to create the Content Template
        var slug=jQuery("div#slugdiv input#post_name").val();
        var title=jQuery("div#post-body-content div#titlediv input#title").val();
        var text=jQuery("div#post-body-content div#wp-content-editor-container textarea#content").val();
        jQuery.post(ajaxurl,{action:'mf2tk_update_content_macro',slug:slug,title:title,text:text,nonce:this.dataset.nonce},function(r){
            alert(r);
        });
    });
    
    // div#tti_iii-popup_margin is semi-opaque full browser window background used to surround the popup
    var divPopupOuter=jQuery("div#tti_iii-popup_margin");
    
    // Insert Template popup
    
    // connect "Insert Template" popup HTML elements to their JavaScript code
    var divTemplate=jQuery("div#mf2tk-alt-template");
    divTemplate.find("button#button-mf2tk-alt-template-close").click(function(){
        divTemplate.hide();
        divPopupOuter.hide();
    });
    var select=divTemplate.find("select#mf2tk-alt_template-select");
    // mf2tk_globals.mf2tk_alt_template.templates is the content template database defined in another script
    var templates=mf2tk_globals.mf2tk_alt_template.templates;
    // create the options for the select
    Object.keys(templates).forEach(function(k){
        var option=document.createElement("option");
        option.value=k;
        option.textContent=templates[k].title;
        select.append(option);
    });
    // when the selection changes update the "how to use" input element and the template definition textarea
    select.change(function(){
        var select=this;
        // get the macro definition
        var template=mf2tk_globals.mf2tk_alt_template.templates[select.value].content;
        // find template variables in the template definition
        var matches=template.match(/\$#(\w+)#/g);
        var parms={};
        if(matches){matches.forEach(function(v){parms[v]=true;});}
        // find assigned template variables
        var assigneds=[];
        var assigned;
        // find assignments using HTML comments
        var assignedRe=/(<|&lt;)!--\s*(\$#\w+#)\s*=/g;
        while((assigned=assignedRe.exec(template))!==null){
            assigneds.push(assigned[2]);
        }
        // find iterator assignments
        assignedRe=/\s(iterator|it)=("|&quot;)(\w+):/g;
        while((assigned=assignedRe.exec(template))!==null){
            assigneds.push("$#"+assigned[3]+"#");
        }
        // find assignments using shortcode attributes
        var shortcodes=template.match(new RegExp("\\[("+mf2tk_globals.mf2tk_alt_template.shortcode+"|"
            +mf2tk_globals.mf2tk_alt_template.shortcode_alias+")\\s.*?\\]","g"));
        if(shortcodes){
            shortcodes.forEach(function(shortcode){
                assignedRe=/\s(\w+)=("|&quot;)/g;
                while((assigned=assignedRe.exec(shortcode))!==null){
                    assigneds.push("$#"+assigned[1]+"#");
                }
            });
        }
        // get the macro slug
        var macro='['+mf2tk_globals.mf2tk_alt_template.shortcode+' '+mf2tk_globals.mf2tk_alt_template.name+'="'
          +select.value+'"';
        // add the parameters for free template variables
        for(parm in parms){
            if(assigneds.indexOf(parm)!==-1){continue;}
            macro+=" "+parm.slice(2,-1)+'=""';
        }
        macro+="][/"+mf2tk_globals.mf2tk_alt_template.shortcode+"]";
        // update the "how to use" input element
        var parent=select.parentNode.parentNode.parentNode;
        parent.querySelector("input#mf2tk-alt_template-post_name").value=macro;
        // update the macro definition textarea element
        parent.querySelector("textarea#mf2tk-alt_template-post_content").innerHTML=template;
    });
    // "how to use" button
    divTemplate.find("button.mf2tk-how-to-use").click(function(){
        jQuery(this.parentNode).find("input.mf2tk-how-to-use")[0].select();
        return false;
    });
    // open/hide template source button
    divTemplate.find("button.mf2tk-field_value_pane_button").click(function(){
        if(jQuery(this).text()=="Open"){
            jQuery(this).text("Hide");
            jQuery(this.parentNode).find("div.mf2tk-field_value_pane").css("display","block");
        }else{
            jQuery(this).text("Open");
            jQuery(this.parentNode).find("div.mf2tk-field_value_pane").css("display","none");
        }
        return false;
    });
    // wire up the "Insert Template" button
    jQuery("button#tti_iii-insert_template").click(function(){
        divPopupOuter.show();
        divTemplate.show();
        divTemplate.find("select#mf2tk-alt_template-select").change();
    });
    
    // Shortcode Tester popup
    
    // connect "Shortcode Tester" popup HTML elements to their JavaScript code
    var divShortcode=jQuery("div#mf2tk-shortcode-tester");
    // "Shortcode Tester" close button
    divShortcode.find("button#button-mf2tk-shortcode-tester-close").click(function(){
        divShortcode.hide();
        divPopupOuter.hide();
    });
    // "Shortcode Tester" evaluate button
    divShortcode.find("button#mf2tk-shortcode-tester-evaluate").click(function(){
        var post_id=jQuery("form#post input#post_ID[type='hidden']").val();
        var source=jQuery("div#mf2tk-shortcode-tester div#mf2tk-shortcode-tester-area-source textarea").val();
        var button=jQuery("button#tti_iii-shortcode-tester");
        jQuery("div#mf2tk-shortcode-tester div#mf2tk-shortcode-tester-area-result textarea").val("Evaluating..., please wait...");
        // Use AJAX to request the server to evaluate the post content fragment
        jQuery.post(ajaxurl,{action:'tpcti_eval_post_content',post_id:post_id,post_content:source,nonce:button[0].dataset.nonce},function(r){
            jQuery("div#mf2tk-shortcode-tester div#mf2tk-shortcode-tester-area-result textarea").val(r.trim());
        });
    });
    // "Shortcode Tester" show both source and result button
    divShortcode.find("button#mf2tk-shortcode-tester-show-both").click(function(){
        divShortcode.find("div.mf2tk-shortcode-tester-half")
            .css({display:"block",width:"50%",padding:"0",margin:"0",float:"left"})
    });
    // "Shortcode Tester" show source only button
    divShortcode.find("button#mf2tk-shortcode-tester-show-source").click(function(){
        divShortcode.find("div#mf2tk-shortcode-tester-area-source").parent()
            .css({display:"block",width:"99%",float:"none","margin-left":"auto","margin-right":"auto"});
        divShortcode.find("div#mf2tk-shortcode-tester-area-result").parent().css("display","none");
    });
    // "Shortcode Tester" show result only button
    divShortcode.find("button#mf2tk-shortcode-tester-show-result").click(function(){
        divShortcode.find("div#mf2tk-shortcode-tester-area-source").parent().css("display","none");
        divShortcode.find("div#mf2tk-shortcode-tester-area-result").parent()
            .css({display:"block",width:"99%",float:"none","margin-left":"auto","margin-right":"auto"});
    });
    // wire up the "Shortcode Tester" button
    jQuery("button#tti_iii-shortcode-tester").click(function(){
        divShortcode.find("div#mf2tk-shortcode-tester-area-source textarea").val("");
        divShortcode.find("div#mf2tk-shortcode-tester-area-result textarea").val("");
        divPopupOuter.show();
        divShortcode.show();
    });
});
