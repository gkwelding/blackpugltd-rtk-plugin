"use strict";

window.bpltdStickyTopAdUnit = function($window, $document) {
    const auctionCode = 'v8QT';
    const adunitId = 'ksgi';

    let createContainer = function() {
        let $adDiv = document.createElement('div');
        $adDiv.setAttribute('class', 'bpltdRTKStickyTop');
        $adDiv.setAttribute('id', 'bpltd_sticky_RTK_' + adunitId);

        let $adContainer = document.createElement('div');
        $adContainer.setAttribute('class', 'bpltdRTKStickyContainer');
        $adContainer.appendChild($adDiv);

        $document.body.appendChild($adContainer);
    };

    let loadAd = function() {
        let divMappings = {};
        divMappings['RTK_'+adunitId] = 'bpltd_sticky_RTK_'+adunitId;
        console.log("<----- putting sticky top ad onto page (RTK_"+ adunitId + ") ----->");
        $window.jitaJS.que.push(function() {
            $window.jitaJS.rtk.refreshAdUnits(['RTK_' + adunitId], true, divMappings);
        });
    };

    return {
        init: function() {
            createContainer();

            $window.addEventListener('JITA_Ready', function (e) {
                loadAd();
            });
        },
        loadContainer: function() {
            createContainer();
        },
        refresh: function() {
            loadAd();
        }
    }
}(window, document);