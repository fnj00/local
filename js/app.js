'use strict';

var suggestApp = angular.module('suggestApp', [
  'ngRoute',
  'ngMaterial',
  'suggestControllers',
  'ngSanitize'
]);

suggestApp.config([
  '$routeProvider',
  function ($routeProvider) {
    $routeProvider
      .when('/voting/:votes', {
        templateUrl: 'partials/votes.html',
        controller: 'EventCtrl'
      })
      .when('/event', {
        templateUrl: 'partials/event.html',
        cache: false,
        controller: 'EventSearchCtrl'
      })
      .when('/event/:eventId', {
        templateUrl: 'partials/event.html',
        cache: false,
        controller: 'EventSearchCtrl'
      })
      .when('/suggest', {
        templateUrl: 'partials/suggest.html',
        controller: 'SongSuggestCtrl'
      })
      .when('/suggest/:eventId', {
        templateUrl: 'partials/suggest.html',
        controller: 'SongSuggestCtrl'
      })
      .when('/anonymous/:eventId', {
        templateUrl: 'partials/anonymous.html',
        controller: 'AnonSongSuggestCtrl'
      })
      .when('/photo', {
        templateUrl: 'partials/photo.html',
        controller: 'PhotoCtrl'
      })
      .when('/photo/:eventId', {
        templateUrl: 'partials/photo.html',
        controller: 'PhotoCtrl'
      })
      .otherwise({
        redirectTo: '/event'
      });
  }
]);
