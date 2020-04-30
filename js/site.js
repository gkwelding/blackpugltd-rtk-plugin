"use strict";

var jitaJS = window.jitaJS || {};
jitaJS.que = jitaJS.que || [];
var jita_tg_params = {};

(function(a) {
    if (a == "") return {};
    var b = {};
    for (var i = 0; i < a.length; ++i) {
        var p = a[i].split('=', 2);
        if (p.length == 1)
            jita_tg_params[p[0]] = "";
        else
            jita_tg_params[p[0]] = decodeURIComponent(p[1].replace(/\+/g, " "));
    }
})(window.location.search.substr(1).split('&'));

jita_tg_params['page'] = 1;
var bpltdRTKDeug = false;
var bpltdLastShownId = '';
var bpltdRTKStickyCounter = 0;
var bpLtdStickyRefresh = false;
var viewportHeight = window.innerHeight;
// TODO: implement Wordpress settings for scrolling ads then uncomment this
// var adScrollHeight = 0.90 * viewportHeight;
var bpltdDistanceFromTop = (3 * viewportHeight);
var bpltdAdBid = false;
var bpltdAdUnits = [];
var bpltdAdUnitsLoaded = {};
var bpltdTopStickyFirstLoad = true;

jQuery(function(){
    // TODO: implement Wordpress settings for scrolling ads then uncomment this
    // jQuery('.rtkadunit-wrapper').each(function(){
    //     jQuery(this).css('height', adScrollHeight + 'px');
    // });

    jQuery('div.rtkadunit').each(function(){
        const $this = jQuery(this);
        const arrClass = $this.attr("class").split(" ");
        const adUnitCode = arrClass[0];

        if (jQuery.inArray(adUnitCode, bpltdAdUnits) < 0) {
            bpltdAdUnits.push(adUnitCode);
            bpltdAdUnitsLoaded[adUnitCode] = false;
        }

        $this.data('adUnitCode', adUnitCode);
        $this.data('adLoaded', false);
        $this.data('adDestroyed', false);
    });
});

window.addEventListener('JITA_Ready', function (e) {
    bpltdLogMessages('JITA Ready! ' + JSON.stringify(e));

    JITA.setCustomParameters(window.jita_tg_params);

    if (bpltdAdBid === false) {
        bpltdRefreshBidCache(true);
        bpltdAdBid = true;
    }

    window.addEventListener('scroll', function () {
        jQuery('.rtkadunit').each(function(){
            const $this = jQuery(this);
            const $parent = $this.parent();
            let elementTop = $parent.offset().top;
            let elementHeight = $parent.height();
            let elementBottom = elementTop - elementHeight;
            let viewportTop = jQuery(window).scrollTop();
            let viewportBottom = viewportTop + window.innerHeight;
            let intFromBottom = elementTop - viewportBottom;
            let intFromTop = elementTop - viewportTop;
            let intElementBottomFromTop = viewportTop - elementBottom;
            const objId = $this.attr("id");
            let strAdUnitCode = $this.data('adUnitCode');

            if (
                bpltdLastShownId !== objId &&
                intFromBottom <= 0 &&
                intFromTop >= 0
            ) {
                bpltdLastShownId = objId;
                bpltdAdUnitsLoaded[strAdUnitCode] = true;

                bpltdLogMessages("<----- new last shown ID set to " + bpltdLastShownId + " ----->");

                bpltdRTKStickyCounter++;
                if (bpltdRTKStickyCounter === 2) {
                    bpltdRTKStickyCounter = 0;
                    bpLtdStickyRefresh = true;
                }

                if (typeof window.top.rtkJitaSticky != 'undefined') {
                    if (bpLtdStickyRefresh) {
                        bpltdLogMessages("<----- refreshing adhesion unit ----->");
                        window.top.rtkJitaSticky.refresh();
                    }
                }

                if (typeof window.top.bpltdStickyTopAdUnit != 'undefined') {
                    if (bpLtdStickyRefresh) {
                        if (bpltdTopStickyFirstLoad) {
                            window.top.bpltdStickyTopAdUnit.loadContainer();
                            window.top.bpltdStickyTopAdUnit.refresh();
                        } else {
                            bpltdLogMessages("<----- refreshing top sticky unit ----->");
                            window.top.bpltdStickyTopAdUnit.refresh();
                        }

                        bpltdTopStickyFirstLoad = false;
                    }
                }

                bpLtdStickyRefresh = false;

                if (bpltdDoInsertNewAds()) {
                    bpltdInsertCachedBids();
                    bpltdRefreshBidCache();
                }
            }

            if (
                !$this.data('adDestroyed') &&
                $this.data('adLoaded') &&
                intElementBottomFromTop > bpltdDistanceFromTop
            ) {
                let $adContainer = jQuery('#'+objId);
                let $adContainerParent = $adContainer.parent();
                let intAdContainerHeight = $adContainerParent.height();

                $adContainerParent.css('min-height', intAdContainerHeight);

                $this.data('adDestroyed', true);

                jitaJS.que.push(function() {
                    bpltdLogMessages("<----- destroying ad (" + objId + ") ----->");
                    JITA.destroySlot(objId);
                });
            }
        });
    });
});

window.addEventListener(
    'JITA_AdServed',
    function (e) {
        bpltdLogMessages('Ad Served! ' + JSON.stringify(e.detail));

        let strDivId = e.detail.divId;
        let $adContainer = jQuery('#'+strDivId);

        $adContainer.addClass('ad_loaded');
        $adContainer.data('adLoaded', true);
    },
    false
);

window.addEventListener(
    'JITA_NoAdServed',
    function (e) {
        bpltdLogMessages('No Ad Served! ' + JSON.stringify(e.detail));

        let strDivId = e.detail.divId;
        let $adContainer = jQuery('#'+strDivId);

        $adContainer.addClass('ad_loaded');
        $adContainer.data('adLoaded', true);
    },
    false
);

function bpltdDoInsertNewAds()
{
    let refresh = true;

    jQuery.each(bpltdAdUnitsLoaded, function(i, v) {
        if (v === false) refresh = false;
    });

    return refresh;
}

function bpltdRefreshBidCache(initialLoad = false)
{
    if (initialLoad === false) {
        JITA.regeneratePageId();

        window.jita_tg_params['page'] += 1;

        bpltdLogMessages("<----- page number now set to (" + window.jita_tg_params['page'] + ") ----->");

        JITA.setCustomParameters(window.jita_tg_params);
    }

    jitaJS.que.push(function() {
        let strOnPageAdUnits = bpltdAdUnits.join(',');
        bpltdLogMessages("<----- putting bids into cache (" + strOnPageAdUnits + ") ----->");
        jitaJS.rtk.refreshAdUnits(bpltdAdUnits, true, {}, true);
    });
}

function bpltdInsertCachedBids()
{
    jQuery.each(bpltdAdUnitsLoaded, function(i, v){
        bpltdAdUnitsLoaded[i] = false;
    });

    let bpltdDivMapping = {};

    // TODO: this selects the next 3 ads to be loaded, obviously this is an issue that needs addressing - throw back from 80's kids customisation
    jQuery('.rtkadunit:not(.ad_loaded):lt(3)').each(function(){
        let $this = jQuery(this);
        let adUnitCode = $this.data('adUnitCode');

        bpltdDivMapping[adUnitCode] = $this.attr('id');
    });

    bpltdLogMessages(bpltdDivMapping);

    jitaJS.que.push(function() {
        let strOnPageAdUnits = bpltdAdUnits.join(',');
        bpltdLogMessages("<----- pulling ads from bid cache (" + strOnPageAdUnits + ") ----->");
        jitaJS.rtk.refreshAdUnits(bpltdAdUnits, false, bpltdDivMapping);
    });
}

function bpltdLogMessages(str)
{
    if (bpltdRTKDeug) console.log(str);
}