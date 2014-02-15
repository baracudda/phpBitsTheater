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
 * BasicObj is the base class with generic helper methods.
 */
function BasicObj() {
	"use strict"; //ECMAScript 5 Strict Mode
	this.mBrowserWidth = null;
	this.mBrowserHeight = null;
	setTimeout(this.bind(this,'_gbw'), 1000);
	return this;
}

/**
 * Helper function to bind methods as callback functions.
 * @param aTargetObject - context of the object to bind (usually "this")
 * @param aMethodName - name of method to call
 * @returns {Function} Returns the anonymous function used as a callback.
 */
BasicObj.prototype.bind = function(aTargetObject, aMethodName) {
    return function(arg){return aTargetObject[aMethodName](arg)}
}

/**
 * Since JS cannot handle default values in the function definition,
 * this function will return the arg or the default if arg is undefined.
 * @param aArg - the argument
 * @param aDef - the default
 * @returns Arg unless it is undefined, else Default.
 */
BasicObj.prototype.argOrDefault = function(aArg, aDef) {
	return ((typeof aArg) !== 'undefined') ? aArg : aDef;
}

/**
 * Random function [min ... max].  Avoid negatives.
 * @param aMin - minimum.
 * @param aMax - maximum.
 * @returns Returns a random from min to max inclusive.
 */
BasicObj.prototype.randomFromInterval = function(aMin,aMax) {
    return Math.floor(Math.random()*(aMax-aMin+1)+aMin);
}

/**
 * Sometimes the image is not loaded yet and we wish to draw it.
 */
BasicObj.prototype.drawImage = function(aContext, aImage, x,y,w,h) {
	w = this.argOrDefault(w,aImage.width);
	h = this.argOrDefault(h,aImage.height);
	if (aImage.complete) {
		aContext.drawImage(aImage,x,y,w,h);
	} else {
		aImage.onload = function() { aContext.drawImage(aImage,x,y,w,h); }
	}
}

/**
 * Generic "set event handler".
 * @param aEvent - event to listen to
 * @param aHandler - handler to bind, if string will bind to this.methodname
 * @param aObj - element to set the listener, defaults to window if not passed in
 */
BasicObj.prototype.addEvent = function(aEvent, aHandler, aObj) {
	var theHandler = (typeof aHandler != 'string') ? aHandler : this.bind(this,aHandler);
	var theObj = aObj || window;
	if (theObj.addEventListener) { // W3C standard
		theObj.addEventListener(aEvent,theHandler,false);
	} else if (theObj.attachEvent) { // Microsoft
		theObj.attachEvent( 'on'+aEvent,theHandler);
	} else {
		theObj['on'+aEvent]=theHandler;
	}
}

/**
 * Cheap-ass mobile detection mechanism.  Seriously, use something better for production.
 * @returns {Boolean}
 */
BasicObj.prototype.couldBeMobileBrowser = function() {
	return (navigator.userAgent.match(/ipad|iphone|ipod|android|webos|blackberry/i) != null) || (screen.width<=480);
}

/**
 * @returns The client browser width.
 */
BasicObj.prototype._gbw = function() {
	var w=window, d=document, e=d.documentElement, g=d.getElementsByTagName('body')[0];
    this.mBrowserWidth = w.innerWidth || e.clientWidth || g.clientWidth;
    this.mBrowserHeight = w.innerHeight|| e.clientHeight || g.clientHeight;
	return this.mBrowserWidth;
}

/**
 * @returns The client browser width.
 */
BasicObj.prototype.getBrowserWidth = function() {
	return this.mBrowserWidth || this._gbw();
}

