var nap = require('./nodealarmproxy.js');

var urlJeedom = '';
var password = '';
var addr = '';
var port = '';
var zone = '';
var partition = '';
var command = '';

//var https = require('https');
var request = require('request');

process.env.NODE_TLS_REJECT_UNAUTHORIZED = "0";

// print process.argv
process.argv.forEach(function(val, index, array) {

	switch ( index ) {
		case 2 : urlJeedom = val; break;
		case 3 : password = val; break;
		case 4 : addr = val; break;
		case 5 : port = val; break;
		case 6 : zone = val; break;
    case 7 : partition = val; break;
	}

});

var alarm = nap.initConfig({ password:password,
	serverpassword:password,
	actualhost:addr,
	actualport:port,
	serverhost:'0.0.0.0',
	serverport:port,
	zone:zone,
	partition:partition,
	proxyenable:true,
	atomicEvents:true
});

var watchevents = ['601','602','603','604','605','606','609','610','650','620','621','622','623','624','625','626','631','632','651','652','653','654','655','656','657','658','659','800','801','802','803'];

alarm.on('data', function(data) {
	console.log('npmtest data:',data);
});

alarm.on('zoneupdate', function(data) {
	console.log('npmtest zoneupdate:',data);
	if (watchevents.indexOf(data.code) != -1) {
		var smartURL = urlJeedom + "&type=dsc&messagetype=zone&id="+data.zone+"&value="+data.code;
		console.log('smartURL:',smartURL);
		request(smartURL, function (error, response, body) {
	if (!error && response.statusCode == 200) {
	console.log('npmtest systemupdate:',response.statusCode);
	}
});
	}
});

alarm.on('partitionupdate', function(data) {
	console.log('npmtest partitionupdate:',data);
	if (watchevents.indexOf(data.code) != -1) {
		var smartURL = urlJeedom + "&type=dsc&messagetype=partition&id="+data.partition+"&value="+data.code;
		console.log('smartURL:',smartURL);
		request(smartURL, function (error, response, body) {
	if (!error && response.statusCode == 200) {
	console.log('npmtest systemupdate:',response.statusCode);
	}
});
	}
});

alarm.on('partitionuserupdate', function(data) {
	console.log('npmtest partitionuserupdate:',data);
});

alarm.on('systemupdate', function(data) {
	console.log('npmtest systemupdate:',data);
});
