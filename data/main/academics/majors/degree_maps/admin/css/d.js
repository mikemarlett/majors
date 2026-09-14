var DO_NOT_CENSOR = false;
var PAGE_HREF = location.href;

$(function() {
    if ($('#content1').length) {
        var tmp = $('#content1').html();
        tmp = tmp.replace(/(west|south) campus/ig, 'WSU $1');
        tmp = tmp.replace(/(wichita state university|wichita state|wsu) (wsu south|wsu west)/ig, '$2');

        $('#content1').html(tmp);
    }

    if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
        var countrycodes = "1"
        var delimiters = "-|\\.|—|–|&nbsp;"
        var phonedef = "\\+?(?:(?:(?:" + countrycodes + ")(?:\\s|" + delimiters + ")?)?\\(?[2-9]\\d{2}\\)?(?:\\s|" + delimiters + ")?[2-9]\\d{2}(?:" + delimiters + ")?[0-9a-z]{4})"
        var spechars = new RegExp("([- \(\)\.:]|\\s|" + delimiters + ")","gi") //Special characters to be removed from the link
        var phonereg = new RegExp("((^|[^0-9])(href=[\"']tel:)?((?:" + phonedef + ")[\"'][^>]*?>)?(" + phonedef + ")($|[^0-9]))","gi")

        function ReplacePhoneNumbers(oldhtml) {
        //Created by Jon Meck at LunaMetrics.com - Version 1.0
        var newhtml = oldhtml.replace(/href=['"]callto:/gi,'href="tel:')
        newhtml = newhtml.replace(phonereg, function ($0, $1, $2, $3, $4, $5, $6) {
            if ($3) return $1;
            else if ($4) return $2+$4+$5+$6;
            else return $2+"<a href='tel:"+$5.replace(spechars,"")+"'>"+$5+"</a>"+$6; });
        return newhtml;
        }

        $('#content1').html(ReplacePhoneNumbers($('#content1').html()));
        //$('#footer-wsu').html(ReplacePhoneNumbers($('#footer-wsu').html()));
    }

    $('#content1 iframe').each(function(i) {
        var src = $(this).attr('src'),
            video_id,
            $this = $(this),
            wrapper = $('<div></div>', {
                specialAutoThumbWrapper: '1'
            });

        // if matching youtube embed iframe is found...
        if (src && (video_id = src.match(/(72r1YzsQgs8|_xtnM8XSob0|2hsMktcSFpI|cQoElPHy9T4|yGXUPOf1hbw)/))) {
            // make image element that goes on top of iframe...
            $('<img />', {
                specialAutoThumb: '1',
                src: 'https://www.wichita.edu/thisis/images/video-thumbs/'+video_id[1]+'.jpg'
            }).appendTo(wrapper); // and put it in a div wrapper

            // make play button with hover effect...
            $('<img />', {
                specialAutoThumbPlayButton: '1',
                src: 'https://www.wichita.edu/thisis/images/video-thumbs/play-hq.png'
            }).hover(function() {
                $(this).attr('src', 'https://www.wichita.edu/thisis/images/video-thumbs/play-hq-over.png');
            }, function() {
                $(this).attr('src', 'https://www.wichita.edu/thisis/images/video-thumbs/play-hq.png');
            }).appendTo(wrapper); // and put it in a div wrapper

            // in this case $this is iframe element
            wrapper.insertBefore($this);

            wrapper.on('click', function(e) {
                $this.attr('src', $this.attr('src')+'&autoplay=1').insertBefore($(this));
                $("#content1").fitVids();
                $(this).detach();
            });

            $this.detach();
        };
    });

    if ($('#content1').length) {
        $("#content1").fitVids();
    }

    // window.setTimeout(function(){window.location.href=window.location.href},3600000);

    if ($('#nav-left').length == 0) {
        $('#content1').css({
            'width': '',
            'display': 'block'
        });
    }

    processPageSize(false);

    $('#menu-button').bind('click', function() {
        $('#nav-left').slideToggle();
    });

    if (PAGE_HREF.match(/^https:\/\/www.wichita.edu\/thisis\//i)) {
        DO_NOT_CENSOR = true;
    }

    if (PAGE_HREF.match(/^https:\/\/www.wichita.edu\/thisis\/wsunews\/dt\//i)
        ) {
        DO_NOT_CENSOR = false;
    }

    if (!DO_NOT_CENSOR) {
        censor('content1');
    }

    if ($('#ts_story').length > 0) {
        censor('ts_story');
    }

    enable_left_nav();

    if (!PAGE_HREF.match(/^https:\/\/www.wichita.edu\/thisis\//i)) {
        //fix_links();
    }

    //$('body').prepend('<div id="resp-preview" class="noselect">RESPONSIVE PREVIEW</div>');

    $("#wrapper-outer").css('display','block');

    /*
    if (getCookie('resptest') === 'ok') {
        $('body').prepend('<div class="resp-test">Responsive Test <a href="https://www.wichita.edu/thisis/r/off.asp">OFF</a></div>');
        $('.resp-test').css('opacity', 0.5);
    }
    */
    if (location.href.match(/wichita.edu\/thisis\/wsunews\/newsrelease\//)) {
        $('table#user_inserted_mugshot').detach();
    }

    if (!PAGE_HREF.match(/^https:\/\/wsu.wichita.edu/i)) {
        $.each($('#content1 table'), function(i, l){
            if (($(this).attr('id') != 'user_inserted_mugshot')
                    && !window.location.href.match(/ulrichmuseum/)) {
                $(this).responsiveTable();
            }
        });

        $(window).resize(function() {
            $.each($('#content1 table'), function(i, l){
                if (($(this).attr('id') != 'user_inserted_mugshot')
                    && !window.location.href.match(/ulrichmuseum/)) {
                    $(this).responsiveTableUpdate();
                }
            });
        });
    }

    form_do_required();

    /*
    $("#content1 a").each(function() {
        var $t = $(this).text();

        if (!$t.match(/ /)) {
            $(this).css('word-break','break-all');
        }
    })
    */

    $("#footer-dt a").each(function() {
        $(this).css('word-break','normal');
    });

    $('a.new_window').click(function() {
        window.open($(this).attr('href'));
        return false;
    });


    $("img[data-on]").each(function() {
        $(this).wrap('<div class="jqueryfade" style="background:url('+$(this).data('on')+') no-repeat"></div>');
    });

    $("img[data-on]").hover(function(){
        $(this).stop().animate({ opacity: 0 }, 'slow');
    }, function() {
        $(this).stop().animate({ opacity: 1 }, 'slow');
    });

    /*
    $('img[data-on]').hover(function() {
        $(this).data('off', $(this).attr('src'));

        $(this).stop(true, true).fadeOut(350, function() {});

        $(this).attr('src', $(this).data('on')).fadeIn(350);

    }, function() {
        $(this).stop(true, true).fadeOut(350, function() {
              $(this).attr('src', $(this).data('off')).fadeIn(350);
        });

    });
    */
});

function form_do_required() {
    var found = false;

    /**
    kang: really? what is this for?
    */
    $('form .webks-responsive-table:has(input[isreq])').remove();
    $('form table:has(input[isreq])').css('display', 'block');

    $('form').each(function() {
        var frm = $(this);

        //$(this).find('.webks-responsive-table').remove();

        if (frm.attr('action').indexOf('https://webs.wichita.edu/dt/form/receiver/') > -1) {
            if ($('#__ACTUAL_FORM_NAME__').length) {}
            else{
                if (frm.attr('name')) {
                    frm.prepend('<input type="hidden" name="__ACTUAL_FORM_NAME__" id="__ACTUAL_FORM_NAME__" value="'+frm.attr('name')+'">');
                };
            }

            if ($('#__OVERRIDE_EMAIL__').length) {}
            else {
                if (frm.attr('addrec')) {
                    frm.prepend('<input type="hidden" name="__OVERRIDE_EMAIL__" id="__OVERRIDE_EMAIL__" value="'+frm.attr('addrec')+'">');
                };
            }

            if ($('#__DEST__').length) {}
            else{
                if (frm.attr('dest')) {
                    frm.prepend('<input type="hidden" name="__DEST__" id="__DEST__" value="'+frm.attr('dest')+'">');
                };
            }

            if ($('#__ACTUAL_FORM_NAME__').length) {}
            else{
                if (frm.attr('name')) {
                    frm.prepend('<input type="hidden" name="__ACTUAL_FORM_NAME__" id="__ACTUAL_FORM_NAME__" value="'+frm.attr('name')+'">');
                };
            }
        }

        frm.find('input[isreq],textarea[isreq]').each(function() {
            $(this).after('<span style="color:red;font-weight:bold;font-size:20px;">*</span>');
            found = true;
        });

        if (found) {
            frm.prepend('<div style="color: red; width: 140px; font-weight: bold; float: right; text-align: right;">* = Required Field</div>');

            frm.bind('submit', function() {
                $('.webks-responsive-table:hidden').remove();

                if (document.location.href.indexOf('u=wsupostoffice') == -1) {
                    var focused = false;
                    var frm_ok = true;

                    frm.find('.input-required').removeClass('input-required');

                    frm.find('input[isreq]').each(function() {
                        var el = $(this);

                        if (el.attr('type') === 'text') {
                            if (el.val().trim() == '') {
                                el.addClass('input-required');
                                if (!focused) {
                                    el.focus();
                                    focused = true;
                                    frm_ok = false;
                                }
                            }
                        }

                        //console.log(el.attr('name')+': '+frm_ok);

                        if (el.attr('type') === 'radio') {
                            if ($('input[name="'+el.attr('name')+'"]:checked').length === 0) {
                                if (!focused) {
                                    el.focus();
                                    focused = true;
                                    frm_ok = false;
                                }
                            };
                        }

                        //console.log(el.attr('name')+': '+frm_ok);
                    });

                    frm.find('textarea[isreq]').each(function() {
                        var el = $(this);

                        if (el.val().trim() == '') {
                            el.addClass('input-required');
                            if (!focused) {
                                el.focus();
                                focused = true;
                                frm_ok = false;
                            }
                        }

                        //console.log(el.attr('name')+': '+frm_ok);
                    });
                }

                if (frm_ok) {
                    frm.find('input[type=checkbox]').not(':checked').each(function() {
                        var el = $(this);

                        el.replaceWith('<input type="hidden" value="*" name="'+el.attr('name')+'">');
                    });
                }
                else {
                    $('.form-error-msg').remove();

                    frm.find('input[type=submit]').each(function() {
                        $(this).after('<span style="color:red;font-weight:bold;font-size:13px;margin-left:10px;" class="form-error-msg">You must complete all required fields.</span>');
                    });
                }

                return frm_ok;
            });
        }
        else {
            frm.bind('submit', function() {
                $('.webks-responsive-table:hidden').remove();
                $('.webks-responsive-table').prev('table:hidden').remove();
            })
        }
    });
}

/* This function is not used; */
function __ONSUBMIT(o) { }
function __onsubmit(o) { }

function fix_links() {
    $('a').each(function() {
        $(this).attr('href', function(index, v) {
            return (v) ? v.replace(/\/\?u=/,'/r.asp?u=') : '#';
        })
    });
}

function enable_left_nav() {
    //console.log('active-nav-item: '+$.cookie("active-nav-item"));

    $('#nav-left > ul > li > ul > li').prepend('&#187; ');

    $('#nav-left > ul > li').each(function() {
        $(this).has('ul').each(function() {
            $(this).html(function() {
                return $(this).html().replace(/^([\S\s]*?)<ul>/i, '$1 <img src="https://www.wichita.edu/thisis/zed/images/ad_h.gif" class="left-nav-arrow"><ul>');
            });

            $(this).css('cursor', 'pointer');

            $(this).click(function() {
                //console.log('nav clicked');

                var c = $(this).children('ul');
                var icon = $(this).children('.left-nav-arrow');

                if (c.is(':visible')) {
                    icon.attr('src', 'https://www.wichita.edu/thisis/zed/images/ad_h.gif');
                    c.slideUp();

                    $.removeCookie("active-nav-item");
                }
                else {
                    $('.left-nav-arrow').attr('src', 'https://www.wichita.edu/thisis/zed/images/ad_h.gif');
                    $('#nav-left > ul > li > ul').slideUp();
                    icon.attr('src', 'https://www.wichita.edu/thisis/zed/images/ad.gif');
                    c.slideDown();

                    $.cookie("active-nav-item", $(this).attr('id'), {
                       expires : 10,           //expires in 10 days

                       path    : '/',          //The value of the path attribute of the cookie
                                               //(default: path of page that created the cookie).

                       domain  : 'wichita.edu',  //The value of the domain attribute of the cookie
                                               //(default: domain of page that created the cookie).

                       secure  : false          //If set to true the secure attribute of the cookie
                                               //will be set and the cookie transmission will
                                               //require a secure protocol (defaults to false).
                    });
                }
            });
        });
    });

    if ($.cookie("active-nav-item")) {
        $('li#'+$.cookie("active-nav-item")).trigger('click');
    }
}

function censor(o) {
    $('#'+o+' div, #'+o+' p').each(function() {
        var w = $(this).width();
        $(this).removeAttr('width');
        $(this).removeAttr('height');
        //$(this).removeAttr('cellspacing');
        //$(this).removeAttr('cellpadding');

        // take style height and width from divs
        $(this).css({
            'width': '',
            'height': ''
        });
    });

    $('#'+o+' input, #'+o+' textarea, #'+o+' select').each(function() {
        //if ($(this).width() >= 100) {
            $(this).css({
                'max-width': '90%'
            });
        //}
    });

    $('#'+o+' table, #'+o+' td, #'+o+' tr, #'+o+' p').each(function() {
        var w = $(this).width();

        /* bryan asked to remove these 5/7/2013
        $(this).removeAttr('cellspacing');
        $(this).removeAttr('cellpadding');
        $(this).removeAttr('style');
        */

        //$(this).removeAttr('width');
        //$(this).removeAttr('height');

/*
        $(this).css({
            'width': '',
            'height': ''
        });
*/
    });

    $('#'+o+' td').each(function() {
        $(this).attr('valign', 'top');
    });

    $('#'+o+' iframe').each(function() {

    });

    $('#'+o+' img, #asp_top_image img').each(function() {
        var w = $(this).width();

        if ($(this).parents('td').length > 0) {
/*
            $(this).removeAttr('style');
            $(this).removeAttr('class');

            $(this).removeAttr('width');
            $(this).removeAttr('height');

            $(this).css({
                'max-width': '100%',
                'height': 'auto'
            });
*/
            /*
            $(this).removeAttr('hspace');
            $(this).removeAttr('vspace');
            */
            $(this).removeAttr('border');

            return;
        }

        //$(this).removeAttr('style');
        $(this).css({
                'width':'',
                'height':''
            });

        $(this).removeAttr('class');
        $(this).removeAttr('width');
        $(this).removeAttr('height');
        //$(this).removeAttr('hspace');
        //$(this).removeAttr('vspace');
        $(this).removeAttr('border');
        $(this).css({
            'max-width':'100%'
        });

        if (w > 0) {
            $(this).css('width', w);
        }
        /*
        if (w > 550) {
            $(this).css('width', 565);
        }
        */
    });
}

var was_in_wide = false;

$(window).resize(function () {
    processPageSize(true);
});

function processPageSize(for_resize) {
    var w = $(window).width();

    if ((w >= 560) && !was_in_wide) {
        was_in_wide = true;
        //console.log('resized to wide');
    }

    if (w < 560) {
        $('.hide-non-mobile').show();

        // this prevents nav-left from expanding when going from wide to narrow! 5/7/13
        $('.full-width-hide:not(#nav-left)').show();

        $('#footer-dt').before($('#contact-box'));

        // this line was causing iphone 5 safari to act weirdly when orientation changed from landscape to portrait
        //$('#wrapper-outer').css('width', 100);

        if ($('#top-mobile-menu-btn').length > 0) {}
        else {
            $('#top-bar-black').prepend('<div id="top-mobile-menu-btn"><img src="https://www.wichita.edu/thisis/r/images/tmb-image.png" style="margin:5px;margin-right:15px"></div>');
            $('#top-mobile-menu-btn img').click(function() {
                $('#top-bar-black ul').slideToggle();
            });
        }

        $('#top-mobile-menu-btn').css('display','block');

        $('#top-bar-black').css('height', '100%');
        $('#top-bar-black').append($('#top-menu-bar').children('ul'));
        $('#top-bar-black').append($('#top-menu-bar9').children('ul'));

        if ($('#top-logo-image-border-bottom').length == 0) {
            $('#top-logo-image').after('<div class="line-hori hide-non-mobile" id="top-logo-image-border-bottom"></div>');
        }

        // ONLY if this was not for resize OR previous width was greater than 560 then hide top menu
        // had to be done this way because of android device > opening keyboard event would trigger resize event!
        if (!for_resize || was_in_wide) {
            $('#nav-left').append($('#pushdown'));

            $('#nav-left').css({
                'display':'block',
                'margin-top':0,
                'overflow':'hidden'
            }).hide();

            $('#top-bar-black ul').hide();
        }

        if (was_in_wide) {
            was_in_wide = false;
        }

        // for dt landing page
        $('#flash_placeholder').css('height','100%');
        $('.menu_above_flash_inner').children('ul').attr('id','menu_above_flash_inner_ul');

        if ($('#social-stuff').length) {
            $('#social-stuff').before($('#menu_above_flash_inner_ul'));
        }
        else {
            $('#pushdown').before($('#menu_above_flash_inner_ul'));
        }

        $('.stay-in-content').each(function() {
            $('#div-left').append($(this));
        })
        // ###

        // asp top image is causing chrome to lose div1 when resized to full
        //$('#content1').before($('#asp_top_image'));
        $('#wrapper-inner').before($('#asp_top_image'));
        $('#asp_top_image').css('display','block');

        /*
        $('#nav-left').css({
            'margin-top': 0
        });
        */

        /*
        if ($.browser.msie) {
            $('#content1 img').css('width', '100%');
        }
        */

        if ($('#NewHeadSet1').length > 0) {
            $('#content-bottom').append($('#NewHeadSet1'));
        }

        if ($('#super-news-chunk').length > 0) {
            $('#contact-box').before($('#super-news-chunk'));
        }

        if ($('.social-icons').length > 0) {
            $('#content1').append($('.social-icons'));
        }
   }
    else {
        $('#nav-left').css('display','table-cell');
        $('#nav-left').show();
        $('.hide-non-mobile').hide();

        $('#nav-left').append($('#contact-box'));
        $('#wrapper-outer').css('width', 779);

        $('#top-menu-bar').append($('#top-bar-black').children('ul'));
        $('#top-menu-bar9').append($('#top-bar-black').children('ul'));
        $('#top-mobile-menu-btn').css('display','none');

        $('#top-bar-black').css('height', 12);
        $('#top-menu-bar').children('ul').show();
        $('#top-menu-bar9').children('ul').show();

        $('.full-width-hide').hide();

        $('#flash_placeholder').css('height',305);

        // for dt landing page
        $('.stay-in-content').each(function() {
            $('#pushdown').append($(this));
        })
        $('#content-bottom').append($('#pushdown'));
        $('.menu_above_flash_inner').append($('#menu_above_flash_inner_ul'));
        // ###

        $('#wrapper-inner').before($('#asp_top_image'));
        $('#asp_top_image').css('display','block');

        if ($('#dept_header').length == 0) {
            $('#nav-left').css({
                'margin-top': -10
            });
        }

        if ($('#NewHeadSet1').length > 0) {
            $('#nav-news-div').append($('#NewHeadSet1'));
        }

        if ($('#super-news-chunk').length > 0) {
            $('#asp-template-left-super-news').append($('#super-news-chunk'));
        }

        if ($('.social-icons').length > 0) {
            $('#content1').prepend($('.social-icons'));
        }
    }
}

/*global jQuery */
/*!
* FitVids 1.0
*
* Copyright 2011, Chris Coyier - https://css-tricks.com + Dave Rupert - https://daverupert.com
* Credit to Thierry Koblentz - https://www.alistapart.com/articles/creating-intrinsic-ratios-for-video/
* Released under the WTFPL license - https://sam.zoy.org/wtfpl/
*
* Date: Thu Sept 01 18:00:00 2011 -0500
*/

(function( $ ){
  $.fn.fitVids = function( options ) {
    var settings = {
      customSelector: null
    }

    var div = document.createElement('div'),
        ref = document.getElementsByTagName('base')[0] || document.getElementsByTagName('script')[0];

    div.className = 'fit-vids-style';
    div.innerHTML = '&shy;<style>         \
      .fluid-width-video-wrapper {        \
         width: 100%;                     \
         position: relative;              \
         padding: 0;                      \
      }                                   \
                                          \
      .fluid-width-video-wrapper iframe,  \
      .fluid-width-video-wrapper object,  \
      .fluid-width-video-wrapper embed {  \
         position: absolute;              \
         top: 0;                          \
         left: 0;                         \
         width: 100%;                     \
         height: 100%;                    \
      }                                   \
    </style>';

    ref.parentNode.insertBefore(div,ref);

    if ( options ) {
      $.extend( settings, options );
    }

    return this.each(function(){
      var selectors = [
        "iframe[src*='player.vimeo.com']",
        "iframe[src*='www.youtube.com']",
        "iframe[src*='www.kickstarter.com']",
        "object",
        "embed"
      ];

      if (settings.customSelector) {
        selectors.push(settings.customSelector);
      }

      var $allVideos = $(this).find(selectors.join(','));

      $allVideos.each(function(){
        var $this = $(this);
        if (this.tagName.toLowerCase() == 'embed' && $this.parent('object').length || $this.parent('.fluid-width-video-wrapper').length) { return; }
        var height = ( this.tagName.toLowerCase() == 'object' || $this.attr('height') ) ? $this.attr('height') : $this.height(),
            width = $this.attr('width') ? $this.attr('width') : $this.width(),
            aspectRatio = height / width;
        if(!$this.attr('id')){
          var videoID = 'fitvid' + Math.floor(Math.random()*999999);
          $this.attr('id', videoID);
        }
        $this.wrap('<div class="fluid-width-video-wrapper"></div>').parent('.fluid-width-video-wrapper').css('padding-top', (aspectRatio * 100)+"%");
        $this.removeAttr('height').removeAttr('width');
      });
    });
  }
})( jQuery );

function getCookie(name) {
    var cookiename = name + "=";
    var ca = document.cookie.split(';');

    for(var i=0;i < ca.length;i++)
    {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(cookiename) == 0) return c.substring(cookiename.length,c.length);
    }

    return null;
}

function delCookie(name)
{
    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
}

/**
 * @file Contains the jQuery "webks Responsive Table" Plugin.
 *
 * @version 1.0.0
 * @since 2012-08-20
 * @see Project home:
 * @category responsive webdesign, jquery
 * @author webks:websolutions kept simple - Julian Pustkuchen & Thomas Frobieter
 *         GbR | https://www.webks.de
 * @copyright webks:websolutions kept simple - Julian Pustkuchen & Thomas
 *            Frobieter GbR | https://www.webks.de
 */
(function($) {
  /*
   * Usage Examples:
   *  -- Simple: -- Make all tables responsible using the default settings.
   * $('table').responsiveTable();
   *  -- Custom configuration example 1 (Disable manual switch): --
   * $('table').responsiveTable({ showSwitch: false });
   *  -- Custom configuration example 2 (Use different selectors): --
   * $('table').responsiveTable({ headerSelector: 'tr th', bodyRowSelector:
   * 'tr', });
   *  -- Custom configuration example 3 (Use different screensize in dynamic
   * mode): -- $('table').responsiveTable({ displayResponsiveCallback:
   * function() { return $(document).width() < 500; // Show responsive if screen
   * width < 500px }, });
   *  -- Custom configuration example 4 (Make ALL tables responsive - regardless
   * of screensize): -- $('table').responsiveTable({ dynamic: false });
   */

  /**
   * jQuery "webks Responsive Table" plugin transforms less mobile compliant
   * default HTML Tables into a flexible responsive format. Furthermore it
   * provides some nice configuration options to optimize it for your special
   * needs.
   *
   * Technically the selected tables are being transformed into a list of
   * (definition) lists. The table header columns are used as title for each
   * value.
   *
   * Functionality: - Select tables easily by jQuery selector. - Provide custom
   * rules (by callback function) for transformation into tables mobile version. -
   * Hard or dynamic switching for selected tables. - Use custom header and
   * content rows selectors. - Provides an optional, customizable link to
   * default table layout. - (Optionally) preserves most of the table elements
   * class attributes. - Decide if the original table is kept in DOM and set
   * invisible or completely removed. - Update display type (Table / Responsive &
   * re-calculate dynamic switch) by easily calling
   * .responsiveTableUpdate()-function.
   *
   * Functionality may be applied to all DOM table elements. See examples above.
   * Please ensure that the settings match your requirements and your table
   * structure is compliant.
   */
  $.fn.responsiveTable = function(options) {
    return $(this).each(function(){
      $(this).responsiveTableInit(options);
    });
  };

  /**
   * Initializes the responsive tables. Expects to be executed on DOM table
   * elements only. These are being transformed into responsive tables like
   * configured.
   *
   * @param options
   *            Optional JSON list of settings.
   */
  $.fn.responsiveTableInit = function(options) {
    var settings = $.extend({
      /**
       * Keep table components classes as far as possible for the responsive
       * output.
       */
      preserveClasses : true,
      /**
       * true: Toggle table style if settings.dynamicSwitch() returns true.
       * false: Only convert to mobile (one way)
       */
      dynamic : true,
      /**
       * (Only used if dynamic!) If this function returns true, the responsive
       * version is shown, else displays the default table. Might be used to set
       * a switch based on orientation, screen size, ... for dynamic switching!
       *
       * @return boolean
       */
      displayResponsiveCallback : function() {
        //console.log('|'+$(document).width()+'|, |560|');

        return $(document).width() < 560;
      },
      /**
       * (Only used if dynamic!) Display a link to switch back from responsive version to original table version.
       */
      showSwitch : true,
      /**
       * (Only used if showSwitch: true!) The title of the switch link.
       */
      switchTitle : 'Switch to original table view',

      // Selectors
      /**
       * The header columns selector.
       * Default: 'thead td, thead th';
       * other examples: 'tr th', ...
       */
      headerSelector : 'thead td, thead th, tbody th',
      /**
       * The body rows selector.
       * Default: 'tbody tr';
       * Other examples: 'tr', ...
       */
      bodyRowSelector : 'tbody tr',

      // Elements
      /**
       * The responsive rows container
       * element.
       * Default: '<dl></dl>';
       * Other examples: '<ul></ul>'.
       */
      responsiveRowElement : '<dl></dl>',
      /**
       * The responsive column title
       * container element.
       * Default: '<dt></dt>';
       * Other examples: '<li></li>'.
       */
      responsiveColumnTitleElement : '<dt></dt>',
      /**
       * The responsive column value container element.
       * Default: '<dd></dd>';
       * Other examples: '<li></li>'.
       */
      responsiveColumnValueElement : '<dd></dd>',
      responsiveColumnValueElementFull : '<df></df>'
    }, options);

    return this.each(function() {
      // $this = The table (each).
      var $this = $(this);

      // Ensure that the element this is being executed on a table!
      $this._responsiveTableCheckElement(false);

      if ($this.data('webks-responsive-table-processed')) {
        // Only update if already processed.
        $this.responsiveTableUpdate();
        return true;
      }

      // General
      var result = $('<div></div>');
      result.addClass('webks-responsive-table');
      if (settings.preserveClasses) {
        result.addClass($this.attr('class'));
      }

      // Head
      // Iterate head - extract titles
      var titles = new Array();
      $this.find(settings.headerSelector).each(function(i, e) {
        var title = $(this).html();
        titles[i] = title.trim();
      });

      // Body
      // Iterate body
      $this.find(settings.bodyRowSelector).each(function(i, e) {
        // Row
        var row = $(settings.responsiveRowElement);
        row.addClass('row row-' + i);
        if (settings.preserveClasses) {
          row.addClass($(this).attr('class'));
        }
        // Column
        $(this).children('td').each(function(ii, ee) {
          var dt = $(settings.responsiveColumnTitleElement);
          if (settings.preserveClasses) {
            dt.addClass($(this).attr('class'));
          }
          dt.addClass('title col-' + ii);

          if (typeof(titles[ii]) != 'undefined') {
            dt.html(titles[ii]+'');

            var dd = $(settings.responsiveColumnValueElement);
            if (settings.preserveClasses) {
                dd.addClass($(this).attr('class'));
            }
            dd.addClass('value col-' + ii);
            dd.html($(this).html()+'<div style="clear:both;"></div>');
            // Set empty class if value is empty.
            if ($.trim($(this).html()) == '') {
                dd.addClass('empty');
                dt.addClass('empty');
            }
            row.append(dt).append(dd);
          }
          else {
            var dd = $(settings.responsiveColumnValueElementFull);
            if (settings.preserveClasses) {
                dd.addClass($(this).attr('class'));
            }
            dd.addClass('value col-' + ii);
            dd.html($(this).html()+'<div style="clear:both;"></div>');
            // Set empty class if value is empty.
            if ($.trim($(this).html()) == '') {
                dd.addClass('empty');
                dt.addClass('empty');
            }
            row.append(dd);
          }
        });

        if (row.html() != '') {
            result.append(row);
        }
      });

      // Display responsive version after table.
      $this.after(result);

      // Further + what shell we do with the processed table now?
      if (settings.dynamic) {
        if (settings.showSwitch) {
          var switchBtn = $('<a>');
          switchBtn.html(settings.switchTitle);
          switchBtn.addClass('switchBtn btn');
          switchBtn.attr('href', '#');

          $('div.webks-responsive-table a.switchBtn').live('click',
              function(e) {
                $this.responsiveTableShowTable();
                e.preventDefault();
                return false;
              });
          result.prepend(switchBtn);
        }

        // Connect result to table
        $this.data('webks-responsive-table', result);
        $this.data('webks-responsive-table-processed', true);

        // Connect table to result.
        result.data('table', $this);
        result.data('settings', settings);
        $this.data('webks-responsive-table-processed', true);

        // Hide table. We might need it again!
        $this.hide();

        // Run check to display right display version (table or responsive)
        $this.responsiveTableUpdate();
      } else {
        // Remove table entirely.
        $this.remove();
      }
    });
  };
  /**
   * Re-Check the .displayResponsiveCallback() and display table according to
   * its result. Only available if settings.dynamic is true.
   *
   * May be called on Window resize, Orientation Change, ... Must be executed on
   * already processed DOM table elements.
   */
  $.fn.responsiveTableUpdate = function() {
    return this.each(function() {
      // $this = The table (each).
      var $this = $(this);

      // Ensure that the element this is being executed on must be a table!
      $this._responsiveTableCheckElement(true);

      var responsiveTable = $this.data('webks-responsive-table');
      if (responsiveTable != undefined) {
        var settings = responsiveTable.data('settings');
        if (settings != undefined) {
          // Check preconditions!
          if (settings.dynamic) {
            // Is dynamic!
            if (!settings.displayResponsiveCallback()) {
              // NOT matching defined responsive conditions!
              // Show original table and skip!
              $this.responsiveTableShowTable();
            } else {
              $this.responsiveTableShowResponsive();
              //console.log('on noo... ie');
            }
          }
        }
      }
    });
  };
  /**
   * Displays the default table style and hides the responsive layout.
   *
   * Only available if settings.dynamic is true. Does nothing if the current
   * display is already as wished.
   */
  $.fn.responsiveTableShowTable = function() {
    return this.each(function() {
      // $this = The table (each).
      var $this = $(this);
      // Ensure that the element this is being executed on must be a table!
      $this._responsiveTableCheckElement(true);

      var responsiveTable = $this.data('webks-responsive-table');
      if (responsiveTable.length > 0) {
        $this.show();
        responsiveTable.hide();
      }
    });
  };

  /**
   * Displays the responsive style and hides the default table layout.
   *
   * Only available if settings.dynamic is true. Does nothing if the current
   * display is already as wished.
   */
  $.fn.responsiveTableShowResponsive = function() {
    return this.each(function() {
      // $this = The table (each).
      var $this = $(this);
      // Ensure that the element this is being executed on must be a table!
      $this._responsiveTableCheckElement();

      var responsiveTable = $this.data('webks-responsive-table');
      if (responsiveTable.length > 0) {
        $this.hide();
        responsiveTable.show();
      }
    });
  };

  /**
   * Checks the general preconditions for elements that this Plugin is being
   * executed on.
   *
   * @throws Exception
   *             if the given DOM element is not a table.
   * @throws Exception
   *             if a helper method is directly called on a not yet initialized
   *             table.
   */
  $.fn._responsiveTableCheckElement = function(checkProcessed) {
    if (checkProcessed === undefined) {
      checkProcessed = true;
    }
    var $this = $(this);
    if (!$this.is('table')) {
      throw 'The selected DOM element may only be a table!';
    }
    if (checkProcessed
        && ($this.data('webks-responsive-table-processed') === undefined || !$this
            .data('webks-responsive-table-processed'))) {
      throw 'The selected DOM element has to be initialized by webks-responsive-table first.';
    }
    return $this;
  };
})(jQuery);

/*!
 * jQuery Cookie Plugin v1.3.1
 * https://github.com/carhartl/jquery-cookie
 *
 * Copyright 2013 Klaus Hartl
 * Released under the MIT license
 */
(function (factory) {
    if (typeof define === 'function' && define.amd) {
        // AMD. Register as anonymous module.
        define(['jquery'], factory);
    } else {
        // Browser globals.
        factory(jQuery);
    }
}(function ($) {

    var pluses = /\+/g;

    function raw(s) {
        return s;
    }

    function decoded(s) {
        return decodeURIComponent(s.replace(pluses, ' '));
    }

    function converted(s) {
        if (s.indexOf('"') === 0) {
            // This is a quoted cookie as according to RFC2068, unescape
            s = s.slice(1, -1).replace(/\\"/g, '"').replace(/\\\\/g, '\\');
        }
        try {
            return config.json ? JSON.parse(s) : s;
        } catch(er) {}
    }

    var config = $.cookie = function (key, value, options) {

        // write
        if (value !== undefined) {
            options = $.extend({}, config.defaults, options);

            if (typeof options.expires === 'number') {
                var days = options.expires, t = options.expires = new Date();
                t.setDate(t.getDate() + days);
            }

            value = config.json ? JSON.stringify(value) : String(value);

            return (document.cookie = [
                config.raw ? key : encodeURIComponent(key),
                '=',
                config.raw ? value : encodeURIComponent(value),
                options.expires ? '; expires=' + options.expires.toUTCString() : '', // use expires attribute, max-age is not supported by IE
                options.path    ? '; path=' + options.path : '',
                options.domain  ? '; domain=' + options.domain : '',
                options.secure  ? '; secure' : ''
            ].join(''));
        }

        // read
        var decode = config.raw ? raw : decoded;
        var cookies = document.cookie.split('; ');
        var result = key ? undefined : {};
        for (var i = 0, l = cookies.length; i < l; i++) {
            var parts = cookies[i].split('=');
            var name = decode(parts.shift());
            var cookie = decode(parts.join('='));

            if (key && key === name) {
                result = converted(cookie);
                break;
            }

            if (!key) {
                result[name] = converted(cookie);
            }
        }

        return result;
    };

    config.defaults = {};

    $.removeCookie = function (key, options) {
        if ($.cookie(key) !== undefined) {
            // Must not alter options, thus extending a fresh object...
            $.cookie(key, '', $.extend({}, options, { expires: -1 }));
            return true;
        }
        return false;
    };
}));
