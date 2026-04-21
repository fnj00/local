'use strict';

/* Admin Controllers */


var adminControllers = angular.module('adminControllers', ['ngMaterial']).factory("sharedService", function($rootScope){

    var mySharedService = {};
    mySharedService.values = {};
    mySharedService.passData = function(newData, fbid){
        console.log(fbid);
        //mySharedService.values = newData;
        //console.log('Service Activated..passsing data', newData);
        $rootScope.$broadcast('dataPassed', fbid);
    }

    return mySharedService;
});

adminControllers.controller('AdminLoginCtrl', ['$scope', '$location', '$facebook','$route', function($scope, $location, $facebook, $route) {

    console.log($location, $scope);

    $scope.fbStatus = $facebook.getLoginStatus()
        .then(function (login){
            console.log(login.status);
            if(login.status !== 'connected'){
                console.log('Not Connected');
                //$route.reload();
                //$location.path('/');
            }
            else{
                console.log('logging in');
                $location.path('/voting');
            }
        });

    $scope.submit = function() {
        console.log('here');
        if ($scope.text) {
            $location.path('/voting/' + $scope.text);
        }
    };

    $scope.openMenu = function($mdOpenMenu, ev){
        console.log('menu Opened');
        var originatorEv;
        originatorEv = ev;
        $mdOpenMenu(ev);
    };
}]);

adminControllers.controller('AdminSearchCtrl', ['$scope', '$location', '$facebook', function($scope, $location, $facebook) {
    $scope.fbStatus = $facebook.getLoginStatus()
        .then(function (login){
            console.log(login.status);
            if(login.status !== 'connected'){
                console.log('Not Connected');
                //$route.reload();
                $location.path('/login');
            }
        });


    $scope.submit = function() {
		console.log('here');
        if ($scope.text) {
            $location.path('/voting/' + $scope.text);
            // window.location.href = '#/voting/'+$scope.text;
        }
    };
	
	$scope.openMenu = function($mdOpenMenu, ev){
		console.log('menu Opened');
		var originatorEv;
		originatorEv = ev;
      $mdOpenMenu(ev);
	};
}]);

adminControllers.controller('AdminPhotoCtrl', PhotoCtrl);

function PhotoCtrl($scope, $http, $location, $routeParams, $sce, $facebook) {
    var self = this;
    self.editEvent = editEvent;
    self.openMenu = openMenu;

    function editEvent(obj) {
        console.log('AdminEventMenuCtrl', obj);
        $location.path('/admin/editevent/'+obj.id);
    }

    function openMenu($mdOpenMenu, ev){
            console.log('menu Opened');
            var originatorEv;
            originatorEv = ev;
            $mdOpenMenu(ev);
    }

    $scope.eventId = $routeParams.eventId;
    $scope.fbStatus = $facebook.getLoginStatus()
        .then(function (login){
            console.log(login.status);
            if(login.status !== 'connected'){
                console.log('Not Connected');
                $location.path('/login');
            }
        })
        .then(function (){
            $scope.displayData();
        });

    $scope.displayData = function (){
        $http.get('data/eventDetails.php?id=' + $routeParams.eventId).then(function(response) {

            if (response.data) {
               $scope.eventDetails = response.data;
                var date = new Date($scope.eventDetails.date),
                    timezoneOffset = date.getTimezoneOffset() * 60000;
                $scope.eventDate = new Date(date.getTime() + timezoneOffset);

                $scope.eventTitle = $scope.eventDetails.title;
                if(response.data.error)
                {
                    console.log(response.data.error, $location.path().split('/')[1]);
                    $location.path('/' + $location.path().split('/')[1]);
                }
           } else {
                console.log(response.data.errors);
            }
        });
    };
}

adminControllers.controller('AdminEventCtrl', EventCtrl);

function EventCtrl($scope, $http, $location, $routeParams, $sce, $facebook) {
    var self = this;
    self.editEvent = editEvent;
    self.openMenu = openMenu;
    self.editEventPhotos = editEventPhotos;

    function editEvent(obj) {
        //obj.voted = true;
        console.log('AdminEventMenuCtrl', obj);
        $location.path('/admin/editevent/'+obj.id);
    }

    function editEventPhotos(obj) {
	console.log('AdminEventmenuCtrl', obj);
	$location.path('/admin/photos/'+obj.id);
    }

    function openMenu($mdOpenMenu, ev){
            console.log('menu Opened');
            var originatorEv;
            originatorEv = ev;
            $mdOpenMenu(ev);
    }

    $scope.eventId = $routeParams.votes;
    $scope.fbStatus = $facebook.getLoginStatus()
        .then(function (login){
            console.log(login.status);
            if(login.status !== 'connected'){
                console.log('Not Connected');
                $location.path('/login');
            }
        })
        .then(function (){
            $scope.displayData();
        });

    $scope.displayData = function (){
        $http.get('data/eventDetails.php?id=' + $routeParams.votes).then(function(response) {

            if (response.data) {
                //$http.get('http://localhost:8080/beathouse/suggest/jsonResults.php?id=' + $routeParams.votes+'&fbid=5555').success(function(data) {
                $scope.eventDetails = response.data;
                var date = new Date($scope.eventDetails.date),
                    timezoneOffset = date.getTimezoneOffset() * 60000;
                $scope.eventDate = new Date(date.getTime() + timezoneOffset);

                $scope.eventTitle = $scope.eventDetails.title;
                if(response.data.error)
                {
                    console.log(response.data.error, $location.path().split('/')[1]);
                    $location.path('/' + $location.path().split('/')[1]);
                }
                //Number(obj.userVotes++);
            } else {
                console.log(response.data.errors);
            }
        });
    };

    $scope.suggest = function() {
        console.log($scope.eventId);
        if ($scope.eventId) {
            $location.path('/suggest/' + $scope.eventId);
        }
    };


    $scope.submit = function() {
        if ($scope.text) {
            $location.path('/voting/' + $scope.text);
            // window.location.href = '#/voting/'+$scope.text;
        }
    };



    $scope.cancel = function()
    {
        $location.path('/admin/'+$scope.eventId);
    };

    $scope.updateEvent = function(){
        console.log('update event pressed');
        var obj = {};
        obj.action = 'update_event';
        obj.title = $scope.eventTitle;
        obj.date = $scope.eventDate;
        obj.eventId = $scope.eventId;
        $http.post('data/admin/index.php', obj).then(
            //$http.post('http://localhost:8080/beathouse/suggest/addVote.php', obj).then(
            function(response) {
                if (response.data.success) {
                    $location.path('/admin/'+obj.eventId);
                    //Number(obj.userVotes++);
                } else {
                    console.log(response.data.errors);
                }
            },
            function(errResponse) {
                console.log(errResponse);
            });
    };
}


adminControllers.controller('AdminSongsQueuedCtrl', ['$scope', '$location', '$routeParams', '$http', '$facebook', 'sharedService',
    function($scope, $location, $routeParams, $http, $facebook, sharedService) {

        $scope.$on('dataPassed', function (me, fbid) {
            //$scope.newItems = sharedService.values;
            //console.log('dataPassed',sharedService.values );
            $http.get('data/jsonResults.php?id=' + $routeParams.votes + '&fbid=' + fbid).success(function(data) {
                $scope.votes = data;
            });
        });


        $scope.user = $facebook.cachedApi('/me').then(function(response) {
                $http.get('data/jsonResults.php?id=' + $routeParams.votes + '&fbid=' + response.id).success(function(data) {
                    $scope.votes = data;
                });
            },
            function(errResponse) {
                console.log(errResponse);
            }
        );

        $scope.eventId = $routeParams.votes;

        $scope.vote = function(obj, fbid) {
            //obj.voted = true;
            $http.post('data/addVote.php', obj).then(
                //$http.post('http://localhost:8080/beathouse/suggest/addVote.php', obj).then(
                function(response) {
                    if (response.data.success) {
                        //Number(obj.userVotes++);
                    } else {
                        console.log(response.data.errors);
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


            //FIXME:  use real FB ID. 
            obj.votingFbid = fbid;

            //Passing in EventId
            obj.eventId = $scope.eventId;
        };

        $scope.played = function(obj, fbid, toggleVal) {
            //obj.voted = true;
            obj.played = toggleVal;
            console.log(obj, fbid);
            $http.post('data/toggleSong.php', obj).then(
                //$http.post('http://localhost:8080/beathouse/suggest/addVote.php', obj).then(
                function(response) {
                    if (response.data.success) {
                        console.log(response.data);
                        sharedService.passData($scope.votes, fbid);
                        //Number(obj.userVotes++);
                    } else {
                        console.log(response.data.errors);
                    }
                },
                function(errResponse) {
                    console.log(errResponse);
                });


            //FIXME:  use real FB ID.
            obj.votingFbid = fbid;
            //Passing in EventId
            obj.eventId = $scope.eventId;

        };

        $scope.resend = function(obj, fbid, toggleVal) {
            //obj.voted = true;
            obj.played = toggleVal;
            console.log(obj, fbid);
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

adminControllers.controller('AdminSongsPlayedCtrl', ['$scope', '$route', '$routeParams', '$http', '$facebook', 'sharedService',
    function($scope, $route, $routeParams, $http, $facebook, sharedService) {

        $scope.$on('dataPassed', function (me, fbid) {
            //$scope.newItems = sharedService.values;
            //console.log('dataPassed',sharedService.values );
            $http.get('data/jsonResults.php?id=' + $routeParams.votes + '&show=played' + '&fbid=' + fbid).success(function(data) {
                $scope.votes = data;
            });
        });

        $scope.user = $facebook.cachedApi('/me').then(function(response) {
                $http.get('data/jsonResults.php?id=' + $routeParams.votes + '&fbid=' + response.id + '&show=played').success(function(data) {
                    //$http.get('http://localhost:8080/beathouse/suggest/jsonResults.php?id=' + $routeParams.votes+'&fbid=5555').success(function(data) {
                    $scope.votes = data;

                });
            },
            function(errResponse) {
                console.log(errResponse);
            }
        );

        $scope.eventId = $routeParams.votes;

        $scope.played = function(obj, fbid) {
            obj.played = 0;
            //obj.voted = true;
            console.log(obj, fbid);
            $http.post('data/toggleSong.php', obj).then(
                function(response) {
                    if (response.data.success) {
                        console.log(response.data);
                        sharedService.passData($scope.votes, fbid);
                    } else {
                        console.log(response.data.errors);
                    }
                },
                function(errResponse) {
                    console.log(errResponse);
                });


            //FIXME:  use real FB ID.
            obj.votingFbid = fbid;
            //Passing in EventId
            obj.eventId = $scope.eventId;
        };
    }
]);

adminControllers.controller('AdminPhotosUploadedCtrl', ['$scope', '$route', '$routeParams', '$http', '$facebook', 'sharedService',
    function($scope, $route, $routeParams, $http, $facebook, sharedService) {

        $scope.$on('dataPassed', function (me, fbid) {
           $http.get('data/photoResults.php?id=' + $routeParams.eventId + '&show=queued').success(function(data) {
                $scope.photos = data;
            });
        });

        $scope.user = $facebook.cachedApi('/me').then(function(response) {
                $http.get('data/photoResults.php?id=' + $routeParams.eventId + '&show=queued').success(function(data) {
                   $scope.photos = data;

                });
            },
            function(errResponse) {
                console.log(errResponse);
            }
        );

        $scope.eventId = $routeParams.eventId;

        $scope.photomark = function(obj, approvedVal) {
	    obj.approved = approvedVal;
            obj.eventId = $scope.eventId;
            obj.eventId = $routeParams.eventId;
            console.log(obj, approvedVal);
            $http.post('data/togglePhoto.php', obj).then(
                function(response) {
                    if (response.data.success) {
                        console.log(response.data);
                        sharedService.passData($scope.photos);
                    } else {
                        console.log(response.data.errors);
                    }
                },
                function(errResponse) {
                    console.log(errResponse);
                });
//            obj.eventId = $scope.eventId;
        };
    }
]);


adminControllers.controller('AdminPhotosApprovedCtrl', ['$scope', '$route', '$routeParams', '$http', '$facebook', 'sharedService',
    function($scope, $route, $routeParams, $http, $facebook, sharedService) {

        $scope.$on('dataPassed', function (me, fbid) {
           $http.get('data/photoResults.php?id=' + $routeParams.eventId + '&show=approved').success(function(data) {
                $scope.photos = data;
            });
        });

        $scope.user = $facebook.cachedApi('/me').then(function(response) {
                $http.get('data/photoResults.php?id=' + $routeParams.eventId + '&show=approved').success(function(data) {
                   $scope.photos = data;

                });
            },
            function(errResponse) {
                console.log(errResponse);
            }
        );

        $scope.eventId = $routeParams.eventId;

        $scope.photomark = function(obj, approvedVal) {
	    obj.approved = approvedVal;
            obj.eventId = $routeParams.eventId;
            console.log(obj, approvedVal);
            $http.post('data/togglePhoto.php', obj).then(
                function(response) {
                    if (response.data.success) {
                        console.log(response.data);
                        sharedService.passData($scope.photos);
                    } else {
                        console.log(response.data.errors);
                    }
                },
                function(errResponse) {
                    console.log(errResponse);
                });
//            obj.eventId = $scope.eventId;
        };
    }
]);


adminControllers.controller('AdminPhotosDeniedCtrl', ['$scope', '$route', '$routeParams', '$http', '$facebook', 'sharedService',
    function($scope, $route, $routeParams, $http, $facebook, sharedService) {

        $scope.$on('dataPassed', function (me, fbid) {
           $http.get('data/photoResults.php?id=' + $routeParams.eventId + '&show=denied').success(function(data) {
                $scope.photos = data;
            });
        });

        $scope.user = $facebook.cachedApi('/me').then(function(response) {
                $http.get('data/photoResults.php?id=' + $routeParams.eventId + '&show=denied').success(function(data) {
                   $scope.photos = data;

                });
            },
            function(errResponse) {
                console.log(errResponse);
            }
        );

        $scope.eventId = $routeParams.eventId;

        $scope.photomark = function(obj, approvedVal) {
	    obj.approved = approvedVal;
            obj.eventId = $routeParams.eventId;
            console.log(obj, approvedVal);
            $http.post('data/togglePhoto.php', obj).then(
                function(response) {
                    if (response.data.success) {
                        console.log(response.data);
                        sharedService.passData($scope.photos);
                    } else {
                        console.log(response.data.errors);
                    }
                },
                function(errResponse) {
                    console.log(errResponse);
                });
//            obj.eventId = $scope.eventId;
        };
    }
]);



adminControllers.controller('AdminSongsRemovedCtrl', ['$scope', '$route', '$routeParams', '$http', '$facebook', 'sharedService',
    function($scope, $route, $routeParams, $http, $facebook, sharedService) {

        $scope.$on('dataPassed', function (me, fbid) {
            //$scope.newItems = sharedService.values;
            //console.log('dataPassed',sharedService.values );
            $http.get('data/jsonResults.php?id=' + $routeParams.votes + '&show=removed' + '&fbid=' + fbid).success(function(data) {
                $scope.votes = data;
            });
        });

        $scope.user = $facebook.cachedApi('/me').then(function(response) {
                $http.get('data/jsonResults.php?id=' + $routeParams.votes + '&fbid=' + response.id + '&show=removed').success(function(data) {
                    //$http.get('http://localhost:8080/beathouse/suggest/jsonResults.php?id=' + $routeParams.votes+'&fbid=5555').success(function(data) {
                    $scope.votes = data;

                });
            },
            function(errResponse) {
                console.log(errResponse);
            }
        );

        $scope.eventId = $routeParams.votes;

        $scope.played = function(obj, fbid) {
            obj.played = 0;
            //obj.voted = true;
            console.log(obj, fbid);
            $http.post('data/toggleSong.php', obj).then(
                function(response) {
                    if (response.data.success) {
                        console.log(response.data);
                        sharedService.passData($scope.votes, fbid);
                    } else {
                        console.log(response.data.errors);
                    }
                },
                function(errResponse) {
                    console.log(errResponse);
                });


            //FIXME:  use real FB ID.
            obj.votingFbid = fbid;
            //Passing in EventId
            obj.eventId = $scope.eventId;
        };
    }
]);

adminControllers.controller('AdminGenericCtrl', ['$scope', function($scope) {
    $scope.date = new Date();
}]);

adminControllers.controller('AdminCtrl', AdminCtrl);

function AdminCtrl($scope, $timeout, $q, $log, $http, $location) {
    var self = this;
    self.manageEvents = manageEvents;
    self.adminHome = adminHome;
    self.createEvent = createEvent;
    self.viewEvent = viewEvent;
    self.editEvent = editEvent;
//    self.createEvent = createEvent;
    self.getTitle = getTitle;
    self.viewEventPhotos = viewEventPhotos;

    function editEvent(obj) {
        $scope.eventDate = obj.date;
        $scope.eventTitle = obj.title;
        $location.path('/admin/editevent/'+obj.id);
    }

    function getTitle(){
        console.log($scope);
        return $scope.eventTitle;
    }

    $scope.openMenu = function($mdOpenMenu, ev){
        console.log('menu Opened');
        var originatorEv;
        originatorEv = ev;
        $mdOpenMenu(ev);
    };

    function manageEvents() {
        console.log('manageing Events');
            $location.path('/admin/events');
    }
    function adminHome() {
        console.log('Admin Home');
            $location.path('/admin');
    }
    function createEvent() {
        console.log('create Event');
	    $location.path('/admin/create');
    }

    function viewEvent(id, obj) {
        obj.played = 0;
        //obj.voted = true;
        console.log('view Event', id, obj);
        $location.path('/admin/'+id);
    }

    function viewEventPhotos(id, obj) {
        console.log('view Event Photos', id, obj);
        $location.path('/admin/photos/'+id);
    }

    $http.get('data/admin/events.php').then(function(response) {

        if (response.data) {
            console.log(response.data);
            $scope.events = response.data;

            //Number(obj.userVotes++);
        } else {
            console.log(response.data.errors);
        }
    });
}

adminControllers.controller('CreateEventCtrl', CreateEventCtrl);

function CreateEventCtrl($scope, $timeout, $q, $log, $http, $location) {
    var self = this;
    //self.editEvent = editEvent;
    self.openMenu = openMenu;
    self.cancel = cancel;
    self.createEvent = createEvent;

    function cancel(){
        $location.path('/admin/events');
    }

    function createEvent(){
        console.log('creating event');
        var obj = {};
        obj.action = 'create_event';
        obj.title = $scope.eventTitle;
        obj.date = $scope.eventDate;
        $http.post('data/admin/index.php', obj).then(
            //$http.post('http://localhost:8080/beathouse/suggest/addVote.php', obj).then(
            function(response) {
                if (response.data.success) {
                    $location.path('/admin/'+response.data.data.eventId);
                    //Number(obj.userVotes++);
                } else {
                    console.log(response.data.errors);
                }
            },
            function(errResponse) {
                console.log(errResponse);
            });
    }

    function openMenu($mdOpenMenu, ev){
        console.log('menu Opened');
        var originatorEv;
        originatorEv = ev;
        $mdOpenMenu(ev);
    }
}
