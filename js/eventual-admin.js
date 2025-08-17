/**
 * Eventual Plugin Admin JavaScript
 * 
 * This file contains the JavaScript functionality for the Eventual WordPress plugin admin area.
 */

(function() {
	'use strict';
	
	// Wait for DOM to be ready
	document.addEventListener('DOMContentLoaded', function() {
		
		// Initialize the admin plugin
		initEventualAdmin();
		
	});
	
	/**
	 * Initialize the Eventual admin plugin
	 */
	function initEventualAdmin() {
		console.log('Eventual admin plugin initialized');
		
		// Add your admin-specific functionality here
		// Example: Handle admin-specific click events
		const adminEventualElements = document.querySelectorAll('.eventual-admin-element');
		adminEventualElements.forEach(function(element) {
			element.addEventListener('click', function(e) {
				e.preventDefault();
				console.log('Eventual admin element clicked');
			});
		});
		
		// Example: Add admin-specific styles dynamically
		document.body.classList.add('eventual-admin-loaded');
		
		// Example: Handle WordPress admin notices
		const adminNotices = document.querySelectorAll('.eventual-admin-notice');
		adminNotices.forEach(function(notice) {
			const closeButton = notice.querySelector('.notice-dismiss');
			if (closeButton) {
				closeButton.addEventListener('click', function() {
					notice.style.display = 'none';
				});
			}
		});
		
	}
	
	/**
	 * Public API for other admin scripts to use
	 */
	window.EventualAdmin = {
		init: initEventualAdmin,
		version: '1.0.0'
	};
	
})(); 