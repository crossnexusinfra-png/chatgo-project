/**
 * Google Ad Manager GPT。
 * data-gpt-slot を定義して display する。後から挿入された枠も chatgoGptServe で出す。
 * 定期 refresh はしない。遅れて挿入した枠の初回取得にだけ refresh を使う。
 */
(function () {
    'use strict';

    window.googletag = window.googletag || { cmd: [] };

    var servicesReady = false;
    var rewardedSlot = null;
    var rewardedReadyEvent = null;
    var slotsByElementId = {};

    var INLINE_SIZES = [[728, 90], [468, 60], [320, 50], [300, 250]];

    function slotWidth(el) {
        var node = el;
        var width = 0;
        while (node && width < 2) {
            width = node.clientWidth;
            node = node.parentElement;
        }
        return width;
    }

    function sizesFor(el) {
        if (el.getAttribute('data-gpt-size') !== 'inline') {
            return [[300, 250], [160, 600], [120, 600], [300, 600]];
        }
        var width = slotWidth(el);
        var fitting = INLINE_SIZES.filter(function (size) {
            return size[0] <= width;
        });
        return fitting.length ? fitting : [[320, 50]];
    }

    function sizeSignature(sizes) {
        return sizes.map(function (size) {
            return size[0] + 'x' + size[1];
        }).join(',');
    }

    function dispatchRewarded(name) {
        document.dispatchEvent(new CustomEvent(name));
    }

    function bindRewarded(slot) {
        if (rewardedSlot) {
            return;
        }
        rewardedSlot = slot;
        var pubads = googletag.pubads();
        pubads.addEventListener('rewardedSlotReady', function (event) {
            if (!event.slot || event.slot !== rewardedSlot) {
                return;
            }
            rewardedReadyEvent = event;
            dispatchRewarded('chatgo-rewarded-ready');
        });
        pubads.addEventListener('rewardedSlotGranted', function (event) {
            if (!event.slot || event.slot !== rewardedSlot) {
                return;
            }
            dispatchRewarded('chatgo-rewarded-granted');
        });
        pubads.addEventListener('rewardedSlotClosed', function (event) {
            if (!event.slot || event.slot !== rewardedSlot) {
                return;
            }
            rewardedReadyEvent = null;
            dispatchRewarded('chatgo-rewarded-closed');
            googletag.pubads().refresh([rewardedSlot]);
        });
    }

    function defineSlot(el) {
        var path = el.getAttribute('data-gpt-path');
        var size = el.getAttribute('data-gpt-size');
        if (size === 'interstitial' || size === 'rewarded') {
            if (!window.googletag.enums || !googletag.enums.OutOfPageFormat) {
                return null;
            }
            var format = size === 'rewarded'
                ? googletag.enums.OutOfPageFormat.REWARDED
                : googletag.enums.OutOfPageFormat.INTERSTITIAL;
            var outOfPage = googletag.defineOutOfPageSlot(path, format);
            if (outOfPage && size === 'rewarded') {
                bindRewarded(outOfPage);
            }
            return outOfPage;
        }
        if (!el.id) {
            return null;
        }
        return googletag.defineSlot(path, sizesFor(el), el.id);
    }

    function serve(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var nodes = Array.prototype.filter.call(
            scope.querySelectorAll('[data-gpt-slot]:not([data-chatgo-gpt-defined])'),
            function (el) {
                return !!el.getAttribute('data-gpt-path');
            }
        );
        if (!nodes.length) {
            return;
        }
        nodes.forEach(function (el) {
            el.setAttribute('data-chatgo-gpt-defined', '1');
            if (el.getAttribute('data-gpt-size') === 'inline') {
                el.setAttribute('data-gpt-size-sig', sizeSignature(sizesFor(el)));
            }
        });

        googletag.cmd.push(function () {
            var late = servicesReady;
            var slots = [];
            nodes.forEach(function (el) {
                var slot = defineSlot(el);
                if (!slot) {
                    return;
                }
                slot.addService(googletag.pubads());
                slots.push(slot);
                if (el.id) {
                    slotsByElementId[el.id] = slot;
                }
            });
            if (!slots.length) {
                return;
            }
            if (!servicesReady) {
                googletag.pubads().enableSingleRequest();
                googletag.pubads().collapseEmptyDivs();
                googletag.enableServices();
                servicesReady = true;
            }
            if (late) {
                var displaySlots = slots.filter(function (slot) {
                    return slot !== rewardedSlot;
                });
                if (displaySlots.length) {
                    googletag.pubads().refresh(displaySlots);
                }
                return;
            }
            slots.forEach(function (slot) {
                googletag.display(slot);
            });
        });
    }

    window.chatgoGptServe = serve;
    function collectSlotElements(root) {
        var list = [];
        if (root && root.nodeType === 1 && root.matches && root.matches('[data-gpt-slot]') && root.id) {
            list.push(root);
        }
        if (root && root.querySelectorAll) {
            root.querySelectorAll('[data-gpt-slot]').forEach(function (el) {
                if (el.id && list.indexOf(el) === -1) {
                    list.push(el);
                }
            });
        }
        return list;
    }

    window.chatgoGptDestroy = function (root) {
        var ids = collectSlotElements(root).map(function (el) {
            return el.id;
        });
        if (!ids.length) {
            return;
        }
        googletag.cmd.push(function () {
            var doomed = [];
            ids.forEach(function (id) {
                if (!slotsByElementId[id]) {
                    return;
                }
                doomed.push(slotsByElementId[id]);
                delete slotsByElementId[id];
            });
            if (doomed.length && googletag.destroySlots) {
                googletag.destroySlots(doomed);
            }
        });
    };
    window.chatgoRewardedReady = function () {
        return !!rewardedReadyEvent;
    };
    window.chatgoShowRewardedAd = function () {
        if (!rewardedReadyEvent) {
            return false;
        }
        var event = rewardedReadyEvent;
        rewardedReadyEvent = null;
        event.makeRewardedVisible();
        return true;
    };

    function relayoutInlineSlots() {
        document.querySelectorAll('[data-gpt-slot][data-gpt-size="inline"]').forEach(function (el) {
            var next = sizeSignature(sizesFor(el));
            if (!el.getAttribute('data-chatgo-gpt-defined') || el.getAttribute('data-gpt-size-sig') === next) {
                return;
            }
            el.removeAttribute('data-chatgo-gpt-defined');
            if (window.chatgoGptDestroy) {
                window.chatgoGptDestroy(el);
            }
            serve(el.parentNode);
        });
    }

    var relayoutTimer = 0;
    window.addEventListener('resize', function () {
        window.clearTimeout(relayoutTimer);
        relayoutTimer = window.setTimeout(relayoutInlineSlots, 200);
    });

    function initOnReady() {
        serve(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOnReady);
    } else {
        initOnReady();
    }
})();
