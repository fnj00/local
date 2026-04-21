'use strict';

/* Controllers */


var suggestControllers = angular.module('suggestControllers', ['ngMaterial']).factory("sharedService", function($rootScope){
  var mySharedService = {};
  mySharedService.values = {};
  mySharedService.passData = function(newData){
    $rootScope.$broadcast('dataPassed');
  }

  return mySharedService;
});

suggestControllers.controller('LoginCtrl', ['$scope', '$location', '$facebook','$route', function($scope, $location, $facebook, $route) {
  $scope.fbStatus = $facebook.getLoginStatus()
  .then(function (login){
    console.log(login.status);
    if(login.status !== 'connected'){
      console.log('Not Connected');
    } else {
      console.log('logging in');
      $location.path('/event');
    }
  });

  $scope.submit = function() {
    console.log('here');
    if ($scope.text) {
      $location.path('/event/' + $scope.text);
    }
  };

  $scope.openMenu = function($mdOpenMenu, ev){
    console.log('menu Opened');
    var originatorEv;
    originatorEv = ev;
    $mdOpenMenu(ev);
  };
}]);

suggestControllers.controller('SearchCtrl', SearchCtrl);
function SearchCtrl($scope, $http, $location, $sce, $routeParams, $facebook, $localstorage) {
  $scope.displayData = function (){
    $http.get('data/nextEvent.php').then(function(response) {
      if (response.data) {
        $scope.nextEvent = response.data;
        $scope.eventDate = $scope.nextEvent.date;
        $scope.eventPhoto = $scope.nextEvent.photo;
        if(response.data.error){
          console.log(response.data.error, $location.path().split('/')[1]);
          $location.path('/' + $location.path().split('/')[1]);
        }
        console.log(response.data);
      } else {
        console.log(response.data.errors);
      }
    });
  };

  $scope.displayData();

  $scope.submit = function() {
    if ($scope.text){
      $location.path('/event/' + $scope.text);
    }
  };
	
  $scope.openMenu = function($mdOpenMenu, ev){
    console.log($scope);
    console.log('menu Opened');
    var originatorEv;
    originatorEv = ev;
    $mdOpenMenu(ev);
  };

  $scope.user = JSON.parse(localStorage.getItem('user'));
  if($scope.user){
    $scope.adminPriv = $scope.user.id === "10155961901764667";
  }
};

suggestControllers.controller('EventSearchCtrl', EventSearchCtrl);
function EventSearchCtrl($scope, $http, $location, $window, $sce, $routeParams, $facebook, $localstorage) {
  console.log($routeParams);
  const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

  function nth(day) {
    if(day>3 && day<21) return 'th'; // thanks kennebec
    switch (day % 10) {
      case 1:  return "st";
      case 2:  return "nd";
      case 3:  return "rd";
      default: return "th";
    }
  }

  if (typeof $routeParams.eventId === "undefined") {
    $scope.displayData = function (){
      $http.get('data/nextEvent.php').then(function(response) {
        if (response.data) {
          $scope.eventDetails = response.data;
          const d = new Date($scope.eventDetails.date);
          $scope.eventDateFormat = monthNames[d.getUTCMonth()] + ' ' + d.getUTCDate() +  nth(d.getUTCDate()) + ' ' + d.getFullYear();
          $scope.eventPhoto = $scope.eventDetails.photo;
          if(response.data.error){
            console.log(response.data.error, $location.path().split('/')[1]);
            $location.path('/' + $location.path().split('/')[1]);
          }
          $localstorage.set('eventId', $scope.eventDetails.id);
          console.log($scope.eventDetails.id);
          console.log(response.data);
        } else {
          console.log(response.data.errors);
        }
      });
    };
  } else {
    $localstorage.set('eventId', $location.path().split('/')[2]);
    console.log($localstorage.get('eventId'));
    $scope.displayData = function (){
      $http.get('data/eventDetails.php?id=' + $routeParams.eventId).then(function(response) {
        if (response.data) {
          $scope.eventDetails = response.data;
          const d = new Date($scope.eventDetails.date);
          $scope.eventDateFormat = monthNames[d.getMonth()] + ' ' + d.getUTCDate() +  nth(d.getUTCDate()) + ' ' + d.getFullYear();
          $scope.eventPhoto = $scope.eventDetails.photo;
          if(response.data.error){
            console.log(response.data.error, $location.path().split('/')[1]);
            $location.path('/' + $location.path().split('/')[1]);
          }
        } else {
          console.log(response.data.errors);
        }
      });
    };
  }
  $scope.displayData();
  $scope.fbStatus = $facebook.getLoginStatus()
  .then(function (login){
    if(login.status !== 'connected'){
      console.log('Not Connected');
    } else {
      console.log('Connected');
    }
  });
  $scope.submit = function() {
    if ($scope.text) {
      $location.path('/event/' + $scope.text);
    }
  };
  $scope.openMenu = function($mdOpenMenu, ev){
    console.log($scope);
    console.log('menu Opened');
    var originatorEv;
    originatorEv = ev;
    $mdOpenMenu(ev);
  };
  $scope.user = JSON.parse(localStorage.getItem('user'));
  if($scope.user){
    $scope.adminPriv = $scope.user.id === "10155961901764667";
  }
};

suggestControllers.controller('EventCtrl', EventCtrl);

function EventCtrl($scope, $http, $location, $routeParams, $sce, $facebook, $localstorage) {
  console.log('EventCtrl Loaded');
  $scope.eventId = $routeParams.votes;
  $scope.eventDate = "1";
  $scope.displayData = function (){
    $http.get('data/eventDetails.php?id=' + $routeParams.votes).then(function(response) {
      if (response.data) {
        $scope.eventDetails = response.data;
        $scope.eventDate = $scope.eventDetails.date;
        if(response.data.error){
          console.log(response.data.error, $location.path().split('/')[1]);
          $location.path('/' + $location.path().split('/')[1]);
        }
      } else {
        console.log(response.data.errors);
      }
    });
  };
  $scope.displayData();

  $scope.suggest = function() {
    console.log($scope.eventId);
    if ($scope.eventId) {
      $location.path('/suggest/' + $scope.eventId);
    }
  };

  $scope.photo = function() {
    console.log($scope.eventId);
    if ($scope.eventId) {
      $location.path('/photo/' + $scope.eventId);
    }
  };
  $scope.submit = function() {
    if ($scope.text) {
      $location.path('/voting/' + $scope.text);
    }
  };
  $scope.openMenu = function($mdOpenMenu, ev){
    console.log($scope);
    console.log('menu Opened');
    var originatorEv;
    originatorEv = ev;
    $mdOpenMenu(ev);
  };
  $scope.user = JSON.parse(localStorage.getItem('user'));
  if($scope.user){
    $scope.adminPriv = $scope.user.id === "10155961901764667";
  }
};

suggestControllers.controller('SongsQueuedCtrl', ['$scope', '$location', '$routeParams', '$http', '$facebook', 'sharedService',
  function($scope, $location, $routeParams, $http, $facebook, sharedService) {
    $http.get('data/jsonResults.php?id=' + $routeParams.votes).success(function(data) {
      $scope.votes = data;
    });
    $scope.eventId = $routeParams.votes;
    $scope.vote = function(obj) {
      //obj.voted = true;
      $http.post('data/addVote.php', obj).then(
        function(response) {
          if (response.data.success) {
            //Number(obj.userVotes++);
          } else {
            Swal.fire({
              icon: 'info',
	      title: 'Woops!',
	      text: response.data.errors
            });
          }
        },
        function(errResponse) {
          console.log(errResponse);
        });

      //Update Vote Count Locally.  
      if (!obj.voted) {
        Number(obj.userVotes++);
      }

      //Set Voted to true
      obj.voted = true;

      //Passing in EventId
      obj.eventId = $scope.eventId;
    };

    $scope.played = function(obj, toggleVal) {
      //obj.voted = true;
      obj.played = toggleVal;
      console.log(obj);
      $http.post('data/toggleSong.php', obj).then(
        function(response) {
          if (response.data.success) {
            console.log(response.data);
            sharedService.passData($scope.votes);
          } else {
            console.log(response.data.errors);
          }
        },
        function(errResponse) {
          console.log(errResponse);
        });

      //Passing in EventId
      obj.eventId = $scope.eventId;
    };

    $scope.resend = function(obj, toggleVal) {
      //obj.voted = true;
      obj.played = toggleVal;
      console.log(obj);
      if (obj.songArtist && obj.songTitle) {
        $http({
          method  : 'POST',
          url     : 'https://virtualdj.com/ask/fnj00',
          data    : 'message='+obj.songTitle +' '+obj.songArtist+'&name='+obj.requestedBy,//obj, //forms user object
          headers : {'Content-Type': 'application/x-www-form-urlencoded'}
         });
       }
     };

     $scope.submit = function() {
       if ($scope.text) {
         $scope.eventId = this.text;
         $location.path('/voting/' + $scope.text);
       }
     };
   }
]);

suggestControllers.controller('SongsPlayedCtrl', ['$scope', '$route', '$routeParams', '$http', '$facebook', 'sharedService',
  function($scope, $route, $routeParams, $http, $facebook, sharedService) {
    $http.get('data/jsonResults.php?id=' + $routeParams.votes + '&show=played').success(function(data) {
      $scope.votes = data;
    });
    $scope.eventId = $routeParams.votes;
    $scope.played = function(obj) {
      obj.played = 0;
      //obj.voted = true;
      console.log(obj);
      $http.post('data/toggleSong.php', obj).then(
        function(response) {
          if (response.data.success) {
            console.log(response.data);
            sharedService.passData($scope.votes);
          } else {
            console.log(response.data.errors);
          }
        },
        function(errResponse) {
          console.log(errResponse);
        });


      //FIXME:  use real FB ID.
      //Passing in EventId
      obj.eventId = $scope.eventId;
    };
  }
]);


suggestControllers.controller('SongsRemovedCtrl', ['$scope', '$route', '$routeParams', '$http', '$facebook', 'sharedService',
  function($scope, $route, $routeParams, $http, $facebook, sharedService) {
    $http.get('data/jsonResults.php?id=' + $routeParams.votes + '&show=removed').success(function(data) {
      $scope.votes = data;
    });
    $scope.eventId = $routeParams.votes;
    $scope.played = function(obj) {
      obj.played = 0;
      //obj.voted = true;
      console.log(obj);
      $http.post('data/toggleSong.php', obj).then(
        function(response) {
          if (response.data.success) {
            console.log(response.data);
            sharedService.passData($scope.votes);
          } else {
            console.log(response.data.errors);
          }
        },
        function(errResponse) {
          console.log(errResponse);
        });
      //FIXME:  use real FB ID.
      //Passing in EventId
      obj.eventId = $scope.eventId;
   };
  }
]);


suggestControllers.controller('SongSuggestCtrl', ['$scope', '$http', '$location','$routeParams', '$facebook','$sce',
  function($scope, $http, $location, $routeParams, $facebook, $sce) {
    var self = this;
    $http.get('data/eventDetails.php?id=' + $routeParams.eventId).then(function(response) {
      if (response.data) {
        if(response.data.error) {
          console.log(response.data.error, $location.path().split('/')[1]);
          $location.path('/');
          return;
        }
        $scope.eventDetails = response.data;
        console.log(response.data.date);
        $scope.eventDate = $scope.eventDetails.date;
        $scope.eventTitle = unescape($scope.eventDetails.title);
      } else {
        console.log(response.data.errors);
      }
    });
    $scope.eventId = $routeParams.eventId;
    $scope.openMenu = function($mdOpenMenu, ev){
      console.log('menu Opened');
      var originatorEv;
      originatorEv = ev;
      $mdOpenMenu(ev);
    };
  }
]);


suggestControllers.controller('DashboardCtrl', ['$scope', '$http', '$location','$routeParams',
  function($scope, $http, $location, $routeParams) { 
    var self = this;
    $http.get('data/eventDetails.php?id=' + $routeParams.eventId).then(function(response) {
    if (response.data) {
      if(response.data.error)
        {
          console.log(response.data.error, $location.path().split('/')[1]);
          $location.path('/');
          return;
        }
        $scope.eventDetails = response.data;
        console.log(response.data.date);
	$scope.eventId = $scope.eventDetails.eventId;
        $scope.eventDate = $scope.eventDetails.date;
        $scope.eventTitle = unescape($scope.eventDetails.title);

    } else {
      console.log(response.data.errors);
    }
    });
  }
]);

suggestControllers.controller('PhotoCtrl', ['$scope', '$http', '$location','$routeParams', '$facebook','$sce','sharedService',
  function($scope, $http, $location, $routeParams, $facebook, $sce, sharedService) {
    var self = this;
    $http.get('data/eventDetails.php?id=' + $routeParams.eventId).then(function(response) {
      if (response.data) {
        if(response.data.error) {
          console.log(response.data.error, $location.path().split('/')[1]);
          $location.path('/');
          return;
        }
        $scope.eventDetails = response.data;
        console.log(response.data.date);
        $scope.eventDate = $scope.eventDetails.date;
        $scope.eventTitle = unescape($scope.eventDetails.title);
        console.log($routeParams);
        $http.get('data/photoResults.php?id=' + $routeParams.eventId + '&show=queued&phone=y').success(function(data) {
          $scope.photospending = data;
        });
        $http.get('data/photoResults.php?id=' + $routeParams.eventId + '&show=approved&phone=y').success(function(data) {
          $scope.photosapproved = data;
        });
      } else {
        console.log(response.data.errors);
      }
    });
    $scope.eventId = $routeParams.eventId;
    $scope.openMenu = function($mdOpenMenu, ev){
      console.log('menu Opened');
      var originatorEv;
      originatorEv = ev;
      $mdOpenMenu(ev);
    };
  }
]);

suggestControllers.controller('LoginController', ['$scope', '$http', '$routeParams', 'AuthenticationService', '$log', '$route', '$location', '$localstorage',
    function($scope, $http, $routeParams, AuthenticationService, $log, $route, $location, $localstorage) {
        // reset login status
	AuthenticationService.ClearCredentials();

	$scope.login = function () {
            $scope.dataLoading = true;
            AuthenticationService.Login($scope.username, $scope.password, function(response) {
                if(response.success) {
                    AuthenticationService.SetCredentials($scope.username, $scope.password);
                    $location.path('/');
                } else {
                    $scope.error = response.message;
                    $scope.dataLoading = false;
                }
            });
        };
    }]);

suggestControllers.controller('FacebookCtrl', ['$scope', '$http', '$routeParams', '$facebook', '$log', '$route', '$location', '$localstorage',
    function($scope, $http, $routeParams, $facebook, $log, $route, $location, $localstorage) {
        var self = this;

        $scope.$on('fb.auth.authResponseChange', function() {
            $scope.status = $facebook.isConnected();

            if ($scope.status) {
                $facebook.api('/me').then(function(user) {
                    $scope.user = user;
                    console.log($scope.user, $route, $location);
                    $localstorage.setObject('user', user);
                });
            }
        });

        $scope.$on('fb.auth.statusChange', function(response) {
            $scope.status = $facebook.isConnected();

            console.log('StatusChange Response: ', response);
            if ($scope.status) {
                $facebook.api('/me').then(function(user) {
                    $scope.user = user;
                    refresh();
                });
            }
            else{
                $scope.user = null;
//                $localstorage.remove('eventId');
                console.log('Status changed Logging out');
                refresh();
            }
        });



        $scope.loginToggle = function() {
            if ($scope.status) {
                $facebook.logout();
            } else {
                $facebook.login();
            }
        };

        $scope.getFriends = function() {
            if (!$scope.status) return;
            $facebook.cachedApi('/me/friends').then(function(friends) {
                $scope.friends = friends.data;
            });
        };
        //Init the isLoggedIn variable.
        $scope.fb = {isLoggedIn : false };
        $scope.login = function() {
            $facebook.login().then(function() {
                $log.info('Logging In with Facebook', $scope);
                var eventPath = $localstorage.get('eventId');
                console.log($localstorage.get('eventId'));
                  location.reload();
            });
        };
        $scope.logout = function() {
            $facebook.logout().then(function(response) {
                console.log(response);
                $log.info('Logging Out with Facebook', $scope);
                $scope.fb.isLoggedIn = false;
		$localstorage.remove('eventId');
                refresh();
            });
        };

        function refresh() {
            $facebook.api("/me/?fields=first_name, last_name, name").then(
                function(response) {
                    $scope.welcomeMsg = "Welcome " + response.name;
                    $scope.fb.isLoggedIn = true;
                    $scope.getFullName = response.name;
                    $scope.getFirstName = response.first_name;
                    $scope.getLastName = response.last_name;
                    $scope.getFbId = response.id;
                    $log.info(response);
                },
                function(err) {
                    $log.info('Please log in with Facebook');
                    $log.info(err);
                    $scope.welcomeMsg = "Please log in";
                    $scope.fb.isLoggedIn = false;
                });
        }
    }
]);

suggestControllers.controller('genericCtrl', ['$scope', function($scope) {
    $scope.date = new Date();
}]);

suggestControllers.controller('PhotoUploadCtrl', PhotoUploadCtrl);

function PhotoUploadCtrl($timeout, $rootScope, $q, $log, $http, $routeParams, $location) {
  var self = this;
  window.addEventListener('message', function(e) {
    var $iframe = jQuery("#phonetest");
    var eventName = e.data[0];
    var data = e.data[1];
    if(typeof e.data[2] !== "undefined"){
      location.reload();
    } else {
      switch(eventName) {
        case 'setHeight':
        $iframe.height(data);
        break;
      }
    }
  }, false);
}

suggestControllers.controller('SongSearchCtrl', SongSearchCtrl);

function SongSearchCtrl($timeout, $q, $log, $http, $location) {
  var self = this;
  self.apiKey = '7d2ab11cb4424c8f2dec9ab26f48ce4a';
  self.simulateQuery = false;
  self.isDisabled = false;
  self.searchMadeOnSong = false;
  self.searchSong = searchSong;
  self.selectedSongItemChange = selectedSongItemChange;
  self.searchSongTextChange = searchSongTextChange;
  self.searchArtist = searchArtist;
  self.selectedArtistItemChange = selectedArtistItemChange;
  self.searchArtistTextChange = searchArtistTextChange;
  self.addSong = addSong;
  self.cancel = cancel;
  self.isSongEntered = false;
  self.isArtistEntered = false;

  function cancel(eventId) {
    console.log('cancelled');
    $location.path('/voting/' + eventId);
  }
	
  function addSong(eventId, fullName) {
    var obj = {
      "artist": self.searchArtistText,
      "song": self.searchSongText,
      "eventId": eventId,
      "message": self.searchSongText +' '+self.searchArtistText
    }
    console.log(obj);

    if (self.searchArtistText && self.searchSongText) {
      $http.post('data/addSong.php', obj).then(
        function(response) {
          if (response.data.success) {
            $location.path('/voting/' + eventId);
          } else {
            Swal.fire({
              icon: 'info',
              title: 'Woops!',
              text: response.data.message
            });
          }
        },
        function(errResponse) {
          console.log(errResponse);
        });
            
    } else {
      if (!self.searchSongText) {
        self.isSongEntered = true;
      } else {
        self.isSongEntered = false;
      }
      if (!self.searchArtistText) {
        self.isArtistEntered = true;
      } else {
        self.isArtistEntered = false;
      }
      Swal.fire({
        icon: 'error',
        title: 'Woops!',
        text: 'You must enter a song & artist!'
      });
    }
  }
    // ******************************
    // Internal methods
    // ******************************
    /**
     * Search for states... use $timeout to simulate
     * remote dataservice call.
     */
  function searchSong(song, artist) {
    var artistParam = "";
    if (artist) {
      artistParam = '&artist=' + artist;
    }
    self.searchMadeOnSong = true;
    return $http.get('https://ws.audioscrobbler.com/2.0/?method=track.search&track=' + song + artistParam + '&api_key='+ self.apiKey + '&format=json')
    .then(
      function(response) {
        if (response.data.results) {
          var result = response.data.results.trackmatches;
          return result.track;
        } else {
          console.log(response.data.errors);
        }
      },
      function(errResponse) {
        console.log(errResponse);
      }
    );
  }

  function searchArtist(query) {
    if (!self.searchMadeOnSong) {
      console.log('Not search made on Song first');
      return $http.get('https://ws.audioscrobbler.com/2.0/?method=artist.search&artist=' + query + '&api_key='+ self.apiKey + '&format=json')
      .then(
        function(response) {
          if (response.data.results) {
            var result = response.data.results.artistmatches;
            return result.artist;
          } else {
            console.log(response.data.errors);
          }
        },
        function(errResponse) {
          console.log(errResponse);
        }
      );
    }
    self.searchMadeOnSong = false;
  }


  function searchSongTextChange(text) {
    $log.info('Text changed to ' + text);
  }

  function selectedSongItemChange(item) {
    console.log(item);
    if (item) {
      self.searchArtistText = item.artist;
    }
  }

  function searchArtistTextChange(text) {
    $log.info('Text changed to ' + text);
  }

  function selectedArtistItemChange(item) {
    if (item) {
      self.searchArtistText = item.name;
    }
  }
}
