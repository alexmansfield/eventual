/**
 * Eventual Plugin JavaScript
 * 
 * This file contains the JavaScript functionality for the Eventual WordPress plugin.
 */

(function() {
		'use strict';
		
		// Wait for DOM to be ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function() {
				// Small delay to ensure all content is rendered
				setTimeout(stackOverlappingEvents, 100);
				initEventualNavigation();
			});
		} else {
			// Small delay to ensure all content is rendered
			setTimeout(stackOverlappingEvents, 100);
			initEventualNavigation();
		}
})(); 






function stackOverlappingEvents() {
	const eventsContainer = document.querySelector('.eventual-month__events ul');
	if (!eventsContainer) {
		console.log('Eventual: No events container found');
		return;
	}

	// Get all li elements that are events (PHP-generated)
	const events = Array.from(eventsContainer.children).filter(el => 
		el.classList.contains('eventual-month__event')
	);
	
	console.log('Eventual: Found', events.length, 'events');
	events.forEach((event, index) => {
		console.log('Event', index + 1, ':', {
			classes: event.className,
			style: event.getAttribute('style'),
			text: event.textContent.trim()
		});
	});

	if (events.length === 0) return;

	// Get grid positioning data for each event
	const eventData = events.map(event => {
		const styles = window.getComputedStyle(event);
		const inlineStyle = event.getAttribute('style') || '';
		
		// Parse grid-row and grid-column from inline styles
		let gridRowStart = 1;
		let gridColumnStart = 1;
		let gridColumnEnd = 2;
		
		// Check for inline grid-row style
		const gridRowMatch = inlineStyle.match(/grid-row:\s*(\d+)/);
		if (gridRowMatch) {
			gridRowStart = parseInt(gridRowMatch[1]);
		} else {
			// Fallback to computed styles
			gridRowStart = parseInt(styles.gridRowStart) || 1;
		}
		
		// Check for inline grid-column style
		const gridColumnMatch = inlineStyle.match(/grid-column:\s*(\d+)\s*\/\s*span\s*(\d+)/);
		if (gridColumnMatch) {
			gridColumnStart = parseInt(gridColumnMatch[1]);
			gridColumnEnd = gridColumnStart + parseInt(gridColumnMatch[2]);
		} else {
			// Fallback to computed styles
			gridColumnStart = parseInt(styles.gridColumnStart) || 1;
			gridColumnEnd = parseInt(styles.gridColumnEnd) || gridColumnStart + 1;
		}
		
		console.log('Event data parsed:', {
			text: event.textContent.trim(),
			gridRowStart,
			gridColumnStart,
			gridColumnEnd,
			inlineStyle
		});
		
		return {
			element: event,
			gridColumnStart,
			gridColumnEnd,
			gridRowStart,
			originalGridRowStart: gridRowStart
		};
	});

	// Group events by row
	const eventsByRow = {};
	eventData.forEach(event => {
		const row = event.gridRowStart;
		if (!eventsByRow[row]) {
			eventsByRow[row] = [];
		}
		eventsByRow[row].push(event);
	});

	// Process each row to detect and resolve overlaps
	Object.keys(eventsByRow).forEach(row => {
		const rowEvents = eventsByRow[row];
		console.log('Processing row', row, 'with', rowEvents.length, 'events');
		if (rowEvents.length <= 1) return; // No overlaps possible

		// Sort events by column start position, then by width (narrower first)
		rowEvents.sort((a, b) => {
			// First sort by start column
			if (a.gridColumnStart !== b.gridColumnStart) {
				return a.gridColumnStart - b.gridColumnStart;
			}
			// If they start at the same column, sort by width (narrower events first)
			const widthA = a.gridColumnEnd - a.gridColumnStart;
			const widthB = b.gridColumnEnd - b.gridColumnStart;
			return widthA - widthB;
		});

		// Detect overlaps and assign stack levels
		const stackedEvents = [];
		
		rowEvents.forEach(currentEvent => {
			let stackLevel = 0;
			let foundLevel = false;

			// Try to find a stack level where this event doesn't overlap
			while (!foundLevel) {
				let hasOverlap = false;
				
				// Check if current event overlaps with any event at this stack level
				for (let existingEvent of stackedEvents) {
					if (existingEvent.stackLevel === stackLevel) {
						// Check for column overlap
						if (currentEvent.gridColumnStart < existingEvent.gridColumnEnd && 
								currentEvent.gridColumnEnd > existingEvent.gridColumnStart) {
							hasOverlap = true;
							break;
						}
					}
				}

				if (!hasOverlap) {
					foundLevel = true;
					currentEvent.stackLevel = stackLevel;
					stackedEvents.push(currentEvent);
				} else {
					stackLevel++;
				}
			}
		});

		// Apply the stacking by adjusting margins and positioning
		const maxStackLevel = Math.max(...stackedEvents.map(e => e.stackLevel));
		console.log('Row', row, 'max stack level:', maxStackLevel, 'stacked events:', stackedEvents.map(e => ({
			text: e.element.textContent.trim(),
			stackLevel: e.stackLevel
		})));
		
		// If we have stacking, we need to expand the row height
		if (maxStackLevel > 0) {
			// Calculate the additional height needed
			const eventHeight = 1.5; // em units
			const eventSpacing = 0.5; // em units between stacked events
			const totalAdditionalHeight = (maxStackLevel * (eventHeight + eventSpacing));
			
			console.log('Expanding row', row, 'by', totalAdditionalHeight, 'em');
			// Expand the current row to accommodate stacked events
			expandRowHeight(eventsContainer, parseInt(row), totalAdditionalHeight);
		}

		// Position each event based on its stack level (reverse the stacking order)
		stackedEvents.forEach(event => {
			const element = event.element;
			const stackLevel = event.stackLevel;
			const maxLevel = Math.max(...stackedEvents.map(e => e.stackLevel));
			const invertedLevel = maxLevel - stackLevel; // Invert so lower stack levels go on top
			
			if (invertedLevel === 0) {
				// Top event (narrower events)
				element.style.alignSelf = 'end';
				element.style.marginBottom = `${0.5 + (maxLevel * 2.5)}em`; // Highest position
				element.style.marginTop = 'auto';
				console.log('Positioning', element.textContent.trim(), 'at top with marginBottom:', `${0.5 + (maxLevel * 2.5)}em`);
			} else {
				// Lower stacked events (wider events go lower)
				element.style.alignSelf = 'end';
				const bottomOffset = 0.5 + ((maxLevel - invertedLevel) * 2.5);
				element.style.marginBottom = `${bottomOffset}em`;
				element.style.marginTop = 'auto';
				console.log('Positioning', element.textContent.trim(), 'at level', invertedLevel, 'with marginBottom:', `${bottomOffset}em`);
			}
		});
	});
}

function expandRowHeight(container, rowIndex, additionalHeight) {
	// Get current CSS custom property for rows
	const currentRows = getComputedStyle(container).getPropertyValue('--rows').trim() || '6';
	const rowHeight = '20vh'; // From your CSS - was 15vh but CSS shows 20vh
	
	console.log('Expanding row height:', {
		rowIndex,
		additionalHeight,
		currentRows,
		rowHeight
	});
	
	// Create a new style rule for this specific row
	const styleId = `expanded-row-${rowIndex}`;
	let styleElement = document.getElementById(styleId);
	
	if (!styleElement) {
		styleElement = document.createElement('style');
		styleElement.id = styleId;
		document.head.appendChild(styleElement);
	}
	
	// Calculate the new minimum height for this row
	const newMinHeight = `calc(${rowHeight} + ${additionalHeight}em)`;
	
	// Use nth-child to target the specific row
	const cssRule = `
		.eventual-month__events ul {
			grid-template-rows: ${Array.from({length: parseInt(currentRows)}, (_, i) => 
				i + 1 === rowIndex ? `minmax(${newMinHeight}, auto)` : rowHeight
			).join(' ')};
		}
	`;
	
	console.log('Generated CSS rule:', cssRule);
	styleElement.textContent = cssRule;
}

	// Also provide a way to manually trigger it
	window.stackOverlappingEvents = stackOverlappingEvents;

/**
 * Initialize AJAX navigation for Eventual calendar
 */
function initEventualNavigation() {
	const navigationLinks = document.querySelectorAll('.eventual-month__navigation-link');
	
	if (navigationLinks.length === 0) {
		console.log('Eventual: No navigation links found');
		return;
	}
	
	navigationLinks.forEach(link => {
		link.addEventListener('click', function(e) {
			e.preventDefault();
			
			const href = this.getAttribute('href');
			const url = new URL(href);
			const month = url.searchParams.get('eventual-m');
			const year = url.searchParams.get('eventual-y');
			
			if (month && year) {
				loadMonthViaAjax(month, year, href);
			}
		});
	});
}

/**
 * Load month content via AJAX
 */
function loadMonthViaAjax(month, year, newUrl) {
	// Show loading state
	const contentContainer = document.querySelector('.eventual-month__content');
	if (contentContainer) {
		contentContainer.style.opacity = '0.5';
		contentContainer.style.transition = 'opacity 0.3s ease';
	}
	
	// Prepare form data
	const formData = new FormData();
	formData.append('action', 'eventual_get_month');
	formData.append('nonce', eventual_ajax.nonce);
	formData.append('month', month);
	formData.append('year', year);
	
	// Make AJAX request
	fetch(eventual_ajax.ajax_url, {
		method: 'POST',
		body: formData
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			// Update the content
			if (contentContainer) {
				contentContainer.innerHTML = data.data.content;
				contentContainer.style.opacity = '1';
			}
			
			// Update the title
			const titleElement = document.querySelector('.eventual-month__title');
			if (titleElement && data.data.month_name && data.data.year_formatted) {
				titleElement.textContent = data.data.month_name + ' ' + data.data.year_formatted;
			}
			
			// Update browser URL without page reload
			window.history.pushState({}, '', newUrl);
			
			// Update navigation links for next AJAX request
			updateNavigationLinks(month, year);
			
			// Re-run event stacking
			setTimeout(stackOverlappingEvents, 100);
			
			console.log('Eventual: Month loaded successfully', data.data);
		} else {
			console.error('Eventual: Failed to load month', data.data);
			// Fallback to regular navigation
			window.location.href = newUrl;
		}
	})
	.catch(error => {
		console.error('Eventual: AJAX error', error);
		// Fallback to regular navigation
		window.location.href = newUrl;
	});
}

/**
 * Update navigation links based on current month/year
 */
function updateNavigationLinks(currentMonth, currentYear) {
	// Calculate previous month
	const prevMonth = getPreviousMonth(currentMonth, currentYear);
	const prevUrl = buildNavigationUrl(prevMonth.month, prevMonth.year);
	
	// Calculate next month
	const nextMonth = getNextMonth(currentMonth, currentYear);
	const nextUrl = buildNavigationUrl(nextMonth.month, nextMonth.year);
	
	// Update the navigation links
	const prevLink = document.querySelector('.eventual-month__navigation-link--previous');
	const nextLink = document.querySelector('.eventual-month__navigation-link--next');
	
	if (prevLink) {
		prevLink.href = prevUrl;
	}
	if (nextLink) {
		nextLink.href = nextUrl;
	}
	
	console.log('Eventual: Navigation links updated', {
		prev: prevUrl,
		next: nextUrl
	});
}

/**
 * Calculate previous month
 */
function getPreviousMonth(month, year) {
	let prevMonth = parseInt(month) - 1;
	let prevYear = parseInt(year);
	
	if (prevMonth === 0) {
		prevMonth = 12;
		prevYear--;
	}
	
	return {
		month: prevMonth.toString().padStart(2, '0'),
		year: prevYear.toString()
	};
}

/**
 * Calculate next month
 */
function getNextMonth(month, year) {
	let nextMonth = parseInt(month) + 1;
	let nextYear = parseInt(year);
	
	if (nextMonth === 13) {
		nextMonth = 1;
		nextYear++;
	}
	
	return {
		month: nextMonth.toString().padStart(2, '0'),
		year: nextYear.toString()
	};
}

/**
 * Build navigation URL with query parameters
 */
function buildNavigationUrl(month, year) {
	const url = new URL(window.location.href);
	url.searchParams.set('eventual-m', month);
	url.searchParams.set('eventual-y', year);
	return url.toString();
}