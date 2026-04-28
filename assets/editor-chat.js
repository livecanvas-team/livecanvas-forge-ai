(function(){
  var shell=document.querySelector("[data-lcfa-editor-shell]");
  if(!shell||shell.dataset.bound==="1"){return;}
  shell.dataset.bound="1";

  var drawer=shell.querySelector(".lcfa-editor-drawer");
  var openBtn=shell.querySelector("[data-lcfa-editor-open]");
  var closeBtn=shell.querySelector("[data-lcfa-editor-close]");
  var configNode=shell.querySelector("[data-lcfa-editor-config]")||document.querySelector("[data-lcfa-editor-config]");
  var threadSelect=shell.querySelector("[data-lcfa-editor-thread]");
  var targetSelect=shell.querySelector("[data-lcfa-editor-target]");
  var promptInput=shell.querySelector("[data-lcfa-editor-prompt]");
  var analyzeButton=shell.querySelector("[data-lcfa-editor-analyze]");
  var createThreadButton=shell.querySelector("[data-lcfa-editor-thread-create]");
  var duplicateThreadButton=shell.querySelector("[data-lcfa-editor-thread-duplicate]");
  var renameThreadButton=shell.querySelector("[data-lcfa-editor-thread-rename]");
  var clearThreadButton=shell.querySelector("[data-lcfa-editor-thread-clear]");
  var deleteThreadButton=shell.querySelector("[data-lcfa-editor-thread-delete]");
  var openDeckLink=shell.querySelector("[data-lcfa-editor-open-deck]");
  var attachmentInput=shell.querySelector("[data-lcfa-editor-attachment]");
  var attachmentTriggerButton=shell.querySelector("[data-lcfa-editor-attachment-trigger]");
  var attachmentClearButton=shell.querySelector("[data-lcfa-editor-attachment-clear]");
  var attachmentPreview=shell.querySelector("[data-lcfa-editor-attachment-preview]");
  var attachmentPreviewImage=shell.querySelector("[data-lcfa-editor-attachment-preview-image]");
  var attachmentPreviewMeta=shell.querySelector("[data-lcfa-editor-attachment-preview-meta]");
  var supportDetails=shell.querySelector("[data-lcfa-editor-support-details]");
  var resultBox=shell.querySelector("[data-lcfa-editor-result]");
  var resultSummary=shell.querySelector("[data-lcfa-editor-result-summary]");
  var resultMeta=shell.querySelector("[data-lcfa-editor-result-meta]");
  var statusNode=shell.querySelector("[data-lcfa-editor-status]");
  var threadLog=shell.querySelector("[data-lcfa-editor-thread-log]");
  var threadEmpty=shell.querySelector("[data-lcfa-editor-thread-empty]");
  var reasonsWrap=shell.querySelector("[data-lcfa-editor-result-reasons-wrap]");
  var reasonsList=shell.querySelector("[data-lcfa-editor-result-reasons]");
  var warningsWrap=shell.querySelector("[data-lcfa-editor-result-warnings-wrap]");
  var warningsList=shell.querySelector("[data-lcfa-editor-result-warnings]");
  var workflowWrap=shell.querySelector("[data-lcfa-editor-result-workflow-wrap]");
  var workflowList=shell.querySelector("[data-lcfa-editor-result-workflow]");
  var preflightWrap=shell.querySelector("[data-lcfa-editor-result-preflight-wrap]");
  var preflightNode=shell.querySelector("[data-lcfa-editor-result-preflight]");
  var diffWrap=shell.querySelector("[data-lcfa-editor-result-diff-wrap]");
  var diffNode=shell.querySelector("[data-lcfa-editor-result-diff]");
  var existingWrap=shell.querySelector("[data-lcfa-editor-result-existing-wrap]");
  var existingNode=shell.querySelector("[data-lcfa-editor-result-existing]");
  var proposedWrap=shell.querySelector("[data-lcfa-editor-result-proposed-wrap]");
  var proposedNode=shell.querySelector("[data-lcfa-editor-result-proposed]");

  var config={};
  try{config=configNode?JSON.parse(configNode.textContent||"{}"):{};}catch(error){config={};}

  var suggestionPayload=null;
  var previewedSuggestionKey="";
  var selectedThreadId=(config.threadId||"default");
  var attachmentState=null;
  var selectedSectionAnchor=null;
  var analyzeBusy=false;
  var actionBusyMode="";

  var getAgentConfig=function(){
    return config.agent&&typeof config.agent==="object"?config.agent:{};
  };

  var getAgentLabel=function(){
    var agent=getAgentConfig();
    return String(agent.displayLabel||agent.client||"Coding agent");
  };

  var isActiveConversationState=function(state){
    return state==="thinking"||state==="queueing"||state==="running";
  };

  var getAgentLoaderIcon=function(){
    var connectionMedia=shell.querySelector(".lcfa-editor-bridge__connection-media");
    if(connectionMedia&&connectionMedia.children&&connectionMedia.children.length&&typeof connectionMedia.children[0].cloneNode==="function"){
      return connectionMedia.children[0].cloneNode(true);
    }
    var fallback=document.createElement("span");
    fallback.className="lcfa-editor-bridge__agent-loader-letter";
    fallback.textContent=(getAgentLabel().charAt(0)||"A").toUpperCase();
    return fallback;
  };

  var renderActiveConversationStatus=function(label){
    var loader=document.createElement("span");
    loader.className="lcfa-editor-bridge__agent-loader";
    loader.setAttribute("aria-hidden","true");

    var icon=document.createElement("span");
    icon.className="lcfa-editor-bridge__agent-loader-icon";
    icon.appendChild(getAgentLoaderIcon());
    loader.appendChild(icon);

    var labelNode=document.createElement("span");
    labelNode.className="lcfa-editor-bridge__thread-status-label";
    labelNode.textContent=label;

    statusNode.appendChild(loader);
    statusNode.appendChild(labelNode);
  };

  var isAgentQueueEnabled=function(){
    var agent=getAgentConfig();
    return !!(config.agentRequestEndpoint&&agent.enabled&&agent.client);
  };

  var getFrontendProvenance=function(){
    var agent=getAgentConfig();
    if(isAgentQueueEnabled()){
      return {
        _lcfa_origin:"frontend_bridge",
        _lcfa_transport:"browser_rest",
        _lcfa_agent:String(agent.client||""),
        _lcfa_processed_by:"agent_queue"
      };
    }
    return {
      _lcfa_origin:"frontend_bridge",
      _lcfa_transport:"browser_rest",
      _lcfa_agent:"forge",
      _lcfa_processed_by:"forge_local_rules"
    };
  };

  var withFrontendProvenance=function(payload){
    return Object.assign({},payload||{},getFrontendProvenance());
  };

  var escapeHtml=function(value){
    return String(value||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/\"/g,"&quot;");
  };

  var getButtonLabelNode=function(button){
    if(!button||!button.children||!button.children.length){return null;}
    return button.children[button.children.length-1]||null;
  };

  var getStorageKey=function(){
    return "lcfa-editor-thread:"+(config.postId||"global");
  };

  var getPersistedThreadId=function(){
    if(!window.localStorage){return "";}
    try{return String(window.localStorage.getItem(getStorageKey())||"");}catch(error){return "";}
  };

  var persistThreadId=function(threadId){
    if(!window.localStorage){return;}
    try{window.localStorage.setItem(getStorageKey(),threadId);}catch(error){}
  };

  var getThreadById=function(threadId){
    if(!config.threads||typeof config.threads!=="object"){return null;}
    return config.threads[threadId]||null;
  };

  var cacheThread=function(thread){
    if(!thread||typeof thread!=="object"||!thread.id){return;}
    if(!config.threads||typeof config.threads!=="object"){config.threads={};}
    config.threads[thread.id]=thread;
  };

  var rebuildThreadSelect=function(summaries){
    if(!threadSelect||!Array.isArray(summaries)){return;}
    threadSelect.innerHTML="";
    summaries.forEach(function(summary){
      if(!summary||!summary.id){return;}
      var option=document.createElement("option");
      option.value=summary.id;
      option.textContent=summary.title||summary.id;
      if(option.value===selectedThreadId){
        option.selected=true;
        threadSelect.value=option.value;
      }
      threadSelect.appendChild(option);
    });
  };

  var setSelectedThreadId=function(threadId){
    selectedThreadId=String(threadId||config.threadId||"default");
    config.threadId=selectedThreadId;
    if(threadSelect){threadSelect.value=selectedThreadId;}
    persistThreadId(selectedThreadId);
  };

  var setOpen=function(nextOpen){
    shell.classList.toggle("is-open",Boolean(nextOpen));
    if(drawer){drawer.setAttribute("aria-hidden",nextOpen?"false":"true");}
  };

  var setConversationState=function(state,customLabel){
    if(!statusNode){return;}
    var label=customLabel||"";
    if(label===""){
      if(state==="thinking"){label=(config.labels&&config.labels.thinkingState)||"Analyzing request...";}
      else if(state==="queueing"){label=(config.labels&&config.labels.queuedState)||"Queued for inline execution.";}
      else if(state==="running"){label=(config.labels&&config.labels.runningState)||"Running inline execution...";}
      else if(state==="suggested"){label=(config.labels&&config.labels.suggestedState)||"Suggestion ready. Review it or run it inline.";}
      else if(state==="previewed"){label=(config.labels&&config.labels.previewedState)||"Preview ready. Review the support details below.";}
      else if(state==="applied"){label=(config.labels&&config.labels.appliedState)||"Inline action completed.";}
      else if(state==="failed"){label=(config.labels&&config.labels.failedState)||"The current request failed. Review the support details below.";}
      else{label=(config.labels&&config.labels.idleState)||"Ready for a new request.";}
    }
    statusNode.setAttribute("data-state",state||"idle");
    statusNode.innerHTML="";
    if(isActiveConversationState(state)){
      renderActiveConversationStatus(label);
    }else{
      statusNode.textContent=label;
    }
  };

  var getLatestConversationState=function(thread){
    if(!thread||!Array.isArray(thread.messages)||thread.messages.length===0){return "idle";}
    var latestMessage=thread.messages[thread.messages.length-1]||{};
    var role=String(latestMessage.role||"");
    var meta=latestMessage.meta&&typeof latestMessage.meta==="object"?latestMessage.meta:{};
    if(role==="suggestion_result"){return "suggested";}
    if(role==="tool_result"){
      if(Object.prototype.hasOwnProperty.call(meta,"ok")&&!meta.ok){return "failed";}
      if((meta.mode||"")==="preview"||(meta.mode||"")==="dry_run"){return "previewed";}
      return "applied";
    }
    return "idle";
  };

  var renderList=function(listNode,wrapNode,items){
    if(!listNode||!wrapNode){return;}
    listNode.innerHTML="";
    if(!Array.isArray(items)||items.length===0){
      wrapNode.hidden=true;
      return;
    }
    items.forEach(function(item){
      var li=document.createElement("li");
      li.textContent=String(item||"");
      listNode.appendChild(li);
    });
    wrapNode.hidden=false;
  };

  var renderWorkflow=function(items){
    renderList(workflowList,workflowWrap,items);
  };

  var renderJson=function(node,wrap,value){
    if(!node||!wrap){return;}
    if(!value||typeof value!=="object"){
      node.textContent="";
      wrap.hidden=true;
      return;
    }
    node.textContent=JSON.stringify(value,null,2);
    wrap.hidden=false;
  };

  var renderMarkupPane=function(node,wrap,value,isHtml){
    if(!node||!wrap){return;}
    var content=String(value||"");
    if(content===""){
      wrap.hidden=true;
      if(isHtml){node.innerHTML="";}else{node.textContent="";}
      return;
    }
    if(isHtml){node.innerHTML=content;}
    else{node.textContent=content;}
    wrap.hidden=false;
  };

  var resetSupportDetails=function(){
    if(resultBox){
      resultBox.classList.remove("is-visible");
      resultBox.classList.remove("is-error");
    }
    if(supportDetails){supportDetails.open=false;}
    if(resultSummary){resultSummary.textContent="";}
    if(resultMeta){resultMeta.innerHTML="";}
    renderList(reasonsList,reasonsWrap,[]);
    renderList(warningsList,warningsWrap,[]);
    renderWorkflow([]);
    renderJson(preflightNode,preflightWrap,null);
    renderMarkupPane(diffNode,diffWrap,"",true);
    renderMarkupPane(existingNode,existingWrap,"",false);
    renderMarkupPane(proposedNode,proposedWrap,"",false);
  };

  var getSuggestionKey=function(payload){
    var applyPayload=buildApplyPayload(payload);
    if(!applyPayload||typeof applyPayload!=="object"||!applyPayload.action){return "";}
    return JSON.stringify(applyPayload);
  };

  var syncComposerButtons=function(){
    var hasPrompt=!!(promptInput&&promptInput.value&&promptInput.value.trim()!=="");
    var hasActionBusy=actionBusyMode==="preview"||actionBusyMode==="apply";

    if(analyzeButton){analyzeButton.disabled=analyzeBusy||hasActionBusy||!hasPrompt;}
  };

  var setSuggestionState=function(payload,isBusy,mode){
    var previousKey=getSuggestionKey(suggestionPayload);
    suggestionPayload=payload&&typeof payload==="object"?payload:null;
    var nextKey=getSuggestionKey(suggestionPayload);
    if(!suggestionPayload){
      previewedSuggestionKey="";
    }else if(previousKey!==nextKey){
      previewedSuggestionKey="";
    }
    actionBusyMode=isBusy?String(mode||""):"";
    syncComposerButtons();
  };

  var updateDeckLink=function(payload){
    if(!openDeckLink||!config.commandBaseUrl){return;}
    var params=[
      ["suggest_action",payload&&payload.action?payload.action:(config.defaultAction||"site_audit")],
      ["thread_id",selectedThreadId||config.threadId||"default"],
      ["post_id",String(config.postId||0)]
    ];
    if(payload&&payload.execution_target){params.push(["execution_target",payload.execution_target]);}
    if(payload&&payload.target_id){params.push(["target_id",String(payload.target_id)]);}
    if(payload&&payload.variant){params.push(["variant",payload.variant]);}
    var serialized=params.map(function(entry){
      return encodeURIComponent(entry[0])+"="+encodeURIComponent(entry[1]);
    }).join("&");
    openDeckLink.href=config.commandBaseUrl+(config.commandBaseUrl.indexOf("?")===-1?"?":"&")+serialized;
  };

  var normalizeAnchorToken=function(value){
    return String(value||"").replace(/[^a-zA-Z0-9_\-:.]/g,"").slice(0,96);
  };

  var getSectionIndex=function(element){
    if(!element||!element.parentNode||!element.parentNode.children){return -1;}
    var index=0;
    for(var i=0;i<element.parentNode.children.length;i+=1){
      var child=element.parentNode.children[i];
      if(!child||!child.tagName){continue;}
      if(String(child.tagName).toLowerCase()==="section"){
        if(child===element){return index;}
        index+=1;
      }
    }
    return -1;
  };

  var findSectionElement=function(node){
    var current=node;
    while(current&&current!==document){
      if(current.tagName){
        var tagName=String(current.tagName).toLowerCase();
        if(tagName==="section"||tagName==="header"||tagName==="footer"||tagName==="article"){
          return current;
        }
      }
      current=current.parentNode||null;
    }
    return null;
  };

  var describeSectionAnchor=function(node){
    var element=findSectionElement(node);
    if(!element){return null;}
    var tagName=String(element.tagName||"section").toLowerCase();
    var id=normalizeAnchorToken(element.id||(typeof element.getAttribute==="function"?element.getAttribute("id"):""));
    var className=String(element.className||(typeof element.getAttribute==="function"?element.getAttribute("class"):"")||"");
    var classTokens=className.split(/\s+/).map(normalizeAnchorToken).filter(Boolean);
    var semanticClass="";
    classTokens.some(function(token){
      if(token.indexOf("lcfa-section--")===0){
        semanticClass=token;
        return true;
      }
      return false;
    });
    if(semanticClass===""){
      classTokens.some(function(token){
        if(token.indexOf("section-")===0||token.indexOf("lc-section")===0){
          semanticClass=token;
          return true;
        }
        return false;
      });
    }
    var index=getSectionIndex(element);
    var selector=id!==""?"#"+id:(semanticClass!==""?tagName+"."+semanticClass:"");
    return {
      tag_name:tagName,
      id:id,
      selector:selector,
      class_token:semanticClass,
      section_index:index,
      source:"editor_click"
    };
  };

  var getSelectedSectionAnchor=function(){
    return selectedSectionAnchor&&typeof selectedSectionAnchor==="object"?Object.assign({},selectedSectionAnchor):null;
  };

  var renderAttachmentPreview=function(){
    if(!attachmentPreview||!attachmentPreviewMeta||!attachmentClearButton){return;}
    if(!attachmentState||!attachmentState.data_url){
      attachmentPreview.hidden=true;
      attachmentPreviewMeta.textContent="";
      if(attachmentPreviewImage){
        attachmentPreviewImage.hidden=true;
        attachmentPreviewImage.src="";
        attachmentPreviewImage.alt="";
      }
      attachmentClearButton.hidden=true;
      return;
    }
    attachmentPreview.hidden=false;
    if(attachmentPreviewImage){
      attachmentPreviewImage.hidden=false;
      attachmentPreviewImage.src=attachmentState.data_url;
      attachmentPreviewImage.alt=attachmentState.name||"";
    }
    attachmentPreviewMeta.textContent=[attachmentState.name||"",attachmentState.caption||((config.labels&&config.labels.screenshotReady)||"Image attached to this request.")].filter(Boolean).join(" • ");
    attachmentClearButton.hidden=false;
  };

  var loadAttachmentFile=function(file){
    if(!file){attachmentState=null;renderAttachmentPreview();return Promise.resolve();}
    return readAttachmentFile(file).then(function(attachment){
      attachmentState=attachment;
      renderAttachmentPreview();
    }).catch(function(){
      attachmentState=null;
      renderAttachmentPreview();
    });
  };

  var readAttachmentFile=function(file){
    return new Promise(function(resolve,reject){
      if(!file||!(file.type||"").match(/^image\//)){reject(new Error("invalid-file"));return;}
      var reader=new FileReader();
      reader.onload=function(event){
        var attachment={
          kind:"image",
          name:file.name||"reference.png",
          mime:file.type||"image/png",
          size:file.size||0,
          caption:"",
          data_url:(event&&event.target&&event.target.result)||reader.result||"",
        };
        if(!window.Image||!attachment.data_url){
          resolve(attachment);
          return;
        }
        var image=new window.Image();
        image.onload=function(){
          var width=Number(image.naturalWidth||image.width||0);
          var height=Number(image.naturalHeight||image.height||0);
          if(width>0&&height>0){
            attachment.width=width;
            attachment.height=height;
            attachment.aspect_ratio=Math.round((width/height)*1000)/1000;
            attachment.orientation=width>height?"landscape":(height>width?"portrait":"square");
          }
          resolve(attachment);
        };
        image.onerror=function(){resolve(attachment);};
        image.src=attachment.data_url;
      };
      reader.onerror=function(){reject(new Error("read-failed"));};
      reader.readAsDataURL(file);
    });
  };

  var renderThreadMessage=function(message){
    var article=document.createElement("article");
    var role=String(message&&message.role||"assistant");
    if(role==="suggestion_result"){return null;}
    article.className="lcfa-editor-thread-message is-"+role;

    var head=document.createElement("div");
    head.className="lcfa-editor-thread-message__head";
    var label=document.createElement("span");
    label.textContent=String(message&&message.label||role);
    var time=document.createElement("span");
    time.textContent=String(message&&message.time||"");
    head.appendChild(label);
    head.appendChild(time);
    article.appendChild(head);

    var body=document.createElement("pre");
    body.className="lcfa-editor-thread-message__body";
    body.textContent=String(message&&message.content||"");
    article.appendChild(body);

    if(Array.isArray(message&&message.attachments)&&message.attachments.length){
      var attachmentsWrap=document.createElement("div");
      attachmentsWrap.className="lcfa-editor-thread-message__attachments";
      message.attachments.forEach(function(attachment){
        if(!attachment||attachment.kind!=="image"||!attachment.data_url){return;}
        var figure=document.createElement("figure");
        figure.className="lcfa-editor-thread-message__attachment";
        var image=document.createElement("img");
        image.className="lcfa-editor-thread-message__attachment-image";
        image.src=attachment.data_url;
        var caption=document.createElement("figcaption");
        caption.className="lcfa-editor-thread-message__attachment-copy";
        caption.textContent=[attachment.name||"",attachment.caption||""].filter(Boolean).join(" • ");
        figure.appendChild(image);
        if(caption.textContent!==""){figure.appendChild(caption);}
        attachmentsWrap.appendChild(figure);
      });
      if(attachmentsWrap.children.length){article.appendChild(attachmentsWrap);}
    }

    if(role!=="suggestion_result"&&Array.isArray(message&&message.actions)&&message.actions.length){
      var actions=document.createElement("div");
      actions.className="lcfa-editor-thread-message__actions";
      message.actions.forEach(function(action){
        if(!action||!action.label){return;}
        if(action.kind==="apply"&&action.payload){
          var button=document.createElement("button");
          button.type="button";
          button.className="lcfa-editor-thread-message__link";
          button.setAttribute("data-lcfa-editor-thread-apply",JSON.stringify(action.payload));
          button.textContent=action.label;
          actions.appendChild(button);
          return;
        }
        if(action.kind==="url"&&action.url){
          var link=document.createElement("a");
          link.className="lcfa-editor-thread-message__link";
          link.href=action.url;
          link.target="_blank";
          link.rel="noreferrer noopener";
          link.textContent=action.label;
          actions.appendChild(link);
        }
      });
      if(actions.children.length){article.appendChild(actions);}
    }

    return article;
  };

  var renderThread=function(thread){
    if(!threadLog||!threadEmpty){return;}
    threadLog.innerHTML="";
    var messages=Array.isArray(thread&&thread.messages)?thread.messages:[];
    var renderedCount=0;
    messages.slice().reverse().forEach(function(message){
      var node=renderThreadMessage(message);
      if(!node){return;}
      threadLog.appendChild(node);
      renderedCount+=1;
    });
    threadEmpty.hidden=renderedCount>0;
    cacheThread(thread);
    setConversationState(getLatestConversationState(thread));
  };

  var hydrateSelectedThread=function(){
    var thread=getThreadById(selectedThreadId);
    if(!thread){
      setConversationState("idle");
      return;
    }
    renderThread(thread);
  };

  var renderSuggestion=function(payload,isError){
    if(!resultBox||!resultSummary||!resultMeta){return;}
    resultBox.classList.add("is-visible");
    resultBox.classList.toggle("is-error",Boolean(isError));
    if(supportDetails){supportDetails.open=true;}
    resultMeta.innerHTML="";
    resultSummary.textContent=payload&&payload.summary?payload.summary:(payload&&payload.message?payload.message:"");
    if(payload&&payload.suggested_payload&&payload.suggested_payload.action){
      setSuggestionState(payload.suggested_payload,false,"");
      [
        {label:"Action",value:payload.suggested_payload.action},
        {label:"Confidence",value:payload.confidence||""},
        {label:"Execution",value:payload.suggested_payload.execution_target||""}
      ].forEach(function(entry){
        if(!entry.value){return;}
        var chip=document.createElement("span");
        chip.className="lcfa-editor-bridge__chip";
        chip.textContent=entry.label+": "+entry.value;
        resultMeta.appendChild(chip);
      });
      updateDeckLink(payload.suggested_payload);
    }else{
      setSuggestionState(null,false,"");
    }
    renderList(reasonsList,reasonsWrap,payload&&Array.isArray(payload.reasons)?payload.reasons:[]);
    renderList(warningsList,warningsWrap,payload&&Array.isArray(payload.warnings)?payload.warnings:[]);
    renderWorkflow(payload&&Array.isArray(payload.workflow)?payload.workflow:[]);
    renderJson(preflightNode,preflightWrap,payload&&payload.preflight&&typeof payload.preflight==="object"?payload.preflight:null);
    renderMarkupPane(diffNode,diffWrap,"",true);
    renderMarkupPane(existingNode,existingWrap,"",false);
    renderMarkupPane(proposedNode,proposedWrap,"",false);
    setConversationState(isError?"failed":(payload&&payload.suggested_payload&&payload.suggested_payload.action?"suggested":"idle"));
  };

  var renderExecutionResult=function(result,isError){
    if(!resultBox||!resultSummary||!resultMeta){return;}
    resultBox.classList.add("is-visible");
    resultBox.classList.toggle("is-error",Boolean(isError));
    if(supportDetails){supportDetails.open=true;}
    resultMeta.innerHTML="";
    var provenance=result&&result.provenance&&typeof result.provenance==="object"?result.provenance:{};
    resultSummary.textContent=(result&&result.summary?result.summary:(result&&result.message?result.message:""));
    [
      {label:"Action",value:result&&result.action?result.action:""},
      {label:"Mode",value:result&&result.mode?result.mode:""},
      {label:"Execution",value:result&&result.execution_target?result.execution_target:""},
      {label:"Origin",value:provenance&&provenance.origin?provenance.origin:""},
      {label:"Transport",value:provenance&&provenance.transport?provenance.transport:""},
      {label:"Agent",value:provenance&&provenance.agent?provenance.agent:""},
      {label:"Processor",value:provenance&&provenance.processed_by?provenance.processed_by:""}
    ].forEach(function(entry){
      if(!entry.value){return;}
      var chip=document.createElement("span");
      chip.className="lcfa-editor-bridge__chip";
      chip.textContent=entry.label+": "+entry.value;
      resultMeta.appendChild(chip);
    });
    renderList(reasonsList,reasonsWrap,[]);
    renderList(warningsList,warningsWrap,result&&Array.isArray(result.warnings)?result.warnings:[]);
    renderWorkflow([]);
    renderJson(preflightNode,preflightWrap,null);
    renderMarkupPane(diffNode,diffWrap,result&&result.diff_html?result.diff_html:"",true);
    renderMarkupPane(existingNode,existingWrap,result&&result.existing_html?result.existing_html:"",false);
    renderMarkupPane(proposedNode,proposedWrap,result&&result.proposed_html?result.proposed_html:"",false);
  };

  var getLiveCanvasRefreshUrl=function(){
    var baseUrl=typeof window.lc_editor_url_to_load==="string"?window.lc_editor_url_to_load:"";
    baseUrl=String(baseUrl||"");
    if(baseUrl===""){return "";}
    return baseUrl+(baseUrl.indexOf("?")===-1?"?":"&")+"lcfa_refresh="+String(Date.now());
  };

  var shouldRefreshLiveCanvas=function(result,payload){
    if(!payload||typeof payload!=="object"||payload.dry_run){return false;}
    if(result&&Object.prototype.hasOwnProperty.call(result,"ok")&&!result.ok){return false;}
    var mode=String(result&&result.mode?result.mode:"apply");
    if(mode!==""&&mode!=="apply"){return false;}
    var action=String((result&&result.action)||(payload&&payload.action)||"");
    if(action===""||action==="site_audit"){return false;}
    if(typeof window.loadURLintoEditor!=="function"){return false;}
    return getLiveCanvasRefreshUrl()!=="";
  };

  var refreshLiveCanvas=function(result,payload){
    if(!shouldRefreshLiveCanvas(result,payload)){return false;}
    try{
      window.loadURLintoEditor(getLiveCanvasRefreshUrl());
      return true;
    }catch(error){
      return false;
    }
  };

  var buildPreviewPayload=function(payload){
    if(!payload||typeof payload!=="object"||!payload.action){return null;}
    var nextPayload=Object.assign({},payload);
    nextPayload.dry_run=true;
    return nextPayload;
  };

  var buildApplyPayload=function(payload){
    if(!payload||typeof payload!=="object"||!payload.action){return null;}
    var nextPayload=Object.assign({},payload);
    if(Object.prototype.hasOwnProperty.call(nextPayload,"dry_run")){delete nextPayload.dry_run;}
    return nextPayload;
  };

  var buildExecutionPayload=function(payload){
    var selectedAnchor=getSelectedSectionAnchor();
    var executionPayload=Object.assign({},withFrontendProvenance(payload),{
      thread_id:threadSelect&&threadSelect.value?threadSelect.value:(config.threadId||"default"),
      context_post_id:payload.context_post_id||config.postId||0,
      post_id:payload.post_id||config.postId||0,
      target_id:payload.target_id||config.targetId||0,
      variant:payload.variant||config.variant||"1"
    });
    if(selectedAnchor&&!executionPayload.selected_section_anchor){
      executionPayload.selected_section_anchor=selectedAnchor;
    }
    return executionPayload;
  };

  var pollExecution=function(executionId,payload,suggestionSource){
    if(!config.commandExecutionEndpoint){return Promise.reject(new Error("missing-endpoint"));}
    setConversationState("running");
    var pollUrl=config.commandExecutionEndpoint+"?execution_id="+encodeURIComponent(executionId);
    return fetch(pollUrl,{
      method:"GET",
      credentials:"same-origin",
      headers:{"X-WP-Nonce":config.restNonce||""}
    }).then(function(response){
      return response.text().then(function(text){
        var data={};
        try{data=text?JSON.parse(text):{};}catch(error){data={};}
        if(!response.ok){throw {message:(data&&data.error)||((config.labels&&config.labels.applyFailed)||"The inline execution failed."),data:data};}
        return data;
      });
    }).then(function(data){
      var execution=data&&data.execution&&typeof data.execution==="object"?data.execution:{};
      if(execution.status==="queued"||execution.status==="running"){
        setConversationState(execution.status==="queued"?"queueing":"running");
        return new Promise(function(resolve){
          setTimeout(function(){resolve(pollExecution(executionId,payload,suggestionSource));},200);
        });
      }
      if(execution.thread){cacheThread(execution.thread);renderThread(execution.thread);}
      if(execution.status!=="failed"&&payload&&payload.dry_run){
        previewedSuggestionKey=getSuggestionKey(payload);
      }
      renderExecutionResult(execution.result||{message:(config.labels&&config.labels.applyFailed)||"The inline execution failed."},execution.status==="failed");
      if(execution.status!=="failed"){
        refreshLiveCanvas(execution.result||{},payload);
      }
      setSuggestionState(suggestionSource||payload,false,"");
      setConversationState(execution.status==="failed"?"failed":(payload&&payload.dry_run?"previewed":"applied"));
      return execution;
    });
  };

  var runSyncInlinePayload=function(payload,suggestionSource){
    return fetch(config.commandEndpoint,{
      method:"POST",
      credentials:"same-origin",
      headers:{"Content-Type":"application/json","X-WP-Nonce":config.restNonce||""},
      body:JSON.stringify(buildExecutionPayload(payload))
    }).then(function(response){
      return response.text().then(function(text){
        var data={};
        try{data=text?JSON.parse(text):{};}catch(error){data={};}
        if(!response.ok){
          var errorMessage=(data&&data.result&&data.result.message)||(data&&data.error)||(config.labels&&config.labels.applyFailed)||"The inline execution failed.";
          throw {message:String(errorMessage||""),data:data};
        }
        return data;
      });
    }).then(function(data){
      if(data&&data.thread){cacheThread(data.thread);renderThread(data.thread);}
      if(payload&&payload.dry_run){
        previewedSuggestionKey=getSuggestionKey(payload);
      }
      renderExecutionResult(data&&data.result?data.result:{message:(config.labels&&config.labels.applyFailed)||"The inline execution failed."},false);
      refreshLiveCanvas(data&&data.result?data.result:{},payload);
      setSuggestionState(suggestionSource||payload,false,"");
      setConversationState(payload.dry_run?"previewed":"applied");
    });
  };

  var runInlinePayload=function(payload){
    if(!payload||typeof payload!=="object"||!payload.action){return;}
    var busyMode=payload.dry_run?"preview":"apply";
    setSuggestionState(suggestionPayload||payload,true,busyMode);
    setConversationState("queueing");
    var executionPayload=buildExecutionPayload(payload);
    if(config.commandExecutionEndpoint){
      fetch(config.commandExecutionEndpoint,{
        method:"POST",
        credentials:"same-origin",
        headers:{"Content-Type":"application/json","X-WP-Nonce":config.restNonce||""},
        body:JSON.stringify(executionPayload)
      }).then(function(response){
        return response.text().then(function(text){
          var data={};
          try{data=text?JSON.parse(text):{};}catch(error){data={};}
          if(!response.ok){
            var errorMessage=(data&&data.error)||(config.labels&&config.labels.applyFailed)||"The inline execution failed.";
            throw {message:String(errorMessage||""),data:data};
          }
          return data;
        });
      }).then(function(data){
        var execution=data&&data.execution&&typeof data.execution==="object"?data.execution:{};
        if(!execution.id){
          throw {message:(config.labels&&config.labels.applyFailed)||"The inline execution failed.",data:data};
        }
        return pollExecution(execution.id,payload,suggestionPayload||payload);
      }).catch(function(error){
        renderExecutionResult(error&&error.data&&error.data.result?error.data.result:{message:error&&error.message?error.message:((config.labels&&config.labels.applyFailed)||"The inline execution failed.")},true);
        setSuggestionState(suggestionPayload||payload,false,"");
        setConversationState("failed");
      });
      return;
    }
    runSyncInlinePayload(payload,suggestionPayload||payload).catch(function(error){
      renderExecutionResult(error&&error.data&&error.data.result?error.data.result:{message:error&&error.message?error.message:((config.labels&&config.labels.applyFailed)||"The inline execution failed.")},true);
      setSuggestionState(suggestionPayload||payload,false,"");
      setConversationState("failed");
    });
  };

  var setBusy=function(next){
    analyzeBusy=!!next;
    if(!analyzeButton){return;}
    var label=getButtonLabelNode(analyzeButton);
    if(label){label.textContent=next?((config.labels&&config.labels.analyzing)||"Sending..."):((config.labels&&config.labels.analyzeSuggestion)||"Send");}
    syncComposerButtons();
  };

  var parseRestJson=function(response,fallbackMessage){
    return response.text().then(function(text){
      var data={};
      try{data=text?JSON.parse(text):{};}catch(error){data={};}
      if(!response.ok){
        var errorMessage=(data&&data.error)||(data&&data.message)||fallbackMessage;
        throw {message:String(errorMessage||fallbackMessage||"Request failed."),data:data};
      }
      return data;
    });
  };

  var buildAgentStatusUrl=function(requestId){
    var separator=config.agentRequestEndpoint&&config.agentRequestEndpoint.indexOf("?")===-1?"?":"&";
    return String(config.agentRequestEndpoint||"")+separator+"request_id="+encodeURIComponent(requestId);
  };

  var getAgentPollDelay=function(){
    var delay=Number(config.agentPollDelayMs||1000);
    return delay>0?delay:1000;
  };

  var getAgentPollMaxAttempts=function(){
    var attempts=Number(config.agentPollMaxAttempts||120);
    return attempts>0?attempts:120;
  };

  var getAgentBackgroundPollDelay=function(){
    var delay=Number(config.agentBackgroundPollDelayMs||5000);
    return delay>0?delay:5000;
  };

  var getAgentRequestResult=function(request){
    var result=request&&request.result&&typeof request.result==="object"?request.result:{};
    if(result&&result.result&&typeof result.result==="object"){return result.result;}
    if(!result||Object.keys(result).length===0){
      return {ok:false,message:(request&&request.error)||((config.labels&&config.labels.applyFailed)||"The inline execution failed.")};
    }
    return result;
  };

  var renderAgentRequestThread=function(data,request){
    if(data&&data.thread){cacheThread(data.thread);renderThread(data.thread);return;}
    if(request&&request.thread){cacheThread(request.thread);renderThread(request.thread);}
  };

  var finalizeAgentRequest=function(request,payload,data){
    var status=String(request&&request.status||"");
    if(status!=="completed"&&status!=="failed"){return false;}
    renderAgentRequestThread(data,request);
    var result=getAgentRequestResult(request);
    var failed=status==="failed"||(result&&Object.prototype.hasOwnProperty.call(result,"ok")&&!result.ok);
    renderExecutionResult(result,failed);
    if(!failed){refreshLiveCanvas(result,payload);}
    setSuggestionState(null,false,"");
    setConversationState(failed?"failed":"applied");
    return true;
  };

  var scheduleAgentBackgroundPoll=function(requestId,payload){
    setTimeout(function(){
      pollAgentRequest(requestId,payload,0,true).catch(function(){});
    },getAgentBackgroundPollDelay());
  };

  var pollAgentRequest=function(requestId,payload,attempt,isBackground){
    var nextAttempt=Number(attempt||0);
    return fetch(buildAgentStatusUrl(requestId),{
      method:"GET",
      credentials:"same-origin",
      headers:{"X-WP-Nonce":config.restNonce||""}
    }).then(function(response){
      return parseRestJson(response,(config.labels&&config.labels.applyFailed)||"The inline execution failed.");
    }).then(function(data){
      var request=data&&data.request&&typeof data.request==="object"?data.request:{};
      if(request&&request.status==="running"){
        renderAgentRequestThread(data,request);
        setConversationState("running",(config.labels&&config.labels.agentRunningState)||("The coding agent is processing this request..."));
      }else{
        setConversationState("queueing",(config.labels&&config.labels.agentQueuedState)||("Waiting for "+getAgentLabel()+"..."));
      }
      if(finalizeAgentRequest(request,payload,data)){return request;}
      var maxAttempts=isBackground?0:getAgentPollMaxAttempts();
      if(nextAttempt>=maxAttempts){
        setConversationState("queueing",(config.labels&&config.labels.agentTimeoutState)||"Request queued. Keep the coding agent open, then this panel will update.");
        scheduleAgentBackgroundPoll(requestId,payload);
        return request;
      }
      return new Promise(function(resolve){
        setTimeout(function(){resolve(pollAgentRequest(requestId,payload,nextAttempt+1,!!isBackground));},getAgentPollDelay());
      });
    });
  };

  var enqueueAgentRequest=function(requestPayload){
    setConversationState("queueing",(config.labels&&config.labels.agentQueuedState)||("Waiting for "+getAgentLabel()+"..."));
    fetch(config.agentRequestEndpoint,{
      method:"POST",
      credentials:"same-origin",
      headers:{"Content-Type":"application/json","X-WP-Nonce":config.restNonce||""},
      body:JSON.stringify(requestPayload)
    }).then(function(response){
      return parseRestJson(response,(config.labels&&config.labels.analysisFailed)||"The request analysis failed.");
    }).then(function(data){
      var request=data&&data.request&&typeof data.request==="object"?data.request:{};
      renderAgentRequestThread(data,request);
      if(finalizeAgentRequest(request,requestPayload,data)){return request;}
      if(!request.id){throw {message:(config.labels&&config.labels.analysisFailed)||"The request analysis failed.",data:data};}
      return pollAgentRequest(request.id,requestPayload,0);
    }).catch(function(error){
      setSuggestionState(null,false,"");
      if(error&&error.data&&error.data.thread){cacheThread(error.data.thread);renderThread(error.data.thread);}
      renderExecutionResult(error&&error.data&&error.data.result?error.data.result:{ok:false,message:error&&error.message?error.message:((config.labels&&config.labels.analysisFailed)||"The request analysis failed.")},true);
      setConversationState("failed");
    }).finally(function(){setBusy(false);});
  };

  var analyzeRequest=function(){
    if(!promptInput){return;}
    var prompt=promptInput.value.trim();
    if(prompt===""){
      renderSuggestion({message:(config.labels&&config.labels.requestRequired)||"Write a request first so Forge AI can suggest an action."},true);
      return;
    }
    setBusy(true);
    setConversationState("thinking");
    var requestPayload={
      thread_id:threadSelect&&threadSelect.value?threadSelect.value:(config.threadId||"default"),
      user_prompt:prompt,
      execution_target:targetSelect&&targetSelect.value?targetSelect.value:"local",
      context_post_id:config.postId||0,
      post_id:config.postId||0,
      target_id:config.targetId||0,
      variant:config.variant||"1",
      action:config.defaultAction||"site_audit"
    };
    var selectedAnchor=getSelectedSectionAnchor();
    if(selectedAnchor){
      requestPayload.selected_section_anchor=selectedAnchor;
    }
    requestPayload=withFrontendProvenance(requestPayload);
    if(attachmentState&&attachmentState.data_url){
      requestPayload.attachments=[attachmentState];
    }
    if(isAgentQueueEnabled()){
      enqueueAgentRequest(requestPayload);
      return;
    }
    fetch(config.restEndpoint,{
      method:"POST",
      credentials:"same-origin",
      headers:{"Content-Type":"application/json","X-WP-Nonce":config.restNonce||""},
      body:JSON.stringify(requestPayload)
    }).then(function(response){
      return response.text().then(function(text){
        var data={};
        try{data=text?JSON.parse(text):{};}catch(error){data={};}
        if(!response.ok){
          var errorMessage=(data&&data.suggestion&&data.suggestion.message)||(data&&data.error)||(config.labels&&config.labels.analysisFailed)||"The request analysis failed.";
          throw {message:String(errorMessage||""),data:data};
        }
        return data;
      });
    }).then(function(data){
      if(data&&data.thread){cacheThread(data.thread);renderThread(data.thread);}
      if(data&&data.suggestion&&data.suggestion.suggested_payload&&data.suggestion.suggested_payload.action){
        setSuggestionState(data.suggestion.suggested_payload,false,"");
        updateDeckLink(data.suggestion.suggested_payload);
        runInlinePayload(buildApplyPayload(data.suggestion.suggested_payload));
        return;
      }
      renderSuggestion(data&&data.suggestion?data.suggestion:{message:(config.labels&&config.labels.analysisFailed)||"The request analysis failed."},false);
    }).catch(function(error){
      setSuggestionState(null,false,"");
      if(error&&error.data&&error.data.thread){cacheThread(error.data.thread);renderThread(error.data.thread);}
      renderSuggestion({message:error&&error.message?error.message:((config.labels&&config.labels.analysisFailed)||"The request analysis failed.")},true);
    }).finally(function(){setBusy(false);});
  };

  var manageThread=function(operation){
    if(!config.threadEndpoint){return;}
    var payload={operation:operation,thread_id:selectedThreadId};
    if(operation==="rename"){
      var renamePrompt=(config.labels&&config.labels.renameThreadPrompt)||"Rename the current thread";
      var nextTitle=window.prompt?window.prompt(renamePrompt,""):null;
      if(nextTitle===null){return;}
      payload.title=String(nextTitle||"").trim();
      if(payload.title===""){return;}
    }
    if(operation==="clear"&&window.confirm&&!window.confirm((config.labels&&config.labels.confirmClearThread)||"Clear all messages from the current thread?")){return;}
    if(operation==="delete"&&window.confirm&&!window.confirm((config.labels&&config.labels.confirmDeleteThread)||"Delete the current thread and switch back to the default one?")){return;}
    if(operation==="create"){payload.title=(config.labels&&config.labels.newThreadLabel)||"New thread";}
    if(operation==="duplicate"){payload.title=(config.labels&&config.labels.duplicateThreadLabel)||"Duplicate current";}
    setConversationState("thinking",(config.labels&&config.labels[operation+"Thread"])||((config.labels&&config.labels.thinkingState)||"Analyzing request..."));
    fetch(config.threadEndpoint,{
      method:"POST",
      credentials:"same-origin",
      headers:{"Content-Type":"application/json","X-WP-Nonce":config.restNonce||""},
      body:JSON.stringify(payload)
    }).then(function(response){
      return response.text().then(function(text){
        var data={};
        try{data=text?JSON.parse(text):{};}catch(error){data={};}
        if(!response.ok){throw {message:(data&&data.error)||"Thread operation failed.",data:data};}
        return data;
      });
    }).then(function(data){
      if(data&&data.threads&&typeof data.threads==="object"){config.threads=data.threads;}
      if(Array.isArray(data&&data.thread_summaries)){rebuildThreadSelect(data.thread_summaries);}
      setSelectedThreadId(data&&data.selected_thread_id?data.selected_thread_id:(data&&data.thread&&data.thread.id?data.thread.id:selectedThreadId));
      if(data&&data.thread){cacheThread(data.thread);}
      resetSupportDetails();
      setSuggestionState(null,false,"");
      hydrateSelectedThread();
      updateDeckLink({action:config.defaultAction||"site_audit",execution_target:targetSelect&&targetSelect.value?targetSelect.value:"local",target_id:config.targetId||0,variant:config.variant||"1"});
    }).catch(function(){setConversationState("failed");});
  };

  var previewSuggestion=function(){
    var payload=buildPreviewPayload(suggestionPayload);
    if(!payload){return;}
    runInlinePayload(payload);
  };

  var applySuggestion=function(){
    var payload=buildApplyPayload(suggestionPayload);
    if(!payload){return;}
    runInlinePayload(payload);
  };

  if(openBtn){openBtn.addEventListener("click",function(){setOpen(true);});}
  if(closeBtn){closeBtn.addEventListener("click",function(){setOpen(false);});}
  if(analyzeButton){analyzeButton.addEventListener("click",analyzeRequest);}
  if(createThreadButton){createThreadButton.addEventListener("click",function(){manageThread("create");});}
  if(duplicateThreadButton){duplicateThreadButton.addEventListener("click",function(){manageThread("duplicate");});}
  if(renameThreadButton){renameThreadButton.addEventListener("click",function(){manageThread("rename");});}
  if(clearThreadButton){clearThreadButton.addEventListener("click",function(){manageThread("clear");});}
  if(deleteThreadButton){deleteThreadButton.addEventListener("click",function(){manageThread("delete");});}
  if(attachmentTriggerButton&&attachmentInput){
    attachmentTriggerButton.addEventListener("click",function(){
      attachmentInput.click();
    });
  }
  if(attachmentInput){
    attachmentInput.addEventListener("change",function(event){
      var file=event&&event.target&&event.target.files&&event.target.files[0]?event.target.files[0]:null;
      if(!file){attachmentState=null;renderAttachmentPreview();return;}
      loadAttachmentFile(file);
    });
  }
  if(attachmentClearButton){
    attachmentClearButton.addEventListener("click",function(){
      attachmentState=null;
      if(attachmentInput){attachmentInput.value="";attachmentInput.files=[];}
      renderAttachmentPreview();
    });
  }
  if(attachmentPreviewImage){
    attachmentPreviewImage.addEventListener("error",function(){
      attachmentState=null;
      if(attachmentInput){attachmentInput.value="";attachmentInput.files=[];}
      attachmentPreviewImage.hidden=true;
      attachmentPreviewImage.src="";
      attachmentPreview.hidden=true;
      attachmentPreviewMeta.textContent="";
      if(attachmentClearButton){attachmentClearButton.hidden=true;}
    });
  }
  if(promptInput){
    promptInput.addEventListener("keydown",function(event){
      if((event.metaKey||event.ctrlKey)&&event.key==="Enter"){event.preventDefault();analyzeRequest();}
    });
    promptInput.addEventListener("input",function(){
      syncComposerButtons();
    });
  }
  if(threadSelect){
    threadSelect.addEventListener("change",function(){
      setSelectedThreadId(threadSelect.value||config.threadId||"default");
      resetSupportDetails();
      setSuggestionState(null,false,"");
      hydrateSelectedThread();
      updateDeckLink({action:config.defaultAction||"site_audit",execution_target:targetSelect&&targetSelect.value?targetSelect.value:"local",target_id:config.targetId||0,variant:config.variant||"1"});
    });
  }
  if(threadLog){
    threadLog.addEventListener("click",function(event){
      var target=event.target&&event.target.closest?event.target.closest("[data-lcfa-editor-thread-apply]"):null;
      if(!target){return;}
      event.preventDefault();
      try{
        runInlinePayload(JSON.parse(target.getAttribute("data-lcfa-editor-thread-apply")||"{}"));
      }catch(error){}
    });
  }
  if(targetSelect){
    targetSelect.addEventListener("change",function(){
      updateDeckLink({action:config.defaultAction||"site_audit",execution_target:targetSelect.value,target_id:config.targetId||0,variant:config.variant||"1"});
    });
  }

  var persistedThreadId=getPersistedThreadId();
  if(persistedThreadId!==""&&getThreadById(persistedThreadId)){setSelectedThreadId(persistedThreadId);}
  else if(config.threadId){setSelectedThreadId(config.threadId);}
  renderAttachmentPreview();
  syncComposerButtons();
  updateDeckLink({action:config.defaultAction||"site_audit",execution_target:targetSelect&&targetSelect.value?targetSelect.value:"local",target_id:config.targetId||0,variant:config.variant||"1"});
  if(config.threads&&typeof config.threads==="object"){hydrateSelectedThread();}
  else{setConversationState(statusNode?statusNode.getAttribute("data-state")||"idle":"idle",statusNode?statusNode.textContent||"":"");}

  document.addEventListener("keydown",function(event){if(event.key==="Escape"){setOpen(false);}});
  document.addEventListener("click",function(event){
    if(shell.contains(event.target)){return;}
    var anchor=describeSectionAnchor(event.target);
    if(anchor){selectedSectionAnchor=anchor;}
    if(shell.classList.contains("is-open")){setOpen(false);}
  });
})();
