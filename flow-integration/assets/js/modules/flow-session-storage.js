/**
 * Flow Session Storage Helper
 *
 * Centralizes sessionStorage access for Flow keys.
 *
 * @module FlowSessionStorage
 */
(function() {
	'use strict';
	
	const STORAGE_KEYS = {
		orderId: 'cko_flow_order_id',
		orderKey: 'cko_flow_order_key',
		saveCard: 'cko_flow_save_card'
	};
	
	window.FlowSessionStorage = {
		getOrderId: function() {
			return sessionStorage.getItem(STORAGE_KEYS.orderId);
		},
		setOrderId: function(orderId) {
			if (orderId) {
				sessionStorage.setItem(STORAGE_KEYS.orderId, orderId);
			}
		},
		clearOrderId: function() {
			sessionStorage.removeItem(STORAGE_KEYS.orderId);
		},
		getOrderKey: function() {
			return sessionStorage.getItem(STORAGE_KEYS.orderKey);
		},
		setOrderKey: function(orderKey) {
			if (orderKey) {
				sessionStorage.setItem(STORAGE_KEYS.orderKey, orderKey);
			}
		},
		clearOrderKey: function() {
			sessionStorage.removeItem(STORAGE_KEYS.orderKey);
		},
		getSaveCard: function() {
			return sessionStorage.getItem(STORAGE_KEYS.saveCard);
		},
		setSaveCard: function(value) {
			if (value != null) {
				sessionStorage.setItem(STORAGE_KEYS.saveCard, value);
			}
		},
		clearSaveCard: function() {
			sessionStorage.removeItem(STORAGE_KEYS.saveCard);
		},
		clearOrderData: function() {
			sessionStorage.removeItem(STORAGE_KEYS.orderId);
			sessionStorage.removeItem(STORAGE_KEYS.orderKey);
		}
	};
	
	if (typeof window.ckoLogger !== 'undefined' && window.ckoLogger.debugEnabled) {
		window.ckoLogger.debug('[FlowSessionStorage] Module loaded');
	}
})();
