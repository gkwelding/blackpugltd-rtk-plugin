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
var bpltdRTKDeug = true;
var bpltdLastShownId = '';
var bpltdDistanceFromTop = (4 * window.innerHeight);
var bpltdAdBid = false;
var bpltdAdUnits = [];
var bpltdAdUnitsLoaded = {};

jQuery(function(){
    jQuery('.rtkadunit').each(function(){
        const $this = jQuery(this);
        const arrClass = $this.attr("class").split(" ");
        const adUnitCode = arrClass[0];

        if (jQuery.inArray(adUnitCode, bpltdAdUnits) < 0) {
            bpltdAdUnits.push(adUnitCode);
        }

        if($this.parent().hasClass('break')) {
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
                $parent.hasClass('break') &&
                intFromBottom <= 0 &&
                intFromTop >= 0
            ) {
                bpltdLastShownId = objId;
                bpltdAdUnitsLoaded[strAdUnitCode] = true;

                bpltdLogMessages("<----- new last shown ID set to " + bpltdLastShownId + " ----->");

                if (bpltdDoRefreshBidCache()) {
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

function bpltdDoRefreshBidCache()
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

    jQuery.each(bpltdAdUnitsLoaded, function(i, v){
        bpltdAdUnitsLoaded[i] = false;
    });
}

function bpltdInsertCachedBids()
{
    let bpltdDivMapping = {};
    let bpltdPageBanners = jQuery('div.rtkadunit.banner');
    let bpltdAdsPerPage = 3;

    if (bpltdPageBanners.length>0) bpltdAdsPerPage = 4;

    jQuery('.rtkadunit:not(.ad_loaded):lt('+bpltdAdsPerPage+')').each(function(){
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