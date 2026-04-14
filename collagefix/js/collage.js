;(function( $, window ) {

var origUrl = new URL(window.location.href);
var params = origUrl.searchParams;

if(typeof params.get("id") !== "undefined"){
  if(params.get("id") !== null){
    id = params.get("id");
  } else {
    id = "0";
  }
} else {
  id = "0";
}

  fetch(`/data/boothnext.php?eventId=`+ id).then(function(response) {
    console.log(typeof(response));
    return response.text();
  }).then(function(data) {
    var obj = JSON.parse(data);
    console.log('data ', obj);
    let [year, month, day] = obj.date.split('-').map(v=>parseInt(v));
    var eventName = obj.title;
    collageImg = "../photos/" + obj.collageImg;
    collageX = obj.collageX;
    collageY = obj.collageY;
    numCollage = obj.numCollage;
    imgWidth = obj.imgWidth;
    imgHeight = obj.imgHeight;
    imgAspect = imgWidth / imgHeight;
    let eventId = obj.id;
    var monthname = "January,February,March,April,May,June,July,August,September,October,November,December"
    .split(",")[month-1];
    function nth(d) {
      if(d>3 && d<21) return 'th';
      switch (d % 10) {
        case 1: return "st";
        case 2: return "nd";
        case 3: return "rd";
        default: return "th";
      }
    }
    let formatDate = `${day}${nth(day)} ${monthname} ${year}`;
    document.getElementById("eventName").innerHTML = eventName + " - " + formatDate;
    let headerHeight = document.querySelector('#eventName').offsetHeight;
//    document.getElementById("eventDate").innerHTML = formatDate;
    var mainImg = document.getElementById("mainimg");
    mainImg.src = collageImg;
//    mainImg.style = 'width: 1080px'
    var collage = document.getElementById("collage");
//    collage.style.width = imgWidth + "px";
//    collage.style.height = imgHeight + "px";
    var divTop = document.getElementById("top");
//    divTop.style.width = imgWidth + "px";
    if(imgAspect < 1){
      collageWidth = window.innerWidth - 5;
      collageHeight = collageWidth / imgAspect;
      collage.style.width = collageWidth + "px";
      collage.style.height = collageHeight + "px";
      newImgWidth = collage.style.width;
      newImgHeight = collage.style.height;
//      collage.style.width = window.innerWidth + "px";
//     collage.style.height = ( window.innerWidth / imgAspect ) + "px";
//      document.body.setAttribute( "style", "-webkit-transform: rotate(-90deg);");
    } else if(imgAspect > 1){
//      collage.style.width = window.innerWidth -5 + "px";
      collage.style.height = ( ( window.innerHeight - headerHeight ) + "px" );
      collage.style.width = ( ( ( window.innerHeight - headerHeight ) * imgAspect ) + "px" );
//      collage.style.height = ( ( ( window.innerWidth - 5 ) / imgAspect ) - 250 ) + "px";
//      collage.style.width = ( ( ( window.innerWidth - 5 ) / imgAspect ) - 250 ) * imgAspect + "px";
      newImgWidth = collage.style.width;
      newImgHeight = collage.style.height;
    }
    window.eventId = obj.id;
  })

//  var _defaults = {
//    x : collageX, // tiles in x axis
//    y : collageY, // tiles in y axis
//    gap: 2
//  };

  opacity = '0.40';


  jQuery.fn.extend({

    // get style attributes that were actually set on the first element of the jQuery object
    getStyle: function (prop) {
        var elem = this[0];
        var actuallySetStyles = {};
        for (var i = 0; i < elem.style.length; i++) {
            var key = elem.style[i];
            if (prop == key)
                return elem.style[key];
            actuallySetStyles[key] = elem.style[key];
        }
        if (!prop)
            return actuallySetStyles;
    },

    quickfitText: function (options) {
        options = options || {};
        return this.each(function () {
            var $elem = jQuery(this);
            var elem = $elem[0];
            var maxHeight = options.maxHeight || parseInt($elem.attr('maxheight')) || parseInt($elem.css('min-height')) || 50;
            var maxFontSize = options.minFontSize || parseInt($elem.attr('maxfont')) || 300;
            var minFontSize = options.maxFontSize || parseInt($elem.attr('minfont')) || 7;

            //The magic happens here
            var fontSize = maxFontSize;
            // backup original style
            var style = $elem.getStyle();
            style['line-height'] = elem.style.lineHeight = 'normal'; // intentionally set this
            elem.style.transition = 'none';
            elem.style.display = 'inline';
            elem.style.minHeight = '0';
            // Gradually decrease font size until the element height is ok
            elem.style.fontSize = '' + fontSize + "px";
            while (elem.getBoundingClientRect().height > maxHeight && fontSize > minFontSize) {
                fontSize--;
                elem.style.fontSize = '' + fontSize + "px";
            }
            elem.style.transition = style['transition'] || '';
            elem.style.display = style['display'] || '';
            elem.style.minHeight = style['min-height'] || '';
        });
    }
  });

  jQuery(function () {
        jQuery('.quickfit').quickfitText();
  });

  $.fn.splitInTiles = function( photos ) {

    return this.each(function() {

      var $container = $(this),
          width = $container.width(),
          height = $container.height(),
//            width = newImgWidth,
//          height = newImgHeight,
            $img = $container.find('#mainimg'),
//          n_tiles = o.x * o.y,
            n_tiles = collageX * collageY,
            wraps = [], $wraps,
            gap = '2';

      if ($('.tile').length == 0){

        for ( var i = 0; i < n_tiles; i++ ) {
          if (!(i in wraps)){
            wraps.push('<div class="tile" id="' + i + '"/>');
          }
        }

        $wraps = $( wraps.join('') );


        // Hide original image and insert tiles in DOM
        $img.hide().after( $wraps );

        // Set background
        $wraps.css({
//          width: (width / o.x) - o.gap,
//          height: (height / o.y) - o.gap,
//          marginBottom: o.gap +'px',
//          marginRight: o.gap +'px',
            width: (width / collageX) - gap + "px",
            height: (height / collageY) - gap + "px",
            marginBottom: gap +'px',
            marginRight: gap +'px',
            backgroundImage: 'url('+ $img.attr('src') +')',
            backgroundSize: newImgWidth + ' ' + newImgHeight
        });

        // Adjust position
        $wraps.each(function() {
          var pos = $(this).position(),
              tileId = ++$(this).context.id,
              tileW = $(this).context.clientWidth,
              tileH = $(this).context.clientHeight;

          $(this).css({
            backgroundPosition: -pos.left +'px '+ -pos.top +'px',
          });
          //$(this).html('<img style="opacity: .7" src="https://via.placeholder.com/' + tileW + 'x' + tileH + '"></img>');
          if( photos[tileId] === undefined ) {
            $(this).html('<img src="testimage/black.jpg" style="width: 100%; height: 100%; object-fit: cover; opacity: 1"></img><p id="text">IMG ' + tileId + '</p>');
          } else {
            $(this).html('<img class="pop" onclick="popImage()" onerror="this.onerror=null; this.style.opacity=1; this.src=\'testimage/black.jpg\'" style="width: 100%; height: 100%; object-fit: cover; opacity: ' + opacity + '" src="' + photos[tileId] + '"></img>');
          }
          //debugger;
        });
      } else {

        $('.tile').each(function() {
          console.log($(this));
          var tileId = $(this).context.id;

          if(!$(this).context.innerHTML.includes(onerror)){
            if( tileId in photos ) {
              $(this).html('<img onerror="this.onerror=null; this.style.opacity=1; this.src=\'testimage/black.jpg\'" style="width: 100%; height: 100%; object-fit: cover; opacity: ' + opacity + '" src="' + photos[tileId] + '"></img>');
            }
          }
        });

      }
      console.log($wraps);
    });

  };

}( jQuery, window ));

const dofetch = () => {
  fetch(`/data/collagePhotos.php?id=${window.eventId}`).then(res => res.json()).then((list) => {
    $('#collage').splitInTiles(list);
  });
};
setInterval(dofetch, 6e4);           //Once a minute
function waitForIt(){
  if(typeof eventId !== "undefined"){
    dofetch();
  }
  else{
    setTimeout(waitForIt, 250);
  }
}
waitForIt();
