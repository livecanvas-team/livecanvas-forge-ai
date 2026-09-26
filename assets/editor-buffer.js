(function(root,factory){
  if(typeof module==="object"&&module.exports){module.exports=factory;}
  else{root.LCFACreateEditorBuffer=factory;}
})(typeof window!=="undefined"?window:this,function(win,document,config,shell){
  "use strict";
  var revision=0;
  var observedFrame=null;
  var busy=false;
  var lastCodeEdit=0;
  var watchedSessions=[];
  function watchCodeEditors(){
    [win.lc_html_editor,win.lc_css_editor,win.lc_js_editor].forEach(function(editor){
      if(!editor||typeof editor.getSession!=="function"){return;}
      var session=editor.getSession();
      if(!session||typeof session.on!=="function"||watchedSessions.indexOf(session)!==-1){return;}
      session.on("change",function(){revision++;lastCodeEdit=Date.now();});
      watchedSessions.push(session);
      // The adapter may attach after a native throttled edit was scheduled.
      lastCodeEdit=Date.now();
    });
  }
  var changed=function(event){if(!shell||!shell.contains(event.target)){revision++;}};
  document.addEventListener("input",changed,true);
  document.addEventListener("change",changed,true);
  document.addEventListener("DOMContentLoaded",watchCodeEditors);
  document.addEventListener("lcDocAvailable",watchCodeEditors);
  if(typeof win.addEventListener==="function"){win.addEventListener("load",watchCodeEditors);}
  watchCodeEditors();

  function read(){
    try{
      watchCodeEditors();
      var frame=document.getElementById("previewiframe");
      var frameDoc=frame&&frame.contentDocument;
      if(frameDoc&&frameDoc!==observedFrame){
        frameDoc.addEventListener("input",changed,true);
        frameDoc.addEventListener("change",changed,true);
        observedFrame=frameDoc;
      }
      if(typeof win.getPageHTML!=="function"||typeof win.original_document_html!=="string"||!win.doc||!win.doc.querySelector("main#lc-main")||!frameDoc){return {state:"unknown"};}
      if(Number(win.lc_editor_current_post_id)!==Number(config.postId)||!Number(config.postId)){return {state:"target_changed"};}
      var url=new URL(win.lc_editor_url_to_load,win.location.href);
      if(url.origin!==win.location.origin){return {state:"unknown"};}
      // LiveCanvas's HTML code editor is throttled; its pop-out uses this same
      // toggle. Never treat a pending code/rich-text buffer as a clean document.
      var toggle=document.getElementById("toggle-code-editor");
      if((toggle&&toggle.classList.contains("is-active"))||frameDoc.querySelector(".lc-content-is-being-edited")){return {state:"editing"};}
      if(lastCodeEdit&&Date.now()-lastCodeEdit<250){return {state:"editing"};}
      var focus=document.activeElement;
      if(focus&&(!shell||!shell.contains(focus))&&(focus.isContentEditable||/^(INPUT|TEXTAREA|SELECT)$/.test(focus.tagName))){return {state:"editing"};}
      var saving=document.querySelector("#main-save .fa-spinner");
      if(saving){return {state:"saving"};}
      var html=win.getPageHTML();
      if(typeof html!=="string"||!html){return {state:"unknown"};}
      return {state:html===win.original_document_html?"clean":"dirty",html:html,original:win.original_document_html,
        document:win.doc,url:url.href,postId:Number(config.postId),revision:revision};
    }catch(error){return {state:"unknown"};}
  }

  function unchanged(snapshot){
    var now=read();
    return !!snapshot&&snapshot.state==="clean"&&now.state==="clean"&&snapshot.html===now.html&&snapshot.original===now.original&&snapshot.document===now.document&&snapshot.url===now.url&&snapshot.postId===now.postId&&snapshot.revision===now.revision;
  }

  async function refresh(snapshot){
    if(busy||!unchanged(snapshot)){return {refreshed:false,reason:"editor_changed"};}
    if(typeof win.parseFromComplexString!=="function"||typeof win.filterPreviewHTML!=="function"||typeof win.tryToEnrichPreview!=="function"||typeof win.saveHistoryStep!=="function"||!win.lcMainStore||typeof win.lcMainStore.setDoc!=="function"){return {refreshed:false,reason:"adapter_unavailable"};}
    busy=true;
    try{
      var url=new URL(snapshot.url);url.searchParams.set("lcfa_refresh",String(Date.now()));
      var response=await win.fetch(url.href,{credentials:"same-origin",cache:"no-store",redirect:"error"});
      if(!response.ok){throw new Error("Editor response unavailable");}
      var nextDoc=win.parseFromComplexString(await response.text());
      if(!nextDoc||!nextDoc.querySelector("main#lc-main")||!nextDoc.querySelector("html")){throw new Error("Editor document unavailable");}
      var preview=win.filterPreviewHTML(nextDoc.querySelector("html").outerHTML);
      // The network request must not create a window in which local edits can
      // be overwritten. Read again immediately before the synchronous commit.
      if(!unchanged(snapshot)){return {refreshed:false,reason:"editor_changed"};}
      var frame=document.getElementById("previewiframe");
      win.doc=nextDoc;
      win.original_document_html=nextDoc.querySelector("html").innerHTML;
      win.lcMainStore.setDoc(nextDoc,true);
      frame.srcdoc=preview;
      frame.onload=win.tryToEnrichPreview();
      win.saveHistoryStep();
      return {refreshed:true};
    }catch(error){return {refreshed:false,reason:"refresh_failed"};}
    finally{busy=false;}
  }
  return {read:read,unchanged:unchanged,refresh:refresh};
});
