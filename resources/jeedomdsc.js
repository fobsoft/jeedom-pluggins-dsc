// Fonction pour le pool de request, pour s'assurer de la transmission dans l'ordre de reception
requestData = {
  called: false,
  length: 0,

  addRequest: function addRequest (data) {
    // Creation de l'url de la request
    data.smartURL = this.createUrlRequest(data);
    
    // Ajout d'un compteur de tentative
    data.tryProcessingNumber = 1;
    
    // Ajout de l'object a la liste d'envoi
    [].push.call(this, data);
    
    // Appel de la function d'envoi
    this.execRequest();
  },

  createUrlRequest: function createUrlRequest (data) {
    return urlJeedom + "&type=dsc&" + Object.keys(data).map(function(key){ 
      return encodeURIComponent(key) + '=' + encodeURIComponent(data[key]); 
    }).join('&');
  },
  
  removeRequest: function removeRequest () {
    [].shift.call(this);
  },

  execRequest: function sendRequest () {
    // On bloque un double appel, puisque celle-ci ce rappel tant qu'il y a quelque chose dans le pool
    if (!this.called) {
      this.called = true;
      this.sendNextRequest();
    }
  },
  
  sendNextRequest: function sendNextRequest () {
    if (this.length > 0) {
      console.log('sendRequestHttpUrl:',this[0].smartURL);
      
      request(this[0].smartURL, function (error, response, body) {
        if (error || response.statusCode != 200) {
          if (typeof response.statusCode !== "undefined") {
            console.log('Erreur requestHttp::',response.statusCode,' - ',requestData[0].smartURL);
          }
          else {
            console.log('Erreur requestHttp::','Code undefined',' - ',requestData[0].smartURL);
          }
          requestData[0].tryProcessingNumber += 1;
          
          // Si 10 tantative echoue
          if (requestData[0].tryProcessingNumber >= 10) {
            // Si c'est un type trouble de communication de jeedom on passe a la prochaine
            if (requestData[0].messagetype == "trouble" && requestData[0].value == 1000) {
              requestData.removeRequest();
            }
            // Sinon on remplace la resquest courrante par un type trouble de communication associe a jeedom
            else {
              // Creation de l'url de la request
              requestData[0].smartURL = requestData.createUrlRequest({'messagetype':'trouble','value':1000,'troublecode':response.statusCode});
              
              // Reset du compteur de tentative
              requestData[0].tryProcessingNumber = 1;
            }
          }
        }
        // Si aucune erreur on efface la request pour passer a la suivante
        else {
          requestData.removeRequest();
        }
        // On rappel la fonction
        requestData.sendNextRequest();
      });
    }
    else {
      this.called = false;
    }
  }
};

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
