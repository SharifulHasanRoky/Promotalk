/**
 * Developer DataLayer Pro - Frontend Helper
 * Provides utility functions for dataLayer push and AJAX event sending.
 */
(function($) {
    'use strict';

    window.DDLPro = window.DDLPro || {};

    /**
     * Push event to dataLayer
     */
    DDLPro.push = function(eventName, data) {
        data = data || {};
        data.event = eventName;
        data.ddl_timestamp = Date.now();
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(data);
    };

    /**
     * Push ecommerce event (clears ecommerce first)
     */
    DDLPro.pushEcom = function(eventName, ecomData) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({ ecommerce: null });
        window.dataLayer.push({
            event: eventName,
            ecommerce: ecomData
        });
    };

    /**
     * Send event to server via AJAX (for Conversion API)
     */
    DDLPro.sendServer = function(eventName, eventData) {
        if (!ddlProConfig || !ddlProConfig.ajaxUrl) return;

        $.ajax({
            url: ddlProConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ddl_pro_server_event',
                nonce: ddlProConfig.nonce,
                event_name: eventName,
                event_data: JSON.stringify(eventData)
            }
        });
    };

    /**
     * Get client ID from _ga cookie
     */
    DDLPro.getClientId = function() {
        var match = document.cookie.match(/_ga=GA\d+\.\d+\.(.+)/);
        return match ? match[1] : '';
    };

    /**
     * Get session ID from _ga_XXXX cookie
     */
    DDLPro.getSessionId = function() {
        var ga4Id = (ddlProConfig.ga4Id || '').replace('G-', '');
        if (!ga4Id) return '';
        var match = document.cookie.match(new RegExp('_ga_' + ga4Id + '=GS\\d+\\.\\d+\\.(.+?)\\.' ));
        return match ? match[1] : '';
    };

    /**
     * Update consent state
     */
    DDLPro.updateConsent = function(consentObj) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(function() {
            gtag('consent', 'update', consentObj);
        });
    };

})(jQuery);
