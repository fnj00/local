'use strict';

/* Controllers */
var suggestControllers = angular.module('suggestControllers', ['ngMaterial'])

.factory('sharedService', function($rootScope) {
  var mySharedService = {};
  mySharedService.values = {};
  mySharedService.passData = function(newData) {
    mySharedService.values = newData || {};
    $rootScope.$broadcast('dataPassed');
  };
  return mySharedService;
})

.factory('$localstorage', ['$window', function($window) {
  return {
    set: function(key, value) {
      $window.localStorage[key] = value;
    },
    get: function(key, defaultValue) {
      return $window.localStorage[key] || defaultValue;
    },
    setObject: function(key, value) {
      $window.localStorage[key] = JSON.stringify(value);
    },
    getObject: function(key) {
      var value = $window.localStorage[key];
      return value ? JSON.parse(value) : {};
    },
    remove: function(key) {
      $window.localStorage.removeItem(key);
    }
  };
}])

.directive('fileChange', [function() {
  return {
    restrict: 'A',
    scope: {
      fileChange: '&'
    },
    link: function(scope, element) {
      element.on('change', function(event) {
        scope.$apply(function() {
          scope.fileChange({ $event: event });
        });
      });
    }
  };
}]);

suggestControllers.controller('SearchCtrl', SearchCtrl);
function SearchCtrl($scope, $http, $location, $localstorage) {
  $scope.nextEvent = null;
  $scope.eventDate = '';
  $scope.eventPhoto = '';
  $scope.text = '';

  $scope.displayData = function() {
    $http.get('data/nextEvent.php').then(function(response) {
      if (response.data && !response.data.error) {
        $scope.nextEvent = response.data;
        $scope.eventDate = response.data.date;
        $scope.eventPhoto = response.data.photo || '';
        if (response.data.id) {
          $localstorage.set('eventId', response.data.id);
        }
      }
    }, function() {
      console.log('Unable to load next event.');
    });
  };

  $scope.displayData();

  $scope.submit = function() {
    if ($scope.text) {
      $location.path('/event/' + $scope.text);
    }
  };

  $scope.openMenu = function($mdOpenMenu, ev) {
    $mdOpenMenu(ev);
  };
}

suggestControllers.controller('EventSearchCtrl', EventSearchCtrl);
function EventSearchCtrl($scope, $http, $location, $routeParams, $localstorage) {
  var monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ];

  function nth(day) {
    if (day > 3 && day < 21) return 'th';
    switch (day % 10) {
      case 1: return 'st';
      case 2: return 'nd';
      case 3: return 'rd';
      default: return 'th';
    }
  }

  function formatDate(input) {
    if (!input) return '';
    var d = new Date(input);
    if (isNaN(d.getTime())) return input;
    return monthNames[d.getMonth()] + ' ' + d.getDate() + nth(d.getDate()) + ' ' + d.getFullYear();
  }

  function handleEventResponse(response) {
    if (response.data && !response.data.error) {
      $scope.eventDetails = response.data;
      $scope.eventDateFormat = formatDate(response.data.date);
      $scope.eventPhoto = response.data.photo || '';
      if (response.data.id) {
        $localstorage.set('eventId', response.data.id);
      }
    } else {
      $location.path('/event');
    }
  }

  $scope.eventDetails = null;
  $scope.eventDateFormat = '';
  $scope.eventPhoto = '';
  $scope.text = '';

  if (typeof $routeParams.eventId === 'undefined') {
    $http.get('data/nextEvent.php').then(handleEventResponse, function() {
      $location.path('/event');
    });
  } else {
    $localstorage.set('eventId', $routeParams.eventId);
    $http.get('data/eventDetails.php?id=' + encodeURIComponent($routeParams.eventId)).then(handleEventResponse, function() {
      $location.path('/event');
    });
  }

  $scope.submit = function() {
    if ($scope.text) {
      $location.path('/event/' + $scope.text);
    }
  };

  $scope.openMenu = function($mdOpenMenu, ev) {
    $mdOpenMenu(ev);
  };
}

suggestControllers.controller('EventCtrl', EventCtrl);
function EventCtrl($scope, $http, $location, $routeParams) {
  $scope.eventId = $routeParams.votes;
  $scope.eventDetails = null;
  $scope.eventDate = '';

  $scope.displayData = function() {
    $http.get('data/eventDetails.php?id=' + encodeURIComponent($routeParams.votes)).then(function(response) {
      if (response.data && !response.data.error) {
        $scope.eventDetails = response.data;
        $scope.eventDate = response.data.date;
      } else {
        $location.path('/event');
      }
    }, function() {
      $location.path('/event');
    });
  };

  $scope.displayData();

  $scope.suggest = function() {
    if ($scope.eventId) {
      $location.path('/suggest/' + $scope.eventId);
    }
  };

  $scope.photo = function() {
    if ($scope.eventId) {
      $location.path('/photo/' + $scope.eventId);
    }
  };

  $scope.submit = function() {
    if ($scope.text) {
      $location.path('/voting/' + $scope.text);
    }
  };

  $scope.openMenu = function($mdOpenMenu, ev) {
    $mdOpenMenu(ev);
  };
}

suggestControllers.controller('SongsQueuedCtrl', ['$scope', '$location', '$routeParams', '$http', 'sharedService',
  function($scope, $location, $routeParams, $http, sharedService) {
    $scope.votes = [];
    $scope.eventId = $routeParams.votes;

    $http.get('data/jsonResults.php?id=' + encodeURIComponent($routeParams.votes)).then(function(response) {
      $scope.votes = response.data || [];
    });

    $scope.vote = function(obj) {
      obj.eventId = $scope.eventId;
      $http.post('data/addVote.php', obj).then(function(response) {
        if (response.data && response.data.success) {
          if (!obj.voted) {
            obj.userVotes = Number(obj.userVotes || 0) + 1;
          }
          obj.voted = true;
        } else if (response.data && response.data.errors) {
          Swal.fire({
            icon: 'info',
            title: 'Woops!',
            text: response.data.errors
          });
        }
      }, function(errResponse) {
        console.log(errResponse);
      });
    };

    $scope.played = function(obj, toggleVal) {
      obj.played = toggleVal;
      obj.eventId = $scope.eventId;

      $http.post('data/toggleSong.php', obj).then(function(response) {
        if (response.data && response.data.success) {
          sharedService.passData($scope.votes);
        } else {
          console.log(response.data ? response.data.errors : 'Unknown error');
        }
      }, function(errResponse) {
        console.log(errResponse);
      });
    };

    $scope.resend = function(obj) {
      if (obj.songArtist && obj.songTitle) {
        $http({
          method: 'POST',
          url: 'https://virtualdj.com/ask/fnj00',
          data: 'message=' + encodeURIComponent(obj.songTitle + ' ' + obj.songArtist) +
                '&name=' + encodeURIComponent(obj.requestedBy || ''),
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          }
        });
      }
    };

    $scope.submit = function() {
      if ($scope.text) {
        $location.path('/voting/' + $scope.text);
      }
    };
  }
]);

suggestControllers.controller('SongsPlayedCtrl', ['$scope', '$routeParams', '$http', 'sharedService',
  function($scope, $routeParams, $http, sharedService) {
    $scope.votes = [];
    $scope.eventId = $routeParams.votes;

    $http.get('data/jsonResults.php?id=' + encodeURIComponent($routeParams.votes) + '&show=played').then(function(response) {
      $scope.votes = response.data || [];
    });

    $scope.played = function(obj) {
      obj.played = 0;
      obj.eventId = $scope.eventId;

      $http.post('data/toggleSong.php', obj).then(function(response) {
        if (response.data && response.data.success) {
          sharedService.passData($scope.votes);
        } else {
          console.log(response.data ? response.data.errors : 'Unknown error');
        }
      }, function(errResponse) {
        console.log(errResponse);
      });
    };
  }
]);

suggestControllers.controller('SongsRemovedCtrl', ['$scope', '$routeParams', '$http', 'sharedService',
  function($scope, $routeParams, $http, sharedService) {
    $scope.votes = [];
    $scope.eventId = $routeParams.votes;

    $http.get('data/jsonResults.php?id=' + encodeURIComponent($routeParams.votes) + '&show=removed').then(function(response) {
      $scope.votes = response.data || [];
    });

    $scope.played = function(obj) {
      obj.played = 0;
      obj.eventId = $scope.eventId;

      $http.post('data/toggleSong.php', obj).then(function(response) {
        if (response.data && response.data.success) {
          sharedService.passData($scope.votes);
        } else {
          console.log(response.data ? response.data.errors : 'Unknown error');
        }
      }, function(errResponse) {
        console.log(errResponse);
      });
    };
  }
]);

function loadEventForPublicPage($scope, $http, $location, eventId) {
  $scope.eventId = eventId || '';
  $scope.eventDetails = null;
  $scope.eventTitle = '';
  $scope.eventPhoto = '';

  if (!$scope.eventId) {
    $location.path('/event');
    return;
  }

  $http.get('data/eventDetails.php?id=' + encodeURIComponent($scope.eventId)).then(function(response) {
    if (response.data && !response.data.error) {
      $scope.eventDetails = response.data;
      $scope.eventTitle = unescape(response.data.title || '');
      $scope.eventPhoto = response.data.photo || '';
    } else {
      $location.path('/event');
    }
  }, function() {
    $location.path('/event');
  });
}

suggestControllers.controller('SongSuggestCtrl', ['$scope', '$http', '$location', '$routeParams',
  function($scope, $http, $location, $routeParams) {
    loadEventForPublicPage($scope, $http, $location, $routeParams.eventId);

    $scope.formData = {
      song: '',
      requesterName: ''
    };
    $scope.isSubmitting = false;
    $scope.submitSuccess = false;
    $scope.submitError = '';
    $scope.responseMessage = '';

    $scope.openMenu = function($mdOpenMenu, ev) {
      $mdOpenMenu(ev);
    };

    $scope.submitSong = function() {
      var song = ($scope.formData.song || '').trim();
      var requesterName = ($scope.formData.requesterName || '').trim();

      $scope.submitError = '';
      $scope.responseMessage = '';
      $scope.submitSuccess = false;

      if (!song) {
        $scope.submitError = 'Please enter a song title or artist.';
        return;
      }

      $scope.isSubmitting = true;

      $http.post('data/addSong.php', {
        song: song,
        eventId: $scope.eventId,
        requestedBy: requesterName,
        name: requesterName
      }).then(function(response) {
        $scope.isSubmitting = false;

        if (response.data && response.data.success) {
          $scope.submitSuccess = true;
          $scope.responseMessage = response.data.message || 'Song request sent successfully.';
          $scope.formData.song = '';
          $scope.formData.requesterName = '';
        } else {
          $scope.submitError = (response.data && (response.data.message || response.data.errors)) || 'Unable to send your request.';
        }
      }, function() {
        $scope.isSubmitting = false;
        $scope.submitError = 'Unable to send your request right now. Please try again.';
      });
    };
  }
]);

suggestControllers.controller('AnonSongSuggestCtrl', ['$scope', '$http', '$location', '$routeParams',
  function($scope, $http, $location, $routeParams) {
    loadEventForPublicPage($scope, $http, $location, $routeParams.eventId);

    $scope.formData = {
      song: '',
      requesterName: ''
    };
    $scope.isSubmitting = false;
    $scope.submitSuccess = false;
    $scope.submitError = '';
    $scope.responseMessage = '';

    $scope.submitSong = function() {
      var song = ($scope.formData.song || '').trim();
      var requesterName = ($scope.formData.requesterName || '').trim();

      $scope.submitError = '';
      $scope.responseMessage = '';
      $scope.submitSuccess = false;

      if (!song) {
        $scope.submitError = 'Please enter a song title or artist.';
        return;
      }

      $scope.isSubmitting = true;

      $http.post('data/addSong.php', {
        song: song,
        eventId: $scope.eventId,
        requestedBy: requesterName,
        name: requesterName
      }).then(function(response) {
        $scope.isSubmitting = false;

        if (response.data && response.data.success) {
          $scope.submitSuccess = true;
          $scope.responseMessage = response.data.message || 'Song request sent successfully.';
          $scope.formData.song = '';
          $scope.formData.requesterName = '';
        } else {
          $scope.submitError = (response.data && (response.data.message || response.data.errors)) || 'Unable to send your request.';
        }
      }, function() {
        $scope.isSubmitting = false;
        $scope.submitError = 'Unable to send your request right now. Please try again.';
      });
    };
  }
]);

suggestControllers.controller('PhotoCtrl', ['$scope', '$http', '$location', '$routeParams', '$timeout',
  function($scope, $http, $location, $routeParams, $timeout) {
    loadEventForPublicPage($scope, $http, $location, $routeParams.eventId);

    $scope.uploadNotice = '';
    $scope.uploadNoticeType = '';
    $scope.publicPhotos = [];
    $scope.selectedFile = null;
    $scope.selectedFileName = '';
    $scope.rawPreviewImage = '';
    $scope.croppedPreviewImage = '';
    $scope.isUploading = false;
    $scope.cropper = null;
    $scope.cropperReady = false;
    $scope.cropAspectRatio = 1;
    $scope.cropRatioLabel = '1:1';
    $scope.eventCropMetaLoaded = false;

    $scope.openMenu = function($mdOpenMenu, ev) {
      $mdOpenMenu(ev);
    };

    function destroyCropper() {
      if ($scope.cropper) {
        $scope.cropper.destroy();
        $scope.cropper = null;
      }
      $scope.cropperReady = false;
    }

    function normalizeNumber(value, fallback) {
      var n = parseFloat(value);
      return (!isNaN(n) && isFinite(n) && n > 0) ? n : fallback;
    }

    function loadEventCropMeta(callback) {
      if (!$scope.eventId) {
        $scope.cropAspectRatio = 1;
        $scope.cropRatioLabel = '1:1 fallback';
        if (callback) { callback(); }
        return;
      }

      $http.get('data/eventDetails.php?id=' + encodeURIComponent($scope.eventId)).then(function(response) {
        var obj = response.data || {};

        var collageX = normalizeNumber(obj.collageX || obj.collagex, 1);
        var collageY = normalizeNumber(obj.collageY || obj.collagey, 1);
        var imgWidth = normalizeNumber(obj.imgWidth || obj.imgwidth, 0);
        var imgHeight = normalizeNumber(obj.imgHeight || obj.imgheight, 0);
        var gap = 2; // same value used in collagenew/js/collage.js

        var cAspectRatio;

        if (imgWidth > 0 && imgHeight > 0) {
          cAspectRatio = ( ((imgWidth / collageX) - gap) / ((imgHeight / collageY) - gap) );
          $scope.cropRatioLabel =
            Math.round(imgWidth / collageX) + ' × ' + Math.round(imgHeight / collageY) + ' tile shape';
        } else {
          cAspectRatio = collageY / collageX;
          $scope.cropRatioLabel = Math.round(collageX) + ':' + Math.round(collageY) + ' fallback';
        }

        if (!cAspectRatio || !isFinite(cAspectRatio) || cAspectRatio <= 0) {
          cAspectRatio = 1;
          $scope.cropRatioLabel = '1:1 fallback';
        }

        $scope.cropAspectRatio = cAspectRatio;
        $scope.eventCropMetaLoaded = true;
        $scope.eventCropMeta = {
          collageX: collageX,
          collageY: collageY,
          imgWidth: imgWidth,
          imgHeight: imgHeight,
          cAspectRatio: cAspectRatio
        };

        if ($scope.cropper) {
          $scope.cropper.setAspectRatio(cAspectRatio);
        }

        if (callback) {
          callback();
        }
      }, function() {
        $scope.cropAspectRatio = 1;
        $scope.cropRatioLabel = '1:1 fallback';
        $scope.eventCropMetaLoaded = false;

        if ($scope.cropper) {
          $scope.cropper.setAspectRatio(1);
        }

        if (callback) {
          callback();
        }
      });
    }

    function initCropper() {
      destroyCropper();

      $timeout(function() {
        var image = document.getElementById('cropperTarget');
        if (!image || !image.src) {
          return;
        }

        $scope.cropper = new Cropper(image, {
          aspectRatio: $scope.cropAspectRatio || 1,
          viewMode: 1,
          dragMode: 'move',
          autoCropArea: 1,
          responsive: true,
          background: false,
          guides: true,
          movable: true,
          zoomable: true,
          rotatable: false,
          scalable: false,
          cropBoxMovable: true,
          cropBoxResizable: true,
          ready: function() {
            $scope.$applyAsync(function() {
              $scope.cropperReady = true;
            });
            $scope.refreshCropPreview();
          },
          cropend: function() {
            $scope.refreshCropPreview();
          },
          zoom: function() {
            $scope.refreshCropPreview();
          }
        });
      }, 50);
    }

    $scope.refreshPhotos = function() {
      if (!$scope.eventId) {
        return;
      }

      $http.get('collagePhotos.php?id=' + encodeURIComponent($scope.eventId)).then(function(response) {
        if (angular.isArray(response.data)) {
          $scope.publicPhotos = response.data.slice(0, 24).map(function(photo) {
            return {
              id: photo.id || null,
              thumbUrl: photo.thumbUrl || photo.thumb || photo.image || '',
              fullUrl: photo.fullUrl || photo.url || photo.photo || photo.image || '',
              image: photo.thumbUrl || photo.thumb || photo.image || ''
            };
          });
        } else {
          $scope.publicPhotos = [];
        }
      }, function() {
        $scope.publicPhotos = [];
      });
    };

    $scope.setUploadNotice = function(type, message) {
      $scope.uploadNoticeType = type || '';
      $scope.uploadNotice = message || '';
    };

    $scope.clearSelectedPhoto = function() {
      destroyCropper();
      $scope.selectedFile = null;
      $scope.selectedFileName = '';
      $scope.rawPreviewImage = '';
      $scope.croppedPreviewImage = '';
      var input = document.getElementById('directPhotoUpload');
      if (input) {
        input.value = '';
      }
      $scope.setUploadNotice('', '');
    };

    $scope.onPhotoSelected = function($event) {
      var file = $event && $event.target && $event.target.files ? $event.target.files[0] : null;

      $scope.setUploadNotice('', '');

      if (!file) {
        $scope.clearSelectedPhoto();
        return;
      }

      if (!/^image\//i.test(file.type)) {
        $scope.clearSelectedPhoto();
        $scope.setUploadNotice('error', 'Please choose an image file.');
        return;
      }

      $scope.selectedFile = file;
      $scope.selectedFileName = file.name || 'Selected image';

      var reader = new FileReader();
      reader.onload = function(e) {
        $scope.$applyAsync(function() {
          $scope.rawPreviewImage = e.target.result;
          $scope.croppedPreviewImage = '';

          loadEventCropMeta(function() {
            initCropper();
          });
        });
      };
      reader.onerror = function() {
        $scope.$applyAsync(function() {
          $scope.setUploadNotice('error', 'Unable to read the selected image.');
        });
      };
      reader.readAsDataURL(file);
    };

    $scope.refreshCropPreview = function() {
      if (!$scope.cropper) {
        return;
      }

      var canvas = $scope.cropper.getCroppedCanvas({
        maxWidth: 1200,
        maxHeight: 1200,
        fillColor: '#ffffff',
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high'
      });

      if (!canvas) {
        return;
      }

      $scope.croppedPreviewImage = canvas.toDataURL('image/jpeg', 0.9);
    };

    $scope.uploadSelectedPhoto = function() {
      if (!$scope.cropper) {
        $scope.setUploadNotice('error', 'Please choose and crop a photo first.');
        return;
      }

      var canvas = $scope.cropper.getCroppedCanvas({
        maxWidth: 1600,
        maxHeight: 1600,
        fillColor: '#ffffff',
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high'
      });

      if (!canvas) {
        $scope.setUploadNotice('error', 'Unable to prepare the cropped image.');
        return;
      }

      var imageData = canvas.toDataURL('image/jpeg', 0.9);

      $scope.isUploading = true;
      $scope.setUploadNotice('', '');

      $http.post('data/uploadPhonePhoto.php', {
        eventId: $scope.eventId,
        image: imageData
      }).then(function(response) {
        $scope.isUploading = false;

        if (response.data && response.data.success) {
          $scope.setUploadNotice('success', response.data.message || 'Photo uploaded successfully.');
          $scope.clearSelectedPhoto();
          $scope.refreshPhotos();
        } else {
          $scope.setUploadNotice('error', (response.data && response.data.message) || 'Photo upload failed.');
        }
      }, function() {
        $scope.isUploading = false;
        $scope.setUploadNotice('error', 'Photo upload failed. Please try again.');
      });
    };

    var stopWatch = $scope.$watch('eventId', function(newVal) {
      if (newVal) {
        loadEventCropMeta();
      }
    });

    $scope.$on('$destroy', function() {
      if (stopWatch) {
        stopWatch();
      }
      destroyCropper();
    });

    $scope.refreshPhotos();
  }
]);

suggestControllers.controller('genericCtrl', ['$scope', function($scope) {
  $scope.date = new Date();
}]);
