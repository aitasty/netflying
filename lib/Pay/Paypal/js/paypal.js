/**! 
 * Paypal.js Copyright 2021, Printio (Hebin) 
 * https://developer.paypal.com/docs/checkout/reference/customize-sdk/
 * https://developer.paypal.com/docs/business/javascript-sdk/javascript-sdk-reference/
 * 
 * example:
       var app = new Paypal({
                'braintree':true,
                'token_url':'/api/testing/token',
                'customer_id': '278586407',
                'create_agreement_url':'/api/testing/agreement',
                'approve_agreement_url':'/api/testing/agreementApprove',
                'create_payment_url': '/api/testing/payment',
                'approve_payment_url':'/api/testing/executePayment',
        });
        var boundUrl = '/api/testing/boundCustomer'
       //braintree bound
       app.paymethod('#bound',{
                'displayName':'displayName',
                'billingAgreementDescription':'billingAgreementDescription'
            },{
                'render':function(){
                    console.log('is render');
                },
                'error':function(err){
                    console.log('is error',err);
                },
                'cancel':function(){
                    console.log('is cancel');
                },
                'approve':function(payload,approve){
                    console.log('is approve',payload);
                    approve(boundUrl,{customer:payload.details},function(res,state){
                        console.log(res,state);
                    });
                }
        });
        //braintree or paypal agreement
        app.agreement('#agreement',{
                'a':111
        },{
                'render':function(){
                    console.log('is render');
                },
                'error':function(err){
                    console.log('is error',err);
                },
                'cancel':function(){
                    console.log('is cancel');
                },
                'approve':function(data){
                    //data : approve_payment_url return result;
                    console.log(data);
                }
        });
        //braintree or paypal payment
        app.checkout('#paypal-button',{
            'amount':'20.06',
            'currency':'USD',
            'total':'20.06',
            'subtotal':'20.06',  

            // 'recipientName':'Phyllis',
            // 'line1': '611 Martin Luther king Jr dr', //optional
            // 'line2': '',//optional
            // 'city': 'Jackson',
            // 'countryCode': 'US',
            // 'postalCode': '49203',
            // 'state': 'MI',
            // 'phone': '5177834984'  //optional
        },{
            'render':function(){
                console.log('is render');
            },
            'error':function(){
                console.log('is error');
            },
            'cancel':function(){
                console.log('is cancel');
            },
            'approve':function(payload,approve){
                //if braintree
                approve('/api/testing/braintreeCheckout',{},function(res,state){
                    console.log(res,state);
                });
                //else paypal
                //payload: approve_payment_url return result;
            },
            'approveEnd':function(data){
                console.log('approveEnd',data);
            }
        });
 *      
 * 
 */
(function (g, t) {
    "object" == typeof exports && "undefined" != typeof module ? module.exports = t() :
        "function" == typeof define && define.amd ? define(t) :
            (g = g || self).Paypal = t()
}(this, (function () {
    "use strict";
    // Object.assign Used to copy the values of all enumerable properties from one or more source objects to the target object, returning the target object
    if (typeof Object.assign != 'function') {
        Object.assign = function (target) {
            // .length of function is 2
            if (target == null) {
                // TypeError if undefined or null
                throw new TypeError('Cannot convert undefined or null to object');
            }
            var to = Object(target);
            for (var index = 1; index < arguments.length; index++) {
                var nextSource = arguments[index];
                if (nextSource != null) {
                    // Skip over if undefined or null
                    for (var nextKey in nextSource) {
                        // Avoid bugs when hasOwnProperty is shadowed
                        if (Object.prototype.hasOwnProperty.call(nextSource, nextKey)) {
                            to[nextKey] = nextSource[nextKey];
                        }
                    }
                }
            }
            return to;
        };
    }
    //Object deep assign or clone
    if (typeof Object.deepAssign != 'function') {
        Object.deepAssign = function (target) {
            if (target == null) {
                return null;
            }
            var to = Object(target);
            var targetKeys = Object.keys(to), is_clone = targetKeys.length > 0 ? 0 : 1;
            for (var index = 1; index < arguments.length; index++) {
                var nextSource = arguments[index];
                if (nextSource != null) {
                    // Skip over if undefined or null
                    for (var nextKey in nextSource) {
                        // Avoid bugs when hasOwnProperty is shadowed
                        if (Object.prototype.hasOwnProperty.call(nextSource, nextKey)) {
                            if (typeof nextSource[nextKey] === 'object') {
                                to[nextKey] = is_clone === 1 ? Object.deepAssign({}, to[nextKey], nextSource[nextKey]) : Object.deepAssign(to[nextKey], nextSource[nextKey]);
                            } else {
                                to[nextKey] = nextSource[nextKey];
                            }
                        }
                    }
                }
            }
            return to;
        }
    }

    var class2type = {};
    Array.prototype.forEach.call(['Boolean', 'Number', 'String', 'Function', 'Array', 'Date', 'RegExp', 'Object', 'Error'], function (item, index) {
        class2type["[object " + item + "]"] = item.toLowerCase();
    });

    var isType = function (obj) {
        return obj == null ? String(obj) : class2type[{}.toString.call(obj)] || "object";
    };
    var isFunction = function (value) { return isType(value) === "function"; };
    var isObject = function (obj) {
        return isType(obj) === "object";
    };
    var isEmptyObject = function (o) {
        for (var p in o) {
            if (p !== undefined) {
                return false;
            }
        }
        return true;
    };
    var isWindow = function (obj) { return obj != null && obj === obj.window; };
    var isArray = Array.isArray || function (object) { return object instanceof Array; };
    var isPlainObject = function (obj) {
        return isObject(obj) && !isWindow(obj) && Object.getPrototypeOf(obj) === Object.prototype;
    };
    var doEach = function (elements, callback, hasOwnProperty) {
        if (!elements) {
            return this;
        }
        if (typeof elements.length === 'number') {
            [].every.call(elements, function (el, idx) {
                return callback.call(el, idx, el) !== false;
            });
        } else {
            for (var key in elements) {
                if (hasOwnProperty) {
                    if (elements.hasOwnProperty(key)) {
                        if (callback.call(elements[key], key, elements[key]) === false) return elements;
                    }
                } else {
                    if (callback.call(elements[key], key, elements[key]) === false) return elements;
                }
            }
        }
        return this;
    };
    var now = function () {
        return (new Date()).getTime();
    }
    var parseJSON = JSON.parse;
    //ajax
    var jsonType = 'application/json';
    var htmlType = 'text/html';
    var rscript = /<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi;
    var scriptTypeRE = /^(?:text|application)\/javascript/i;
    var xmlTypeRE = /^(?:text|application)\/xml/i;
    var blankRE = /^\s*$/;
    var noop = function () { }
    var ajaxSettings = {
        type: 'GET',
        beforeSend: noop,
        success: noop,
        error: noop,
        complete: noop,
        context: null,
        xhr: function (protocol) {
            return new window.XMLHttpRequest();
        },
        accepts: {
            script: 'text/javascript, application/javascript, application/x-javascript',
            json: jsonType,
            xml: 'application/xml, text/xml',
            html: htmlType,
            text: 'text/plain'
        },
        timeout: 0,
        processData: true,
        cache: true
    };
    var ajaxBeforeSend = function (xhr, settings) {
        var context = settings.context
        if (settings.beforeSend.call(context, xhr, settings) === false) {
            return false;
        }
    };
    // status: "success", "notmodified", "error", "timeout", "abort", "parsererror"
    var ajaxComplete = function (status, xhr, settings) {
        settings.complete.call(settings.context, xhr, status);
    };
    var ajaxSuccess = function (data, xhr, settings) {
        settings.success.call(settings.context, data, 'success', xhr);
        ajaxComplete('success', xhr, settings);
    };
    // type: "timeout", "error", "abort", "parsererror"
    var ajaxError = function (error, type, xhr, settings) {
        settings.error.call(settings.context, xhr, type, error);
        ajaxComplete(type, xhr, settings);
    };
    var serialize = function (params, obj, traditional, scope) {
        var type, array = isArray(obj),
            hash = isPlainObject(obj);
        doEach(obj, function (key, value) {
            type = isType(value);
            if (scope) {
                key = traditional ? scope :
                    scope + '[' + (hash || type === 'object' || type === 'array' ? key : '') + ']';
            }
            // handle data in serializeArray() format
            if (!scope && array) {
                params.add(value.name, value.value);
            }
            // recurse into nested objects
            else if (type === "array" || (!traditional && type === "object")) {
                serialize(params, value, traditional, key);
            } else {
                params.add(key, value);
            }
        });
    }
    var serializeData = function (options) {
        if (options.processData && options.data && typeof options.data !== "string") {
            var contentType = options.contentType;
            if (!contentType && options.headers) {
                contentType = options.headers['Content-Type'];
            }
            //add: blob form data
            var formData = new FormData();
            var is_file = function (f) {
                var lastModified = '', type = '', size = '';
                try {
                    lastModified = f.lastModified || '';
                    type = f.type || '';
                    size = f.size || '';
                } catch (e) { }
                if (lastModified != '' && type != '' && size != '') {
                    return true;
                }
                return false;
            }
            var loopData = function (data, k) {
                var t = isType(data);
                k = k || '';
                if (t === 'object' && is_file(data)) {
                    if (k == '' || k == null) {
                        return true;
                    }
                    formData.append(k, data);
                } else if (t === 'array' || t === 'object') {
                    for (var i in data) {
                        var ki = k != '' && k != null ? k + '[' + i + ']' : i;
                        loopData(data[i], ki);
                    }
                } else {
                    if (k == '' || k == null) {
                        return true;
                    }
                    formData.append(k, data);
                }
            }
            loopData(options.data);
            options.data = formData;
            options.processData = false;
            options.contentType = false;
        }
        if (options.data && (!options.type || options.type.toUpperCase() === 'GET')) {
            options.url = appendQuery(options.url, options.data);
            options.data = undefined;
        }
    };
    var appendQuery = function (url, query) {
        if (query === '') {
            return url;
        }
        return (url + '&' + query).replace(/[&?]{1,2}/, '?');
    };
    var mimeToDataType = function (mime) {
        if (mime) {
            mime = mime.split(';', 2)[0];
        }
        return mime && (mime === htmlType ? 'html' :
            mime === jsonType ? 'json' :
                scriptTypeRE.test(mime) ? 'script' :
                    xmlTypeRE.test(mime) && 'xml') || 'text';
    };
    var parseArguments = function (url, data, success, dataType) {
        if (isFunction(data)) {
            dataType = success, success = data, data = undefined;
        }
        if (!isFunction(success)) {
            dataType = success, success = undefined;
        }
        return {
            url: url,
            data: data,
            success: success,
            dataType: dataType
        };
    };
    var ajax = function (url, options) {
        if (typeof url === "object") {
            options = url;
            url = undefined;
        }
        var settings = options || {};
        settings.url = url || settings.url;
        for (var key in ajaxSettings) {
            if (settings[key] === undefined) {
                settings[key] = ajaxSettings[key];
            }
        }
        serializeData(settings);
        var dataType = settings.dataType;
        if (settings.cache === false || ((!options || options.cache !== true) && ('script' === dataType))) {
            settings.url = appendQuery(settings.url, '_=' + now());
        }
        var mime = settings.accepts[dataType && dataType.toLowerCase()];
        var headers = {};
        var setHeader = function (name, value) {
            headers[name.toLowerCase()] = [name, value];
        };
        var protocol = /^([\w-]+:)\/\//.test(settings.url) ? RegExp.$1 : window.location.protocol;
        var xhr = settings.xhr(settings);
        var nativeSetHeader = xhr.setRequestHeader;
        var abortTimeout;
        setHeader('X-Requested-With', 'XMLHttpRequest');
        setHeader('Accept', mime || '*/*');
        if (!!(mime = settings.mimeType || mime)) {
            if (mime.indexOf(',') > -1) {
                mime = mime.split(',', 2)[0];
            }
            xhr.overrideMimeType && xhr.overrideMimeType(mime);
        }
        if (settings.contentType || (settings.contentType !== false && settings.data && settings.type.toUpperCase() !== 'GET')) {
            setHeader('Content-Type', settings.contentType || 'application/x-www-form-urlencoded');
        }
        if (settings.headers) {
            for (var name in settings.headers)
                setHeader(name, settings.headers[name]);
        }
        xhr.setRequestHeader = setHeader;
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                xhr.onreadystatechange = noop;
                clearTimeout(abortTimeout);
                var result, error = false;
                var isLocal = protocol === 'file:';
                if ((xhr.status >= 200 && xhr.status < 300) || xhr.status === 304 || (xhr.status === 0 && isLocal && xhr.responseText)) {
                    dataType = dataType || mimeToDataType(settings.mimeType || xhr.getResponseHeader('content-type'));
                    result = xhr.responseText;
                    try {
                        if (dataType === 'script') {
                            (1, eval)(result);
                        } else if (dataType === 'xml') {
                            result = xhr.responseXML;
                        } else if (dataType === 'json') {
                            result = blankRE.test(result) ? null : parseJSON(result);
                        }
                    } catch (e) {
                        error = e;
                    }

                    if (error) {
                        ajaxError(error, 'parsererror', xhr, settings);
                    } else {
                        ajaxSuccess(result, xhr, settings);
                    }
                } else {
                    var status = xhr.status ? 'error' : 'abort';
                    var statusText = xhr.statusText || null;
                    if (isLocal) {
                        status = 'error';
                        statusText = '404';
                    }
                    ajaxError(statusText, status, xhr, settings);
                }
            }
        };
        if (ajaxBeforeSend(xhr, settings) === false) {
            xhr.abort();
            ajaxError(null, 'abort', xhr, settings);
            return xhr;
        }
        if (settings.xhrFields) {
            for (var name in settings.xhrFields) {
                xhr[name] = settings.xhrFields[name];
            }
        }
        var async = 'async' in settings ? settings.async : true;
        try {
            xhr.open(settings.type.toUpperCase(), settings.url, async, settings.username, settings.password);
        } catch (e) { }
        for (var name in headers) {
            if (headers.hasOwnProperty(name)) {
                nativeSetHeader.apply(xhr, headers[name]);
            }
        }
        if (settings.timeout > 0) {
            abortTimeout = setTimeout(function () {
                xhr.onreadystatechange = noop;
                xhr.abort();
                ajaxError(null, 'timeout', xhr, settings);
            }, settings.timeout);
        }
        try { xhr.send(settings.data ? settings.data : null); } catch (e) { }
        return xhr;
    };

    var config = {
        'paypal_customize': {
            //js-sdk config
            //Your PayPal REST client ID. This identifies your PayPal account, and determines where any transactions are paid to. While you're testing in sandbox,   *  you can use client-id=sb as a shortcut.
            'client-id': null,
            //Set to true if the transaction sets up a billing agreement or subscription.
            'buyer-country': null,//default: automatic
            //Set to true if the transaction is completed on the PayPal review page or false if the amount captured changes after the buyer returns to your site. Not applicable for subscriptions.
            'commit': null, //default:true;(false,true)
            //The currency of the transaction or subscription plan. USD, CAD, EUR. default: USD
            'currency': null,
            //Enable debug mode for ease of debugging. Do not enable for production traffic.
            'debug': null,
            //capture, authorize, subscription, tokenize; (Determines whether the funds are captured immediately on checkout or if the buyer authorizes the funds to be captured later.) default: capture
            'intent': null,
            //The locale used to localize any components. PayPal recommends not setting this parameter, as the buyer's locale is automatically set by PayPal.
            //'locale':'automatic',
            //The merchant for whom you are facilitating a transaction.
            'merchant-id': null, //default: automatic
            //Set to true if the transaction sets up a billing agreement or subscription.
            'vault': null, //default: false
            //A comma-separated list of components to enable. Defaults to allow checkout buttons. Other components are optional.
            'components': null,
        },
        'braintree': false, //true:braintree; false:paypal smart button
        'timeout': 30000, //ajax set timeout
        //'clientId': '',//required, from token_url
        'token_url': '',     // required, token url, get init client-id (& braintree token)
        'create_agreement_url': '', // braintree = false; create billing agreement
        'approve_agreement_url': '',// braintree = false; approve billing agreement
        'create_payment_url': '', // braintree = false; 1. order url get payment token required
        'approve_payment_url': '',   // braintree = false; 2. required order complete by payment token
        'customer_id': '',   //braintree customer id
        'paypal_js': ['https://www.paypal.com/sdk/js'],
        'braintree_js': [
            'https://js.braintreegateway.com/web/3.73.1/js/client.min.js',
            'https://js.braintreegateway.com/web/3.73.1/js/paypal-checkout.min.js',
            'https://js.braintreegateway.com/web/3.73.1/js/data-collector.min.js',
        ],
    };

    function Paypal(_config) {
        this.config = isObject(_config) ? Object.deepAssign({}, config, _config) : config;
        var self = this;
        this.token = null;
        this.clientId = null;
        this.clientInstance = null;
        this.deviceData = null;
        this.paypalCheckoutInstance = null;
        this.paypalAgreement = {};
        this.paypalCheckout = {};
        this.paypalPaymethod = {};
        if (this.config['token_url'] == '') {
            console.log('token_url required');
            return false;
        }
        self.init();
    }
    Paypal.prototype = {
        constructor: Paypal,
        init: function () {
            var self = this,
                observer = [];
            observer.push(new Promise(function (resolve, reject) {
                resolve(1);
                setTimeout(function () {
                    reject(0);
                }, 30000);
            }));
            self.geInitId().then(function () {
                // var initJs = self.sdkJs();
                // observer.push(self.promiseLoader(initJs));
                doEach(self.config['paypal_js'], function (k, v) {
                    if (k == 0) {
                        return true;
                    }
                    observer.push(v);
                });
                if (self.config['braintree']) {
                    doEach(self.config['braintree_js'], function (k, v) {
                        observer.push(self.promiseLoader(v));
                    });
                }
                Promise.all(observer).then(function (values) {
                    self.run();
                }).catch(function (e) {
                    console.log('init promise exception:', e);
                });
            }).catch(function (e) {
                console.log('init token exception:', e);
            });
        },
        sdkJs: function (data) {
            var self = this, url = this.config['paypal_js'][0] || '';
            if (url == '') {
                return false;
            }
            data = data || {};
            var params = this.modeMerge(self.config['paypal_customize'], data);
            var keyValue = [];
            doEach(params, function (k, v) {
                if (v == null || typeof v == 'object') {
                    return true;
                }
                keyValue.push(k + '=' + v);
            });
            return url + '?' + keyValue.join('&');
        },
        run: function () {
            var self = this;
            if (self.config['braintree'] == true) {
                self.braintreeRun();
            } else {
                self.paypalRun();
            }
        },
        //custom reload paypal sdk
        loadPayPalSDK: function (params, func) {
            var self = this;
            var paypalSdk = self.sdkJs(params || {});
            if (paypalSdk == '') {
                return false;
            }
            self.promiseLoader(paypalSdk).then(function () {
                self.callback(func, null);
            }).catch(function (e) {
                console.log('loadPayPalSDK:', params, e);
            });
        },
        paypalRun: function () {
            var self = this;
            //bound
            if (!isEmptyObject(self.paypalAgreement)) {
                self.loadPayPalSDK({
                    'vault': true,
                    'intent': 'tokenize',
                    'components': 'buttons',
                }, function () {
                    doEach(self.paypalAgreement, function (idx, item) {
                        self.agreementItemRender(item);
                    });
                });
            } else if (!isEmptyObject(self.paypalCheckout)) {
                doEach(self.paypalCheckout, function (idx, item) {
                    self.loadCheckoutRender(item['data'], item['selector']);
                });
            }
            //else postposition
        },
        braintreeRun: function () {
            var self = this;
            self.braintreeClientInstance().then(function (success) {
                Promise.all([
                    self.braintreeDeviceData(),
                    self.braintreePaypalCheckoutInstance()
                ]).then(function (values) {
                    setTimeout(function () {
                        //bound
                        if (!isEmptyObject(self.paypalAgreement)) {
                            self.agreementRender();
                        } else if (!isEmptyObject(self.paypalPaymethod)) {
                            self.paymethodRender();
                        } else { //checkout
                            self.checkoutRender();
                        }
                    }, 0);
                }).catch(function (e) {
                    console.log('braintreeRun:', e);
                })
            }, function (err) {
                console.log(err);
            });
        },
        //braintree: create clientInstance
        braintreeClientInstance: function () {
            var self = this, token = self.token;
            return new Promise(function (resolve, reject) {
                braintree.client.create({
                    authorization: token
                }, function (err, clientInstance) {
                    if (err) {
                        reject('clientInstance Error:' + err);
                        return;
                    }
                    self.clientInstance = clientInstance;
                    resolve('clientInstance');
                });
            });
        },
        //braintree: create deviceData by clientInstance
        braintreeDeviceData: function () {
            var self = this, clientInstance = self.clientInstance;
            return new Promise(function (resolve, reject) {
                braintree.dataCollector.create({
                    client: clientInstance,
                    paypal: true
                }, function (err, dataCollectorInstance) {
                    if (err) {
                        reject('deviceData Error:' + err);
                        return;
                    }
                    self.deviceData = dataCollectorInstance.deviceData;
                    resolve('deviceData');
                });
            });
        },
        //braintree: create paypalCheckoutInstance by clientInstance
        braintreePaypalCheckoutInstance: function () {
            var self = this, clientInstance = self.clientInstance;
            return new Promise(function (resolve, reject) {
                braintree.paypalCheckout.create({
                    autoSetDataUserIdToken: true,
                    client: clientInstance
                }, function (paypalCheckoutErr, paypalCheckoutInstance) {
                    if (paypalCheckoutErr) {
                        reject('paypalCheckoutInstance Error:' + err);
                        return false;
                    }
                    self.paypalCheckoutInstance = paypalCheckoutInstance;
                    resolve('paypalCheckoutInstance');
                });
            });
        },
        paymethodData: function (data) {
            var options = { flow: 'vault' };
            data = data || {};
            var displayName = data['displayName'] || '';
            if (displayName != '') {
                options['displayName'] = displayName;
            }
            //Use this option to set the description of the preapproved payment agreement visible to customers in their PayPal profile during Vault flows. Max 255 characters.
            var billingAgreementDescription = data['billingAgreementDescription'] || '';
            if (billingAgreementDescription != '') {
                options['billingAgreementDescription'] = billingAgreementDescription;
            }
            return options;
        },
        /**
         * @param data
         * [
         *   'displayName' => '',
         *   'billingAgreementDescription' => ''
         * ] 
         * @returns 
         */
        paymethod: function (selector, data, callback) {
            var self = this;
            var render = 0; //init run, else oneself run
            try {
                render = self.paypalPaymethod[selector]['render'];
            } catch (e) { }
            self.paypalPaymethod[selector] = {
                'selector': selector,
                'data': data,
                'callback': callback,
                'render': render, //1:ready,2:render
            };
            if (self.config['braintree'] == true) { //目前只有braintree支持
                if (self.paypalCheckoutInstance != null) {
                    if (self.paypalPaymethod[selector]['render'] == 1) {
                        setTimeout(function () {
                            self.paymethod(selector, data, callback);
                        }, 300);
                    } else if (self.paypalPaymethod[selector]['render'] == 2) {
                        self.paymethodItemRender(self.paypalPaymethod[selector]);
                    }
                }
            } else {

            }
        },
        //braintree
        paymethodRender: function () {
            var self = this, paypalCheckoutInstance = self.paypalCheckoutInstance;
            paypalCheckoutInstance.loadPayPalSDK({
                'vault': true,
                'intent': 'tokenize',
                'components': 'buttons',
            }, function () {
                // window.paypal.Buttons is now available to use
                //paypal buttons
                doEach(self.paypalPaymethod, function (idx, item) {
                    self.paymethodItemRender(item);
                });
            });
        },
        paymethodItemRender: function (item) {
            var self = this;
            var selector = item['selector'], callback = item['callback'];
            self.paypalPaymethod[selector]['render'] = 1;
            var options = self.paymethodData(item['data']);
            var paypalCheckoutInstance = self.paypalCheckoutInstance;
            doEach(document.querySelectorAll(selector), function (idx, elm) {
                elm.innerHTML = "";
            });
            paypal.Buttons({
                fundingSource: paypal.FUNDING.PAYPAL,
                createBillingAgreement: function () {
                    return paypalCheckoutInstance.createPayment(options);
                },
                onApprove: function (data, actions) {
                    return paypalCheckoutInstance.tokenizePayment(data, function (err, payload) {
                        if (err) {
                            self.callback(callback, 'approveError', [err]);
                            return false;
                        }
                        // Submit `payload.nonce` to your server
                        //payload.details [countryCode,email,firstName,lastName,payerId,shippingAddress]
                        self.approve(callback, payload);
                    });
                },
                onCancel: function (data) {
                    self.callback(callback, 'cancel', [data]);
                },
                onError: function (err) {
                    self.callback(callback, 'error', [err]);
                }
            }).render(selector).then(function () {
                // The PayPal button is set up and ready to be used
                self.paypalPaymethod[selector]['render'] = 2;
                self.callback(callback, 'render');
            });
        },
        agreementData: function (data) {
            return data || {};
        },
        agreement: function (selector, data, callback) {
            var self = this;
            var render = 0; //init run, else oneself run
            try {
                render = self.paypalAgreement[selector]['render'];
            } catch (e) { }
            self.paypalAgreement[selector] = {
                'selector': selector,
                'data': data,
                'callback': callback,
                'render': render, //1:ready,2:render
            };
            if (self.config['braintree'] == true) {
                if (self.paypalCheckoutInstance != null) {
                    if (self.paypalAgreement[selector]['render'] == 1) {
                        setTimeout(function () {
                            self.agreement(selector, data, callback);
                        }, 300);
                    } else if (self.paypalAgreement[selector]['render'] == 2) {
                        self.agreementItemRender(self.paypalAgreement[selector]);
                    }
                }
            } else {
                if (self.clientId != null) {
                    self.loadPayPalSDK({
                        'vault': true,
                        'intent': 'tokenize',
                        'components': 'buttons',
                    }, function () {
                        self.agreementItemRender(self.paypalAgreement[selector]);
                    });
                }
            }
        },
        //braintree
        agreementRender: function () {
            var self = this, paypalCheckoutInstance = self.paypalCheckoutInstance;
            paypalCheckoutInstance.loadPayPalSDK({
                'vault': true,
                'intent': 'tokenize',
                'components': 'buttons',
            }, function () {
                // window.paypal.Buttons is now available to use
                //paypal buttons
                doEach(self.paypalAgreement, function (idx, item) {
                    self.agreementItemRender(item);
                });
            });
        },
        agreementItemRender: function (item) {
            var self = this;
            var selector = item['selector'], callback = item['callback'];
            self.paypalAgreement[selector]['render'] = 1;
            var options = self.agreementData(item['data']);
            // var paypalCheckoutInstance = self.paypalCheckoutInstance;
            doEach(document.querySelectorAll(selector), function (idx, elm) {
                elm.innerHTML = "";
            });
            paypal.Buttons({
                fundingSource: paypal.FUNDING.PAYPAL,
                createBillingAgreement: function () {
                    var url = self.config['create_agreement_url'];
                    return fetch(url, {
                        method: 'post',
                        headers: {
                            'content-type': 'application/json'
                        },
                        body: JSON.stringify(options)
                    }).then(function (res) {
                        return res.json();
                    }).then(function (data) {
                        return data.billingToken;
                    });
                },
                onApprove: function (data, actions) {
                        var execute_url = self.config['approve_agreement_url'];
                        return fetch(execute_url, {
                            method: 'post',
                            headers: {
                                'content-type': 'application/json'
                            },
                            body: JSON.stringify({
                                token: data.orderID,
                                billingToken: data.billingToken,
                                facilitatorAccessToken: data.facilitatorAccessToken
                            })
                        }).then(function (res) {
                            return res.json();
                        }).then(function (data) {
                            self.callback(callback, 'approve', [data]);
                        }).catch(function (err) {
                            self.callback(callback, 'approveError', [err]);
                            return false;
                        });
                },
                onCancel: function (data) {
                    self.callback(callback, 'cancel', [data]);
                },
                onError: function (err) {
                    self.callback(callback, 'error', [err]);
                }
            }).render(selector).then(function () {
                // The PayPal button is set up and ready to be used
                self.paypalAgreement[selector]['render'] = 2;
                self.callback(callback, 'render');
            });
        },
        loadCheckoutRender: function (data, selector) {
            var self = this;
            var checkout_data = self.checkoutData(data);
            self.loadPayPalSDK({
                'vault': false,
                'intent': 'capture',
                'components': 'buttons',
                'commit': true,
                'currency': checkout_data['currency'],
                'amount': checkout_data['amount']
            }, function () {
                self.checkoutItemRender(self.paypalCheckout[selector]);
            });
        },
        checkoutData: function (data) {
            var self = this;
            var shipping = self.modeMerge({
                recipientName: '', //optional
                line1: '', //optional
                line2: '',//optional
                city: '',
                countryCode: '',
                postalCode: '',
                state: '',
                phone: ''  //optional
            }, data || {});
            var money = self.modeMerge({
                amount: 0,   //Required
                currency: '', //Required, must match the currency passed in with loadPayPalSDK
                //smart button
                total: 0,
                subtotal: 0,
            }, data || {});
            var currency = self.config['currency'];
            if (money['currency'] == '' && currency != null && !isEmptyObject(currency)) {
                money['currency'] = currency;
            }
            var options = money;
            options['flow'] = 'checkout'; // Required
            options['intent'] = 'capture'; //Must match the intent passed in with loadPayPalSDK
            if (shipping['recipientName'] != '') {
                options['enableShippingAddress'] = true;
                options['shippingAddressEditable'] = true;
                options['shippingAddressOverride'] = shipping;
            }
            return options
        },
        checkout: function (selector, data, callback) {
            var self = this;
            var render = 0; //init run, else oneself run
            try {
                render = self.paypalCheckout[selector]['render'];
            } catch (e) { }
            self.paypalCheckout[selector] = {
                'selector': selector,
                'data': data,
                'callback': callback,
                'render': render, //1:ready,2:render
            };
            if (self.config['braintree'] == true) {
                if (self.paypalCheckoutInstance != null) {
                    if (self.paypalCheckout[selector]['render'] == 1) {
                        setTimeout(function () {
                            self.checkout(selector, data, callback);
                        }, 300);
                    } else if (self.paypalCheckout[selector]['render'] == 2) {
                        self.checkoutItemRender(self.paypalCheckout[selector]);
                    }
                }
            } else {
                if (self.clientId != null) {
                    if (self.paypalCheckout[selector]['render'] == 1) {
                        setTimeout(function () {
                            self.checkout(selector, data, callback);
                        }, 300);
                    } else if (self.paypalCheckout[selector]['render'] == 2) {
                        self.loadCheckoutRender(data, selector);
                    }
                }
            }


        },
        checkoutRender: function () {
            var self = this, paypalCheckoutInstance = self.paypalCheckoutInstance;
            var paypalOptions = {
                intent: 'capture'
            };
            var currency = "";
            try {
                doEach(self.paypalCheckout, function (idx, item) {
                    currency = item['data']['currency'] || '';
                });
            } catch (e) { }
            if (currency != '') {
                paypalOptions['currency'] = currency;
            }
            paypalCheckoutInstance.loadPayPalSDK(paypalOptions, function () {
                // window.paypal.Buttons is now available to use
                //paypal buttons
                doEach(self.paypalCheckout, function (idx, item) {
                    self.checkoutItemRender(item);
                });
            });
        },
        checkoutItemRender: function (item) {
            var self = this;
            var selector = item['selector'], callback = item['callback'];
            self.paypalCheckout[selector]['render'] = 1;
            var options = self.checkoutData(item['data']);
            var paypalCheckoutInstance = self.paypalCheckoutInstance;
            doEach(document.querySelectorAll(selector), function (idx, elm) {
                elm.innerHTML = "";
            });
            console.log(options);
            paypal.Buttons({
                fundingSource: paypal.FUNDING.PAYPAL,
                createOrder: function () {
                    if (self.config['braintree'] == true) {
                        return paypalCheckoutInstance.createPayment(options);
                    } else {
                        var url = self.config['create_payment_url'];
                        return fetch(url, {
                            method: 'post',
                            headers: {
                                'content-type': 'application/json'
                            },
                            body: JSON.stringify(options)
                        }).then(function (res) {
                            // console.log('res', res);
                            return res.json();
                        }).then(function (data) {
                            // console.log('data', data);
                            return data.token;
                        });

                    }
                },
                onApprove: function (data, actions) {
                    if (self.config['braintree'] == true) {
                        return paypalCheckoutInstance.tokenizePayment(data, function (err, payload) {
                            if (err) {
                                self.callback(callback, 'approveError', [err]);
                                return false;
                            }
                            // Submit `payload.nonce` to your server
                            self.approve(callback, payload, { amount: options['amount'], currency: options['currency'] });
                        });
                    } else {
                        var execute_url = self.config['approve_payment_url'];
                        return fetch(execute_url, {
                            method: 'post',
                            headers: {
                                'content-type': 'application/json'
                            },
                            body: JSON.stringify({
                                paymentID: data.paymentID,
                                payerID: data.payerID
                            })
                        }).then(function (res) {
                            return res.json();
                        }).then(function (data) {
                            self.callback(callback, 'approve', [data]);
                        }).catch(function (err) {
                            self.callback(callback, 'approveError', [err]);
                            return false;
                        });
                    }
                },
                onCancel: function (data) {
                    self.callback(callback, 'cancel', [data]);
                },
                onError: function (err) {
                    self.callback(callback, 'error', [err]);
                }
            }).render(selector).then(function () {
                // The PayPal button is set up and ready to be used
                self.paypalCheckout[selector]['render'] = 2;
                self.callback(callback, 'render');
            });
        },
        approve: function (callback, payload, data) {
            var self = this;
            self.callback(callback, 'approve', [payload, function (url, params, func) {
                if (isType(params) == 'function') {
                    func = params;
                }
                data = data || {};
                var d = { 'nonce': payload.nonce, 'deviceData': self.deviceData };
                d = Object.assign({}, d, data);
                var post = Object.assign({}, d, params);
                self.ajaxJson(url, post, function (res, state) {
                    self.callback(func, null, [res, state]);
                });
            }]);
        },
        //utils
        modeMerge: function (mode, data) {
            var d = {};
            doEach(mode, function (k, v) {
                var value = null;
                try {
                    value = data[k];
                } catch (e) { }
                if (value == null) {
                    value = v;
                }
                d[k] = value;
            });
            return d;
        },
        jsArr: {},
        jsLoader: function (scriptSrc, callback, flag) {
            if (!this.jsArr[scriptSrc]) {
                flag = flag || 'async';
                this.jsArr[scriptSrc] = true;
                var head = document.getElementsByTagName('head')[0];
                var script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = scriptSrc;
                if (flag == 'async') { script.async = true; }
                if (flag == 'defer') { script.defer = true; }
                // then bind the event to the callback function
                // there are several events for cross browser compatibility
                //script.onreadystatechange = callback;
                script.onload = callback;
                // fire the loading
                head.appendChild(script);
            } else if (callback) {
                callback();
            }
        },
        promiseLoader: function (scriptSrc, callback, flag) {
            var self = this;
            return new Promise(function (resolve, reject) {
                self.jsLoader(scriptSrc, function () {
                    isType(callback) === 'function' ? callback() : '';
                    resolve(scriptSrc);
                }, flag);
                setTimeout(function () {
                    reject(scriptSrc);
                }, 30000);
            });
        },
        geInitId: function () {
            var self = this;
            return new Promise(function (resolve, reject) {
                self.ajaxJson(self.config['token_url'], { customer_id: self.config['customer_id'] }, function (res, state) {
                    if (state != 1) {
                        reject('token exception')
                        return false;
                    }
                    var token = '', clientId = '';
                    try {
                        clientId = res.data.clientId || '';
                        token = res.data.token || '';
                    } catch (e) { }
                    if (clientId == '') {
                        reject('clientId exception')
                        return false;
                    }
                    if (self.config['braintree'] == true && token == '') {
                        reject('token exception')
                        return false;
                    }
                    self.config['paypal_customize']['client-id'] = self.clientId = clientId;
                    self.token = token;
                    resolve(self.clientId);
                });
            });
        },
        ajaxJson: function (url, d, callback, timeout) {
            timeout = timeout || this.config['timeout'];
            ajax(url, {
                data: d, dataType: 'json', async: true, type: 'post', timeout: timeout,
                success: function (json) {
                    (typeof callback == 'function') ? callback(json, 1) : false;
                    return true;
                },
                error: function (xhr, type, errorThrown) {
                    (typeof callback == 'function') ? callback(false, 0) : false;
                    return false;
                }
            });
        },
        callback: function (callback, key, data) {
            data = data || [];
            var func = noop;
            if (key == null) {
                func = isType(callback) === 'function' ? callback : noop;
            } else {
                func = isType(callback[key]) === 'function' ? callback[key] : noop;
            }
            func.apply(null, data);
        }
    }
    //var index = window.Braintree || function(options){return new Braintree(options);};
    var index = window.Paypal || Paypal;
    window.Paypal = index;
    return index;
})));