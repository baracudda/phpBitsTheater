function selectElement(aId){if(typeof aId!=="undefined"){var r=document.createRange();var e=document.getElementById(aId);var s=window.getSelection();r.selectNode(e);s.removeAllRanges();s.addRange(r)}}function jump_to_top(){$("html, body").animate({scrollTop:0},"fast")}function zulu_to_local(id,phpTs){var sts=new Date(phpTs*1E3),s=sts.toLocaleString();document.getElementById(id).innerHTML=s}
(function(){var bLoadClassDef=false,fnTest=/xyz/.test(function(){xyz})?/\b_super\b/:/.*/;this.StdClass=function(){};StdClass.extend=function(prop){var _super=this.prototype;bLoadClassDef=true;var prototype=new this;bLoadClassDef=false;for(var name in prop)prototype[name]=typeof prop[name]==="function"&&typeof _super[name]==="function"&&fnTest.test(prop[name])?function(name,fn){return function(){var tmp=this._super;this._super=_super[name];var ret=fn.apply(this,arguments);this._super=tmp;return ret}}(name,
prop[name]):prop[name];function StdClass(){if(!bLoadClassDef&&this.onCreate)this.onCreate.apply(this,arguments)}StdClass.prototype=prototype;StdClass.prototype.constructor=StdClass;StdClass.extend=arguments.callee;return StdClass}})();var BaseClass=StdClass.extend({onCreate:function(){}});BaseClass.prototype.argOrDefault=function(aArg,aDef){return typeof aArg!=="undefined"?aArg:aDef};
BaseClass.prototype.asCB=function(aMethod){var thisone=this;var theFn=typeof aMethod==="function"?aMethod:this[aMethod];return function(){return theFn.apply(thisone,arguments)}};BaseClass.prototype.bind=BaseClass.prototype.asCB;Function.prototype.asCB=function(aContext){var theFn=this;return function(){return theFn.apply(aContext,arguments)}};
BaseClass.prototype.createUUID=function(aGlue){var g=this.argOrDefault(aGlue,"-");var d=(new Date).getTime();var s="xxxxxxxx"+g+"xxxx"+g+"4xxx"+g+"yxxx"+g+"xxxxxxxxxxxx";var uuid=s.replace(/[xy]/g,function(c){var r=(d+Math.random()*16)%16|0;d=Math.floor(d/16);return(c==="x"?r:r&7|8).toString(16)});return uuid};function BasicObj(){this.mBrowserWidth=null;this.mBrowserHeight=null;setTimeout(this.bind(this,"_gbw"),1E3);return this}BasicObj.prototype.bind=function(aTargetObject,aMethodName){return function(arg){return aTargetObject[aMethodName](arg)}};
BasicObj.prototype.argOrDefault=function(aArg,aDef){return typeof aArg!=="undefined"?aArg:aDef};BasicObj.prototype.randomFromInterval=function(aMin,aMax){return Math.floor(Math.random()*(aMax-aMin+1)+aMin)};BasicObj.prototype.drawImage=function(aElem,aImage,x,y,w,h){w=this.argOrDefault(w,aImage.width);h=this.argOrDefault(h,aImage.height);if(aImage.complete)aElem.drawImage(aImage,x,y,w,h);else aImage.onload=function(){aElem.drawImage(aImage,x,y,w,h)};return this};
BasicObj.prototype.addEvent=function(aEvent,aHandler,aObj){var theHandler=typeof aHandler!=="string"?aHandler:this.bind(this,aHandler);var theObj=aObj||window;if(theObj.addEventListener)theObj.addEventListener(aEvent,theHandler,false);else if(theObj.attachEvent)theObj.attachEvent("on"+aEvent,theHandler);else theObj["on"+aEvent]=theHandler;return this};
BasicObj.prototype.couldBeMobileBrowser=function(){return navigator.userAgent.match(/ipad|iphone|ipod|android|webos|blackberry/i)!=null||screen.width<=480};BasicObj.prototype._gbw=function(){var w=window,d=document,e=d.documentElement,g=d.getElementsByTagName("body")[0];this.mBrowserWidth=w.innerWidth||e.clientWidth||g.clientWidth;this.mBrowserHeight=w.innerHeight||e.clientHeight||g.clientHeight;return this.mBrowserWidth};BasicObj.prototype.getBrowserWidth=function(){return this.mBrowserWidth||this._gbw()};
function AjaxDataUpdater(aOnLoadURL,aOnUpdateURL,aOnReceiveData,aDelayMin,aDelayMax){BasicObj.call(this);this.mDebugAjaxData=true;this.mOnLoadURL=aOnLoadURL;this.mOnUpdateURL=aOnUpdateURL;this.mOnReceiveData=typeof aOnReceiveData!=="undefined"?aOnReceiveData:this.bind(this,"dummyCallback");this.mDelayMin=this.argOrDefault(aDelayMin,2E3);this.mDelayMax=this.argOrDefault(aDelayMax,5E3);if(this.mDelayMax<this.mDelayMin)this.mDelayMax=this.mDelayMin;this.mPostData=null;this.bStarted=false;this.bDone=
false;this.bAutoParse=true;this.mHttpRequest=this.getAJAXrequest(this.bind(this,"handleStateChange"),this.bind(this,"handleTimeout"));return this}AjaxDataUpdater.prototype=Object.create(BasicObj.prototype);AjaxDataUpdater.prototype.stop=function(){this.bDone=true};AjaxDataUpdater.prototype.dummyCallback=function(){};AjaxDataUpdater.prototype.setAutoParse=function(aBool){this.bAutoParse=aBool};
AjaxDataUpdater.prototype.handleStateChange=function(){if(this.mHttpRequest.readyState==4)switch(this.mHttpRequest.status){case 200:this.requestDataCallback();break;case 408:console.log("408 error. Re-sending ajax request.");if(!this.bDone)setTimeout(this.bind(this,"sendAjaxRequest"),this.randomFromInterval(this.mDelayMin,this.mDelayMax));break;case 331:console.log('Chrome "net::ERR_NETWORK_IO_SUSPENDED" (331) error. Reloading page.');window.location.reload();break;case 504:console.log("Gateway timeout error. Reloading page.");
window.location.reload();break;default:console.log("HTTP response: "+this.mHttpRequest.status+". This particular failure not handled, yet.")}};AjaxDataUpdater.prototype.handleTimeout=function(){alert("Server took too long to respond, keeping quiet until you hit Refresh/Reload page.")};
AjaxDataUpdater.prototype.getAJAXrequest=function(aOnStateChange,aOnTimeout){var theHttpRequest=null;if(window.XMLHttpRequest)theHttpRequest=new XMLHttpRequest;else if(window.ActiveXObject)theHttpRequest=new ActiveXObject("Microsoft.XMLHTTP");if(theHttpRequest!=null){theHttpRequest.onreadystatechange=aOnStateChange;theHttpRequest.ontimeout=aOnTimeout}return theHttpRequest};
AjaxDataUpdater.prototype.sendAjaxRequest=function(aRequestUrl){if(this.mHttpRequest&&!this.bDone)try{this.bStarted=true;this.mHttpRequest.mRequestUrl=this.argOrDefault(aRequestUrl,this.mOnUpdateURL);if(!this.mPostData){this.mHttpRequest.open("GET",this.mHttpRequest.mRequestUrl,true);this.mHttpRequest.setRequestHeader("X-Requested-With","XMLHttpRequest");this.mHttpRequest.setRequestHeader("Cache-Control","no-cache");this.mHttpRequest.send()}else{this.mHttpRequest.open("POST",this.mHttpRequest.mRequestUrl,
true);this.mHttpRequest.setRequestHeader("X-Requested-With","XMLHttpRequest");this.mHttpRequest.setRequestHeader("Cache-Control","no-cache");this.mHttpRequest.setRequestHeader("Content-type","application/x-www-form-urlencoded");this.mHttpRequest.send(this.mPostData);console.log("sendAjaxRequest URL="+this.mHttpRequest.mRequestUrl+" PostData="+this.mPostData)}}catch(err){console.log("Error 1/3: AjaxDataUpdater.sendAjaxRequest URL="+this.mHttpRequest.mRequestUrl);if(this.mDebugAjaxData==true)console.log("Error 2/3: PostData="+
this.mPostData);else console.log("Error 2/3: PostData=(not available)");console.log("Error 3/3: ErrMsg="+err.message.trim());if(this.mDebugAjaxData==true)alert("AJAX call returned an unhandled error. Check the console for details.")}};AjaxDataUpdater.prototype.parseDataRequested=function(){if(this.bAutoParse&&this.mHttpRequest.getResponseHeader("Content-Type")=="application/json; charset=utf-8")return JSON.parse(this.mHttpRequest.responseText);else return this.mHttpRequest.responseText};
AjaxDataUpdater.prototype.requestDataCallback=function(){try{var theData=this.parseDataRequested();if(theData!==null&&!this.bDone)this.mOnReceiveData(theData)}catch(err){console.log("Error 1/3: AjaxDataUpdater.requestDataCallback URL="+this.mHttpRequest.mRequestUrl);console.log("Error 2/3: Response="+this.mHttpRequest.responseText);console.log("Error 3/3: ErrMsg="+err.message.trim());if(this.mDebugAjaxData==true)alert("AJAX call returned an unhandled error. Check the console for details.")}if(!this.mOnUpdateURL)this.stop();
if(!this.bDone)setTimeout(this.bind(this,"sendAjaxRequest"),this.randomFromInterval(this.mDelayMin,this.mDelayMax))};AjaxDataUpdater.prototype.start=function(aDelay){var theDelay=this.argOrDefault(aDelay,0);if(theDelay>0){setTimeout(this.bind(this,"start"),theDelay);return this}if(!this.bStarted&&this.mOnLoadURL)this.sendAjaxRequest(this.mOnLoadURL);else this.sendAjaxRequest(this.mOnUpdateURL);return this};AjaxDataUpdater.prototype.startOnLoad=function(){this.addEvent("load","start");return this};
AjaxDataUpdater.prototype.addPostData=function(aKey,aValue){if(!this.mPostData){this.mPostData={};this.mPostData=aKey+"="+encodeURIComponent(aValue)}else this.mPostData+="&"+aKey+"="+encodeURIComponent(aValue);return this};