/*
 * Copyright (C) 2014 Blackmoon Info Tech Services
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * AjaxDataUpdater is a "behind-the-scenes" AJAX data updater. 
 * @param aOnLoadURL - the onLoad data URL, called only once when .start runs.
 * @param aOnUpdateURL - the onUpdate data URL, called repeatedly after onLoad (if defined) between delay times.
 * @param aOnReceiveData - the external callback once data is retrieved.
 * @param aDelayMin - minimum milliseconds before calling the update URL again; default is 2000.
 * @param aDelayMax - maximum milliseconds before calling the update URL again; default is 5000; if smaller than aDelayMin, will be set equal to it.
 */
function AjaxDataUpdater(aOnLoadURL, aOnUpdateURL, aOnReceiveData, aDelayMin, aDelayMax) {
	BasicObj.call(this);
	this.mDebugAjaxData = true;
	this.mOnLoadURL = aOnLoadURL;
	this.mOnUpdateURL = aOnUpdateURL;
	this.mOnReceiveData = (typeof aOnReceiveData!=='undefined') ? aOnReceiveData : this.bind(this,'dummyCallback');
	this.mDelayMin = this.argOrDefault(aDelayMin,2000);
	this.mDelayMax = this.argOrDefault(aDelayMax,5000);
	if (this.mDelayMax<this.mDelayMin) {
		this.mDelayMax = this.mDelayMin;
	}
	this.mPostData = null;
	this.bStarted = false;
	this.bDone = false;
	this.bAutoParse = true;
	this.mHttpRequest = this.getAJAXrequest(this.bind(this,'handleStateChange'),this.bind(this,'handleTimeout'));
	return this;
}

//setting up the inheritance
AjaxDataUpdater.prototype = Object.create(BasicObj.prototype);

/**
 * Data will continually be updated unless stopped with this function.
 */
AjaxDataUpdater.prototype.stop = function() {
	this.bDone = true;
}

/**
 * This function is only used when an onReceiveData callback is not defined.
 * Used mainly for INSERT/UPDATE/DELETE data calls that do not require feedback.
 */
AjaxDataUpdater.prototype.dummyCallback = function() {
	//do nothing
}

/**
 * Response text will be automatically parsed if TRUE, returned unmolested if FALSE. Default TRUE.
 * @param aBool - true or false.
 */
AjaxDataUpdater.prototype.setAutoParse = function(aBool) {
	this.bAutoParse = aBool;
}

/**
 * Callback method to determine when Ajax call is ready to deliver responseText.
 */
AjaxDataUpdater.prototype.handleStateChange = function() {
	if (this.mHttpRequest.readyState == 4) {
        switch (this.mHttpRequest.status) {
        case 200: //Success!
		this.requestDataCallback();
            break;
        case 408: //Request Timeout.
			console.log('408 error. Re-sending ajax request.');
			if (!this.bDone) {
				setTimeout(this.bind(this,'sendAjaxRequest'), this.randomFromInterval(this.mDelayMin,this.mDelayMax));
			}
            break;
        case 331: //Chrome: net::ERR_NETWORK_IO_SUSPENDED
            //laptop put to sleep and then awakened, net connection to page needs a reload.
			console.log('Chrome "net::ERR_NETWORK_IO_SUSPENDED" (331) error. Reloading page.');
			window.location.reload();
            break;
        case 504: //Gateway Timeout.
			console.log('Gateway timeout error. Reloading page.');
			window.location.reload();
            break;
		default:
			console.log('HTTP response: '+this.mHttpRequest.status+". This particular failure not handled, yet.");			
		}
	}
}

/**
 * Callback method to determine when Ajax call is ready to deliver responseText.
 */
AjaxDataUpdater.prototype.handleTimeout = function() {
	alert('Server took too long to respond, keeping quiet until you hit Refresh/Reload page.')
}

/**
 * Based on the browser, get the appropriate AJAX object to use.
 * @returns Returns the proper {XMLHttpRequest}/{ActiveXObject object. 
 */
AjaxDataUpdater.prototype.getAJAXrequest = function(aOnStateChange, aOnTimeout) {
	var theHttpRequest = null;
	if (window.XMLHttpRequest) { // Mozilla, Safari, ...
		theHttpRequest = new XMLHttpRequest();
	} else if (window.ActiveXObject) { // MSIE
		theHttpRequest = new ActiveXObject("Microsoft.XMLHTTP");
	}
	if (theHttpRequest!=null) {
		theHttpRequest.onreadystatechange = aOnStateChange;
		theHttpRequest.ontimeout = aOnTimeout;
	}
	return theHttpRequest;
}

/**
 * Heart of the class, this function executes the request and passes headers and data approprately.
 * Use .start or .startonload instead of this one directly.
 * @param aRequestUrl - URL for the request, will be either the onLoad or onUpdate URLs.
 */
AjaxDataUpdater.prototype.sendAjaxRequest = function(aRequestUrl) {
	if (this.mHttpRequest && !this.bDone) try {
		this.bStarted = true;
		this.mHttpRequest.mRequestUrl = this.argOrDefault(aRequestUrl,this.mOnUpdateURL);
		if (!this.mPostData) {
			this.mHttpRequest.open("GET", this.mHttpRequest.mRequestUrl, true);
			this.mHttpRequest.setRequestHeader("X-Requested-With", "XMLHttpRequest");
			this.mHttpRequest.setRequestHeader("Cache-Control", "no-cache");
			this.mHttpRequest.send();
		} else {
			this.mHttpRequest.open("POST", this.mHttpRequest.mRequestUrl, true);
			this.mHttpRequest.setRequestHeader("X-Requested-With", "XMLHttpRequest");
			this.mHttpRequest.setRequestHeader("Cache-Control", "no-cache");
			this.mHttpRequest.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			//we are not supposed to set Content-length: https://www.owasp.org/index.php/HTTP_Request_Smuggling
			//this.mHttpRequest.setRequestHeader("Content-length", this.mPostData.length);
			this.mHttpRequest.send(this.mPostData);
			//alert('AjaxPost URL='+this.mHttpRequest.mRequestUrl+'\nPostData='+this.mPostData);
		}
	} catch(err) {
		if (this.mDebugAjaxData == true) {
			alert('AjaxSend URL='+this.mHttpRequest.mRequestUrl+'\n'+err.message.trim()+'\nPostData='+this.mPostData);
		}
	}
}

/**
 * If JSON data is detected, automatically decode it before returning it to the callback function.
 * This feature may be disabled with .setAutoParse(false);
 * @returns Returns the responseText parsed appropriately (only JSON supported at this time).
 */
AjaxDataUpdater.prototype.parseDataRequested = function() {
	if (this.bAutoParse && this.mHttpRequest.getResponseHeader("Content-Type")=="application/json; charset=utf-8") {
		return JSON.parse(this.mHttpRequest.responseText);
	} else {
		return this.mHttpRequest.responseText;
	}
}

/**
 * This callback function is called when the data is ready (readyState=4) for either init or update data.
 */
AjaxDataUpdater.prototype.requestDataCallback = function() {
	try {
		var theData = this.parseDataRequested();
		if (theData!==null && !this.bDone) {
			this.mOnReceiveData(theData);
		}
	} catch(err) {
		if (this.mDebugAjaxData == true) {
			alert('AJAX URL='+this.mHttpRequest.mRequestUrl+'\n'+err.message.trim()+'\nResponse='+this.mHttpRequest.responseText);
		}
	}
	if (!this.mOnUpdateURL) {
		this.stop();
	}
	if (!this.bDone) {
		setTimeout(this.bind(this,'sendAjaxRequest'), this.randomFromInterval(this.mDelayMin,this.mDelayMax));
	}
}

/**
 * Call this function to start the ajax calls immediately, or with a delay in milliseconds.
 * @param aDelay - optional timeout delay (in milliseconds).
 * @returns Returns self, {AjaxDataUpdater}, for chaining purposes.
 */
AjaxDataUpdater.prototype.start = function(aDelay) {
	var theDelay = this.argOrDefault(aDelay,0);
	if (theDelay>0) {
		setTimeout(this.bind(this,'start'),theDelay);
		return this;
	}
	if (!this.bStarted && this.mOnLoadURL)
		this.sendAjaxRequest(this.mOnLoadURL);
	else
		this.sendAjaxRequest(this.mOnUpdateURL);
	return this;
}

/**
 * Call this function if you want to start the ajax calls once the page is finished loading.
 * @returns Returns self, {AjaxDataUpdater}, for chaining purposes.
 */
AjaxDataUpdater.prototype.startOnLoad = function() {
	this.addEvent('load', 'start');
	return this;
}

/**
 * Data to be included with an ajax call. Call repeated for each key/value pair to add.
 * @param aKey - key string
 * @param aValue - value as string (it will be made URL-safe)
 * @returns {AjaxDataUpdater} Returns "this" for chaining.
 */
AjaxDataUpdater.prototype.addPostData = function(aKey, aValue) {
	if (!this.mPostData) {
		this.mPostData = {};
		this.mPostData = aKey+'='+encodeURIComponent(aValue);
	} else {
		this.mPostData += '&'+aKey+'='+encodeURIComponent(aValue);		
	}
	return this;
}

