'use strict';

/* App Module */
var suggestApp = angular.module('suggestApp', [
    'ngFacebook',
    'ngRoute',
    'ngMaterial',
    'suggestControllers',
	'adminControllers',
	'ngSanitize'
]);

suggestApp.config(['$routeProvider',
    function($routeProvider) {
        $routeProvider.
        when('/voting/:votes', {
            templateUrl: 'partials/votes.html',
            controller: 'EventCtrl'
        }).
	when('/event', {
	    templateUrl: 'partials/event.html',
            cache: false,
	    controller: 'EventSearchCtrl'
	}).
	when('/event/:eventId', {
            templateUrl: 'partials/event.html',
            cache: false,
            controller: 'EventSearchCtrl'
        }).
        when('/suggest', {
            templateUrl: 'partials/suggest.html',
            controller: 'SongSuggestCtrl'
        }).
        when('/suggest/:eventId', {
            templateUrl: 'partials/suggest.html',
            controller: 'SongSuggestCtrl'
        }).
        when('/anonymous/:eventId', {
            templateUrl: 'partials/anonymous.html',
            controller: 'AnonSongSuggestCtrl'
        }).
        when('/photo', {
            templateUrl: 'partials/photo.html',
            controller: 'PhotoCtrl'
        }).
        when('/photo/:eventId', {
            templateUrl: 'partials/photo.html',
            controller: 'PhotoCtrl'
        }).
        when('/admin', {
            templateUrl: 'partials/admin/admin.html',
            controller: 'AdminCtrl'
        }).
        when('/admin/events', {
            templateUrl: 'partials/admin/events.html',
            controller: 'AdminCtrl'
        }).
        when('/admin/editevent/:votes', {
            templateUrl: 'partials/admin/editevent.html',
            controller: 'AdminCtrl'
        }).
        when('/admin/create', {
            templateUrl: 'partials/admin/createevent.html',
            controller: 'CreateEventCtrl'
        }).
        when('/admin/:votes', {
            templateUrl: 'partials/admin/event.html',
            controller: 'EventCtrl'
        }).
        when('/admin/photos/', {
            templateUrl: 'partials/admin/photos.html',
            controller: 'AdminPhotoCtrl'
        }).
        when('/admin/photos/:eventId', {
            templateUrl: 'partials/admin/photos.html',
            controller: 'AdminPhotoCtrl'
        }).
        otherwise({
            redirectTo: '/event'
        });
    }
]);

suggestApp.config(function($facebookProvider) {
    $facebookProvider.setAppId('1519867624979211');
    $facebookProvider.setVersion("v7.0");
    $facebookProvider.setCustomInit({
        xfbml      : true,
        status      : true,
        cookie      : true
    });
});

suggestApp.config(function ($mdThemingProvider, $mdIconProvider) {
    $mdThemingProvider.theme('forest')
      .primaryPalette('brown')
      .accentPalette('green');
    $mdIconProvider
      .defaultIconSet('img/icons/sets/social-icons.svg', 24);
  });

suggestApp.run(['$rootScope', function($rootScope) {

    (function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s);
        js.id = id;
        js.src = "//connect.facebook.net/en_US/sdk.js#cookie=true&xfbml=true&version=v2.5&appId=1519867624979211&status=true";
        fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));
}]);

suggestApp.directive('szUploader', () => ({
	transclude: true,
	replace: true,
	scope: {
		szFile: '&'
	},
	template: `
<label layout="row" class="md-button" md-ink-ripple>
	<input type="file" multiple style="display:none" />
	<span ng-transclude></span>
</label>`,
	link: (scope, el) => {
		const input = el.find('input')[0];

		input.addEventListener('change', (ev) => {
			[].forEach.call(ev.target.files, file => {
				scope.szFile({$item: file});
			});
		});
	}
}));
