// Market data provided by market-finder.php
const marketFinderData = window.marketFinderData || {};
const marketsData = Array.isArray(marketFinderData.markets) ? marketFinderData.markets : [];
const initialSidebarMarkets = Array.isArray(marketFinderData.marketsWithDistance)
    ? marketFinderData.marketsWithDistance
    : [];
let sidebarMarkets = initialSidebarMarkets.map(market => ({ ...market }));
let map;
let markers = [];
let userLocationMarker = null;
let userLocation = null;
let currentInfoWindow = null; // Track the currently open info window
const defaultMarkerIcon = { url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png' };
const highlightedMarkerIcon = { url: 'https://maps.google.com/mapfiles/ms/icons/yellow-dot.png' };

/** SPA Market Finder (app/markets) — avoids legacy market-finder.php hub */
function buildSpaMarketsUrl(marketId, userLat, userLng) {
    const q = new URLSearchParams();
    if (marketId != null && marketId !== '') {
        q.set('market', String(marketId));
    }
    if (userLat != null && userLng != null && !Number.isNaN(+userLat) && !Number.isNaN(+userLng)) {
        q.set('lat', String(userLat));
        q.set('lng', String(userLng));
    }
    const qs = q.toString();
    return `app/markets${qs ? `?${qs}` : ''}`;
}
let currentNearestMarkerId = null;
let latestNearestMarketId = null;

function getOrdinalLabel(rank) {
    const mod100 = rank % 100;
    if (mod100 >= 11 && mod100 <= 13) {
        return `${rank}TH`;
    }
    switch (rank % 10) {
        case 1: return `${rank}ST`;
        case 2: return `${rank}ND`;
        case 3: return `${rank}RD`;
        default: return `${rank}TH`;
    }
}

function buildNearestBadge(rank) {
    if (!rank || rank > 3) {
        return '';
    }
    const label = rank === 1 ? 'NEAREST' : `${getOrdinalLabel(rank)} NEAREST`;
    const badgeClass = rank === 1 ? 'nearest-badge primary' : 'nearest-badge secondary';
    return `<span class="${badgeClass}">${label}</span>`;
}

function calculateMarketDistanceValue(market) {
    const lat = parseFloat(market.latitude);
    const lng = parseFloat(market.longitude);
    if (userLocation && !Number.isNaN(lat) && !Number.isNaN(lng)) {
        return calculateDistance(userLocation.lat, userLocation.lng, lat, lng);
    }
    if (typeof market.distance !== 'undefined' && market.distance !== null) {
        const numeric = parseFloat(String(market.distance).replace(/[^\d.]/g, ''));
        if (!Number.isNaN(numeric)) {
            return numeric;
        }
    }
    return null;
}

function highlightNearestMarker(nearestMarketId) {
    if (!nearestMarketId || markers.length === 0) {
        currentNearestMarkerId = null;
        markers.forEach(marker => {
            marker.setIcon(defaultMarkerIcon);
            marker.setZIndex(undefined);
            if (marker.getAnimation() !== null) {
                marker.setAnimation(null);
            }
        });
        return;
    }
    const nearestIdStr = String(nearestMarketId);
    if (currentNearestMarkerId === nearestIdStr) {
        return;
    }
    currentNearestMarkerId = nearestIdStr;
    markers.forEach(marker => {
        const markerIdStr = marker.marketId !== undefined && marker.marketId !== null
            ? String(marker.marketId)
            : null;
        if (markerIdStr === nearestIdStr) {
            marker.setIcon(highlightedMarkerIcon);
            marker.setZIndex(google.maps.Marker.MAX_ZINDEX ? google.maps.Marker.MAX_ZINDEX + 1 : 999);
            marker.setAnimation(google.maps.Animation.BOUNCE);
            setTimeout(() => {
                if (marker.getAnimation() !== null) {
                    marker.setAnimation(null);
                }
            }, 1400);
        } else {
            marker.setIcon(defaultMarkerIcon);
            marker.setZIndex(undefined);
            if (marker.getAnimation() !== null) {
                marker.setAnimation(null);
            }
        }
    });
}
function exploreMarkets() {
    const marketsSection = document.getElementById('marketsSection');
    if (marketsSection) {
        // Show the section
        marketsSection.classList.add('visible');
        
        // Remove footer margin to eliminate gap
        const footer = document.querySelector('.mf-footer');
        if (footer) {
            footer.style.marginTop = '0';
        }
        
        // Scroll to section after a brief delay to allow animation
        setTimeout(() => {
            marketsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    }
}

// Dark theme map styles - Using #333333 color
const darkMapStyle = [
    { elementType: "geometry", stylers: [{ color: "#333333" }] },
    { elementType: "labels.text.fill", stylers: [{ color: "#e5e5e5" }] },
    { elementType: "labels.icon", stylers: [{ visibility: "on" }] },
    { elementType: "labels.icon", stylers: [{ visibility: "on" }] },
    { elementType: "labels.text.stroke", stylers: [{ color: "#333333" }] },
    {
        featureType: "administrative",
        elementType: "geometry",
        stylers: [{ color: "#4a4a4a" }]
    },
    {
        featureType: "administrative.country",
        elementType: "labels.text.fill",
        stylers: [{ color: "#d0d0d0" }]
    },
    {
        featureType: "administrative.land_parcel",
        stylers: [{ visibility: "off" }]
    },
    {
        featureType: "administrative.locality",
        elementType: "labels.text.fill",
        stylers: [{ color: "#e0e0e0" }]
    },
    {
        featureType: "poi",
        elementType: "labels.text.fill",
        stylers: [{ color: "#f0f0f0" }]
    },
    {
        featureType: "poi",
        elementType: "labels.icon",
        stylers: [{ visibility: "on" }]
    },
    {
        featureType: "poi.park",
        elementType: "geometry",
        stylers: [{ color: "#2d2d2d" }]
    },
    {
        featureType: "poi.park",
        elementType: "labels.text.fill",
        stylers: [{ color: "#9e9e9e" }]
    },
    {
        featureType: "poi.park",
        elementType: "labels.text.stroke",
        stylers: [{ color: "#333333" }]
    },
    {
        featureType: "road",
        elementType: "geometry.fill",
        stylers: [{ color: "#404040" }]
    },
    {
        featureType: "road",
        elementType: "geometry.stroke",
        stylers: [{ color: "#4a4a4a" }]
    },
    {
        featureType: "road",
        elementType: "labels.text.fill",
        stylers: [{ color: "#d0d0d0" }]
    },
    {
        featureType: "road.arterial",
        elementType: "geometry",
        stylers: [{ color: "#4a4a4a" }]
    },
    {
        featureType: "road.highway",
        elementType: "geometry",
        stylers: [{ color: "#5a5a5a" }]
    },
    {
        featureType: "road.highway.controlled_access",
        elementType: "geometry",
        stylers: [{ color: "#6a6a6a" }]
    },
    {
        featureType: "road.local",
        elementType: "geometry",
        stylers: [{ color: "#404040" }]
    },
    {
        featureType: "road.local",
        elementType: "labels.text.fill",
        stylers: [{ color: "#b3b3b3" }]
    },
    {
        featureType: "transit",
        elementType: "labels.text.fill",
        stylers: [{ color: "#b3b3b3" }]
    },
    {
        featureType: "water",
        elementType: "geometry",
        stylers: [{ color: "#1a1a1a" }]
    },
    {
        featureType: "water",
        elementType: "labels.text.fill",
        stylers: [{ color: "#6a6a6a" }]
    }
];

// Initialize Google Map
function initMap() {
    // Default center (Balaoan, La Union)
    const defaultCenter = { lat: 16.8219, lng: 120.4042 };
    
    // Only initialize if map element exists (it's hidden in modal)
    const mapElement = document.getElementById('marketsMap');
    if (mapElement) {
        map = new google.maps.Map(mapElement, {
            zoom: 11,
            center: defaultCenter,
            mapTypeControl: true,
            streetViewControl: true,
            fullscreenControl: true,
            styles: darkMapStyle, // Apply dark theme
            backgroundColor: '#1a1a1a'
        });
        
        // Add market markers initially
        addMarketMarkers();
        
        // Select first market
        if (marketsData && marketsData.length > 0) {
            sortedMarkets = marketsData.map(m => ({ ...m }));
            // First market is already active in HTML, just center map on it
            const firstMarket = marketsData[0];
            if (map) {
                map.setCenter({ 
                    lat: parseFloat(firstMarket.latitude), 
                    lng: parseFloat(firstMarket.longitude) 
                });
                map.setZoom(12);
                // Trigger marker click to show info window
                setTimeout(() => {
                    const marker = markers.find(m => {
                        const pos = m.getPosition();
                        return Math.abs(pos.lat() - parseFloat(firstMarket.latitude)) < 0.001 &&
                               Math.abs(pos.lng() - parseFloat(firstMarket.longitude)) < 0.001;
                    });
                    if (marker) {
                        google.maps.event.trigger(marker, 'click');
                    }
                }, 500);
            }
        }
    }
}

function buildMapInfoCard({
    title,
    address,
    hours,
    distance,
    sections = [],
    meta = [],
    actionUrl = '#',
    actionLabel = 'View Market'
}) {
    const safeTitle = title || 'Market';
    const safeAddress = address || 'Address unavailable';
    const safeHours = hours || 'Monday-Sunday: 5:00 AM - 8:00 PM';
    const distanceHtml = distance ? `<div class="map-info-distance">${distance}</div>` : '';
    
    const sectionsHtml = sections.map(section => `
        <div class="map-info-section">
            <span class="map-info-label">${section.label}</span>
            ${section.content}
        </div>
    `).join('');
    
    const metaHtml = meta.length ? `
        <div class="map-info-meta">
            ${meta.map(item => `
                <div class="map-info-meta-item">
                    <span class="map-info-label">${item.label}</span>
                    <span class="${item.className || 'map-info-value'}">${item.value}</span>
                </div>
            `).join('')}
        </div>
    ` : '';
    
    const hasBody = Boolean(distanceHtml || sectionsHtml || metaHtml);
    
    return `
        <div class="map-info-card">
            <div class="map-info-header">
                <h3 class="map-info-title">${safeTitle}</h3>
                <p class="map-info-subtitle">${safeAddress}</p>
                <p class="map-info-hours">${safeHours}</p>
            </div>
            ${distanceHtml}
            ${hasBody ? '<div class="map-info-divider"></div>' : ''}
            ${sectionsHtml}
            ${metaHtml}
            <a class="map-info-button" href="${actionUrl}">${actionLabel}</a>
        </div>
    `;
}

// Add market markers to map
function addMarketMarkers(userLat = null, userLng = null) {
    // Clear existing markers
    markers.forEach(marker => marker.setMap(null));
    markers = [];
    
    marketsData.forEach(market => {
        const marker = new google.maps.Marker({
            position: { lat: parseFloat(market.latitude), lng: parseFloat(market.longitude) },
            map: map,
            title: market.market_name,
            icon: defaultMarkerIcon
        });
        
        marker.marketId = market.id;
        
        // Calculate distance if user location is available
        if (userLat && userLng) {
            const distance = calculateDistance(
                userLat, userLng,
                parseFloat(market.latitude), parseFloat(market.longitude)
            );
            market.distance = parseFloat(distance.toFixed(2));
        }
        marker.addListener('click', () => {
            // Close any currently open info window first
            if (currentInfoWindow) {
                currentInfoWindow.close();
                currentInfoWindow = null;
            }
            
            // Show market info (this will create and open a new info window)
            const marketWithDistance = { ...market };
            if (typeof market.distance !== 'undefined') {
                marketWithDistance.distance = market.distance;
            }
            showMarketInfo(marketWithDistance, userLat, userLng);
            
            // Update sidebar active state by market ID
            const marketIdStr = market.id !== undefined && market.id !== null ? String(market.id) : null;
            const sidebarIndex = marketIdStr
                ? sidebarMarkets.findIndex(item => String((item.id ?? item.market_id)) === marketIdStr)
                : -1;
            document.querySelectorAll('.map-market-item').forEach((item, idx) => {
                const isActive = sidebarIndex >= 0 ? idx === sidebarIndex : false;
                item.classList.toggle('active', isActive);
            });
            if (sidebarIndex >= 0) {
                const selectedItem = document.querySelector(`[data-market-index="${sidebarIndex}"]`);
                if (selectedItem) {
                    selectedItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        });
        
        markers.push(marker);
    });
}

// Calculate distance between two coordinates
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in kilometers
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return Math.round(R * c * 100) / 100; // Round to 2 decimal places
}

// Find nearest markets using geolocation
function findNearestMarkets() {
    // Check if geolocation is supported
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser. Please use a modern browser or manually select a market.');
        return;
    }
    
    // Show loading message
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = 'Getting your location...';
    button.disabled = true;
    
    // Get user's current position
    navigator.geolocation.getCurrentPosition(
        function(position) {
            // Success: Get coordinates
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            userLocation = { lat, lng };
            
            // Show map modal
            showMapModal(lat, lng);
            
            // Reset button
            button.innerHTML = originalText;
            button.disabled = false;
        },
        function(error) {
            // Error: Show message
            button.innerHTML = originalText;
            button.disabled = false;
            
            let errorMessage = 'Unable to get your location. ';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage += 'Please allow location access to find nearest markets.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage += 'Location information is unavailable.';
                    break;
                case error.TIMEOUT:
                    errorMessage += 'Location request timed out.';
                    break;
                default:
                    errorMessage += 'An unknown error occurred.';
                    break;
            }
            alert(errorMessage);
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

// Show map modal with user location and markets
function showMapModal(userLat, userLng) {
    // Show modal first
    const mapModal = document.getElementById('mapModal');
    mapModal.style.display = 'block';
    
    // Force reflow to ensure initial state is applied
    void mapModal.offsetWidth;
    
    // Initialize map if not already initialized
    if (!map) {
        const defaultCenter = { lat: 16.8219, lng: 120.4042 };
        map = new google.maps.Map(document.getElementById('marketsMap'), {
            zoom: 11,
            center: defaultCenter,
            mapTypeControl: true,
            streetViewControl: true,
            fullscreenControl: true,
            styles: darkMapStyle, // Apply dark theme
            backgroundColor: '#1a1a1a'
        });
        
        // Add market markers (will be updated with distances later)
        addMarketMarkers();
    }
    
    // Add active class after a tiny delay to trigger animation
    setTimeout(() => {
        mapModal.classList.add('active');
        
        // Wait for slide animation, then center on user location
        setTimeout(() => {
            if (map) {
                map.setCenter({ lat: userLat, lng: userLng });
                map.setZoom(13);
                
                // Add user location marker
                if (userLocationMarker) {
                    userLocationMarker.setMap(null);
                }
                
                userLocationMarker = new google.maps.Marker({
                    position: { lat: userLat, lng: userLng },
                    map: map,
                    title: 'Your Location',
                    icon: {
                        url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                        scaledSize: new google.maps.Size(40, 40)
                    },
                    zIndex: 1000
                });
                
                // Add user location info window
                const userInfoWindow = new google.maps.InfoWindow({
                    content: '<div style="padding: 0.5rem;"><strong>Your Location</strong></div>'
                });
                userInfoWindow.open(map, userLocationMarker);
                
                // Calculate distances and update markers and list
                updateMapMarkers(userLat, userLng);
            }
        }, 500);
    }, 10);
}

// Store sorted markets for navigation
let sortedMarkets = [];
let currentMarketIndex = 0;

// Update map with markets sorted by distance
function updateMapMarkers(userLat, userLng) {
    // Calculate distances and sort markets
    sortedMarkets = marketsData.map(market => {
        const distance = calculateDistance(
            userLat, userLng,
            parseFloat(market.latitude), parseFloat(market.longitude)
        );
        return { ...market, distance };
    }).sort((a, b) => a.distance - b.distance);
    latestNearestMarketId = sortedMarkets.length > 0 ? sortedMarkets[0].id : null;
    
    // Recreate markers with updated info windows that include distance
    addMarketMarkers(userLat, userLng);
    highlightNearestMarker(latestNearestMarketId);
    
    // Rebuild sidebar list using the newly sorted markets
    if (sortedMarkets.length > 0) {
        restoreOriginalMarketsList();
    }
}

// Select market from sidebar
function selectMarketFromSidebar(index) {
    if (!sidebarMarkets || index < 0 || index >= sidebarMarkets.length) {
        return;
    }
    
    const listMarket = sidebarMarkets[index];
    if (!listMarket) return;
    
    const marketId = listMarket.id !== undefined ? listMarket.id : listMarket.market_id;
    const marketIdStr = marketId !== undefined && marketId !== null ? String(marketId) : null;
    
    let market = null;
    if (marketIdStr) {
        market = marketsData.find(m => String(m.id) === marketIdStr);
    }
    if (!market) {
        market = listMarket;
    }
    
    const marketForInfo = { ...market };
    if (typeof listMarket.distance !== 'undefined') {
        marketForInfo.distance = listMarket.distance;
    }
    
    const userLat = userLocation ? userLocation.lat : null;
    const userLng = userLocation ? userLocation.lng : null;
    
    if (currentInfoWindow) {
        currentInfoWindow.close();
        currentInfoWindow = null;
    }
    
    document.querySelectorAll('.map-market-item').forEach((item, i) => {
        item.classList.toggle('active', i === index);
    });
    
    if (map) {
        map.setCenter({ 
            lat: parseFloat(market.latitude), 
            lng: parseFloat(market.longitude) 
        });
        map.setZoom(userLat && userLng ? 15 : 13);
        showMarketInfo(marketForInfo, userLat, userLng);
    }
    
    const selectedItem = document.querySelector(`[data-market-index="${index}"]`);
    if (selectedItem) {
        selectedItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    currentMarketIndex = index;
}

// Check if market is currently open based on operating hours
function isMarketOpen(operatingHours) {
    if (!operatingHours) return true; // Default to open if no hours specified
    
    const now = new Date();
    const currentTime = now.getHours() * 100 + now.getMinutes(); // HHMM format
    
    // Parse operating hours (e.g., "Monday-Sunday: 5:00 AM - 8:00 PM")
    const hoursStr = operatingHours.toLowerCase();
    
    // Check for "closed" keywords
    if (hoursStr.includes('closed') || hoursStr.includes('close')) {
        return false;
    }
    
    // Try to extract time range
    const timeMatch = hoursStr.match(/(\d{1,2}):?(\d{2})?\s*(am|pm)\s*-\s*(\d{1,2}):?(\d{2})?\s*(am|pm)/i);
    if (timeMatch) {
        let openHour = parseInt(timeMatch[1]);
        const openMin = parseInt(timeMatch[2] || '0');
        const openPeriod = timeMatch[3].toLowerCase();
        let closeHour = parseInt(timeMatch[4]);
        const closeMin = parseInt(timeMatch[5] || '0');
        const closePeriod = timeMatch[6].toLowerCase();
        
        // Convert to 24-hour format
        if (openPeriod === 'pm' && openHour !== 12) openHour += 12;
        if (openPeriod === 'am' && openHour === 12) openHour = 0;
        if (closePeriod === 'pm' && closeHour !== 12) closeHour += 12;
        if (closePeriod === 'am' && closeHour === 12) closeHour = 0;
        
        const openTime = openHour * 100 + openMin;
        const closeTime = closeHour * 100 + closeMin;
        const currentTime24 = now.getHours() * 100 + now.getMinutes();
        
        // Handle overnight hours (e.g., 10 PM - 2 AM)
        if (closeTime < openTime) {
            return currentTime24 >= openTime || currentTime24 <= closeTime;
        } else {
            return currentTime24 >= openTime && currentTime24 <= closeTime;
        }
    }
    
    // Default to open if we can't parse
    return true;
}

// Show market info in floating card and info window
function showMarketInfo(market, userLat, userLng) {
    if (!map) return;
    
    // Close any currently open info window
    if (currentInfoWindow) {
        currentInfoWindow.close();
        currentInfoWindow = null;
    }
    
    // Also show info window on map
    const marketUrl = buildSpaMarketsUrl(market.id, userLat, userLng);
    const productCount = market.product_count || 0;
    const isOpen = isMarketOpen(market.operating_hours);
    
    let distanceText = '';
    if (userLat && userLng) {
        const distance = calculateDistance(
            userLat, userLng,
            parseFloat(market.latitude), parseFloat(market.longitude)
        );
        distanceText = `${distance.toFixed(2)} km away`;
        market.distance = parseFloat(distance.toFixed(2));
    } else if (market.distance !== undefined && market.distance !== null) {
        const numericDistance = parseFloat(market.distance);
        if (!Number.isNaN(numericDistance)) {
            distanceText = `${numericDistance.toFixed(2)} km away`;
        } else if (typeof market.distance === 'string') {
            distanceText = market.distance;
        }
    }
    
    const infoContent = buildMapInfoCard({
        title: market.market_name,
        address: market.address,
        hours: market.operating_hours,
        distance: distanceText,
        meta: [
            { label: 'Products', value: productCount },
            { label: 'Status', value: isOpen ? 'Open' : 'Closed', className: `map-info-status ${isOpen ? 'open' : 'closed'}` }
        ],
        actionUrl: marketUrl
    });
    
    const infoWindow = new google.maps.InfoWindow({
        content: infoContent
    });
    
    // Store reference to current info window
    currentInfoWindow = infoWindow;
    
    infoWindow.setPosition({ lat: parseFloat(market.latitude), lng: parseFloat(market.longitude) });
    infoWindow.open(map);
    
    // Clear reference when info window is closed
    infoWindow.addListener('closeclick', function() {
        currentInfoWindow = null;
    });
}

// Navigate between market markers
function navigateMapMarkers(direction) {
    const marketsToUse = sortedMarkets.length > 0 ? sortedMarkets : marketsData;
    if (marketsToUse.length === 0) return;
    
    currentMarketIndex += direction;
    if (currentMarketIndex < 0) currentMarketIndex = marketsToUse.length - 1;
    if (currentMarketIndex >= marketsToUse.length) currentMarketIndex = 0;
    
    const market = marketsToUse[currentMarketIndex];
    if (market && map) {
        map.setCenter({ lat: parseFloat(market.latitude), lng: parseFloat(market.longitude) });
        map.setZoom(15);
        
        // Update location card
        const userLat = userLocation ? userLocation.lat : null;
        const userLng = userLocation ? userLocation.lng : null;
        showMarketInfo(market, userLat, userLng);
        
        // Find and trigger marker click
        const marker = markers.find(m => {
            const pos = m.getPosition();
            return Math.abs(pos.lat() - parseFloat(market.latitude)) < 0.001 &&
                   Math.abs(pos.lng() - parseFloat(market.longitude)) < 0.001;
        });
        if (marker) {
            google.maps.event.trigger(marker, 'click');
        }
    }
}

// Close map modal
function closeMapModal() {
    const mapModal = document.getElementById('mapModal');
    mapModal.classList.remove('active');
    
    // Hide modal after animation completes
    setTimeout(() => {
        mapModal.style.display = 'none';
    }, 800);
}

// Product Search Variables
let searchTimeout = null;
let currentSearchResults = null;
let isSearchActive = false;
let originalMarketsData = [...marketsData];

function escapeHtml(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/* ── Market map: product autocomplete (market-map.php) ── */
let mapAutocompleteTimer = null;
let mapAutocompleteSeq = 0;
let mapAutocompleteItems = [];
let mapAutocompleteActiveIndex = -1;

function mapAutocompleteEl() {
    return document.getElementById('productSearchAutocomplete');
}

function hideMapProductAutocomplete() {
    const dd = mapAutocompleteEl();
    if (!dd) return;
    dd.hidden = true;
    dd.innerHTML = '';
    mapAutocompleteItems = [];
    mapAutocompleteActiveIndex = -1;
    const input = document.getElementById('productSearchInput');
    if (input) {
        input.setAttribute('aria-expanded', 'false');
    }
}

function updateMapAutocompleteHighlight() {
    const dd = mapAutocompleteEl();
    if (!dd) return;
    const buttons = dd.querySelectorAll('.map-product-autocomplete__item');
    buttons.forEach((btn, i) => {
        const on = i === mapAutocompleteActiveIndex;
        btn.classList.toggle('is-active', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
}

function renderMapProductAutocomplete(suggestions) {
    const dd = mapAutocompleteEl();
    const input = document.getElementById('productSearchInput');
    if (!dd || !input || !suggestions.length) {
        hideMapProductAutocomplete();
        return;
    }
    mapAutocompleteItems = suggestions;
    mapAutocompleteActiveIndex = 0;
    dd.innerHTML = suggestions
        .map((s, i) => {
            const name = escapeHtml(s.product_name);
            const parts = [];
            if (s.min_price != null && !Number.isNaN(Number(s.min_price))) {
                parts.push(
                    'from ₱' + Number(s.min_price).toFixed(2) + '/' + escapeHtml(s.unit || 'kg')
                );
            }
            if (s.market_count > 0) {
                parts.push(s.market_count + ' market' + (s.market_count === 1 ? '' : 's'));
            }
            if (s.category) {
                parts.push(escapeHtml(s.category));
            }
            const meta = parts.length
                ? '<span class="map-product-autocomplete__meta">' + parts.join(' · ') + '</span>'
                : '';
            return (
                '<button type="button" class="map-product-autocomplete__item" role="option" ' +
                'data-index="' +
                i +
                '" id="map-ac-opt-' +
                i +
                '">' +
                '<span class="map-product-autocomplete__name">' +
                name +
                '</span>' +
                meta +
                '</button>'
            );
        })
        .join('');
    dd.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    updateMapAutocompleteHighlight();

    dd.querySelectorAll('.map-product-autocomplete__item').forEach((btn) => {
        btn.addEventListener('mouseenter', function () {
            const idx = parseInt(btn.getAttribute('data-index'), 10);
            if (!Number.isNaN(idx)) {
                mapAutocompleteActiveIndex = idx;
                updateMapAutocompleteHighlight();
            }
        });
        btn.addEventListener('mousedown', function (ev) {
            ev.preventDefault();
        });
        btn.addEventListener('click', function () {
            const idx = parseInt(btn.getAttribute('data-index'), 10);
            const item = mapAutocompleteItems[idx];
            if (item && item.product_name) {
                applyMapAutocompleteSelection(item.product_name);
            }
        });
    });
}

function scheduleMapProductAutocomplete(rawQuery) {
    const dd = mapAutocompleteEl();
    if (!dd) return;
    const q = (rawQuery || '').trim();
    if (mapAutocompleteTimer) {
        clearTimeout(mapAutocompleteTimer);
        mapAutocompleteTimer = null;
    }
    if (q.length < 2) {
        hideMapProductAutocomplete();
        return;
    }
    mapAutocompleteTimer = setTimeout(function () {
        mapAutocompleteTimer = null;
        fetchMapProductAutocomplete(q);
    }, 170);
}

function fetchMapProductAutocomplete(q) {
    const seq = ++mapAutocompleteSeq;
    fetch('api/search-map-product-autocomplete.php?q=' + encodeURIComponent(q) + '&limit=12')
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            if (seq !== mapAutocompleteSeq) return;
            const input = document.getElementById('productSearchInput');
            if (!input || input.value.trim().length < 2) {
                hideMapProductAutocomplete();
                return;
            }
            const list = data && Array.isArray(data.suggestions) ? data.suggestions : [];
            if (!list.length) {
                hideMapProductAutocomplete();
                return;
            }
            renderMapProductAutocomplete(list);
        })
        .catch(function () {
            if (seq === mapAutocompleteSeq) {
                hideMapProductAutocomplete();
            }
        });
}

function applyMapAutocompleteSelection(productName) {
    hideMapProductAutocomplete();
    if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }
    const input = document.getElementById('productSearchInput');
    const clearBtn = document.getElementById('productSearchClear');
    if (input) {
        input.value = productName;
    }
    if (clearBtn) {
        clearBtn.classList.add('visible');
    }
    performProductSearch(productName);
}

function mapAutocompleteHandleArrow(delta) {
    const dd = mapAutocompleteEl();
    if (!dd || dd.hidden || !mapAutocompleteItems.length) return false;
    const n = mapAutocompleteItems.length;
    let next = mapAutocompleteActiveIndex + delta;
    if (next < 0) next = n - 1;
    if (next >= n) next = 0;
    mapAutocompleteActiveIndex = next;
    updateMapAutocompleteHighlight();
    const btn = dd.querySelector('#map-ac-opt-' + next);
    if (btn && typeof btn.scrollIntoView === 'function') {
        btn.scrollIntoView({ block: 'nearest' });
    }
    return true;
}

function initMapProductAutocomplete() {
    if (!mapAutocompleteEl()) return;
    document.addEventListener(
        'mousedown',
        function (e) {
            const wrap = e.target.closest && e.target.closest('.product-search-container');
            if (!wrap) {
                hideMapProductAutocomplete();
            }
        },
        true
    );
}

// Product Search Function
function searchProductMarkets(query) {
    if (!query || query.trim().length < 2) {
        clearProductSearch();
        return;
    }
    
    // Clear previous timeout
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    // Debounce search (wait 300ms after user stops typing)
    searchTimeout = setTimeout(() => {
        performProductSearch(query.trim());
    }, 300);
}

// Perform the actual search
function performProductSearch(query) {
    const searchInput = document.getElementById('productSearchInput');
    const clearBtn = document.getElementById('productSearchClear');
    
    // Show loading state
    searchInput.style.opacity = '0.6';
    
    // Fetch markets with product
    fetch(`api/search-product-markets.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            searchInput.style.opacity = '1';
            
            if (data.error) {
                console.error('Search error:', data.error);
                showNoResults(query);
                return;
            }
            
            if (data.markets && data.markets.length > 0) {
                currentSearchResults = data;
                isSearchActive = true;
                clearBtn.classList.add('visible');
                updateMarketsListWithProduct(data.markets, data.cheapest_price, query);
                updateMapMarkersWithProduct(data.markets, query);
            } else {
                showNoResults(query);
            }
        })
        .catch(error => {
            console.error('Search failed:', error);
            searchInput.style.opacity = '1';
            showNoResults(query);
        });
}

// Update markets list with product search results
function updateMarketsListWithProduct(markets, cheapestPrice, productName) {
    const marketsList = document.getElementById('mapMarketsList');
    marketsList.innerHTML = '';
    
    if (markets.length === 0) {
        showNoResults(productName);
        return;
    }
    
    const marketsWithDistance = markets.map((market, index) => {
        const distanceValue = calculateMarketDistanceValue(market);
        return {
            ...market,
            originalIndex: index,
            distanceValue,
            computedId: market.id !== undefined ? market.id : market.market_id
        };
    });
    
    const rankLookup = {};
    let nearestMarketIdForList = null;
    marketsWithDistance
        .filter(item => typeof item.distanceValue === 'number')
        .sort((a, b) => a.distanceValue - b.distanceValue)
        .forEach((item, position) => {
            rankLookup[item.originalIndex] = position + 1;
            if (position === 0) {
                nearestMarketIdForList = item.computedId;
            }
        });
    
    latestNearestMarketId = nearestMarketIdForList || latestNearestMarketId;
    
    marketsWithDistance.forEach((marketData, index) => {
        const marketItem = document.createElement('div');
        const classes = ['map-market-item'];
        if (index === 0) {
            classes.push('active');
        }
        const rank = rankLookup[index];
        if (rank === 1) {
            classes.push('nearest');
        }
        marketItem.className = classes.join(' ');
        marketItem.setAttribute('data-market-index', index);
        if (marketData.computedId !== undefined && marketData.computedId !== null) {
            marketItem.setAttribute('data-market-id', marketData.computedId);
        }
        marketItem.setAttribute('data-product-price', marketData.price);
        marketItem.onclick = () => selectMarketFromSearchResults(index, markets);
        
        // Calculate distance if user location is available
        let distanceHtml = '';
        if (userLocation && typeof marketData.distanceValue === 'number') {
            distanceHtml = `<div class="map-market-distance">${marketData.distanceValue.toFixed(2)} km away</div>`;
        }
        
        // Price badge
        let priceBadge = '';
        if (marketData.is_cheapest) {
            priceBadge = '<span class="price-badge cheapest">CHEAPEST</span>';
        } else if (marketData.price_diff > 0) {
            priceBadge = `<span class="price-badge more-expensive">₱${marketData.price_diff.toFixed(2)} more</span>`;
        }
        
        const badgeHtml = buildNearestBadge(rank);
        const listedAs = escapeHtml(marketData.product_name || productName);
        
        marketItem.innerHTML = `
            <div class="map-market-name">${marketData.market_name}${badgeHtml}</div>
            <div class="map-market-product-line" aria-label="Matched product listing">Product: <span>${listedAs}</span></div>
            <div class="map-market-venue">${marketData.address}</div>
            <div class="map-market-location">${marketData.operating_hours || 'Monday-Sunday: 5:00 AM - 8:00 PM'}</div>
            <div class="map-market-price">
                ₱${parseFloat(marketData.price).toFixed(2)}/${marketData.unit}
                ${priceBadge}
            </div>
            ${distanceHtml}
        `;
        
        marketsList.appendChild(marketItem);
    });
    
    // Select first market
    if (markets.length > 0) {
        selectMarketFromSearchResults(0, markets);
    }
}

// Update map markers with product search results
function updateMapMarkersWithProduct(markets, productName) {
    // Clear all existing markers
    markers.forEach(marker => marker.setMap(null));
    markers = [];
    
    let fallbackNearestId = null;
    if (userLocation) {
        const sortedByDistance = markets
            .map(market => ({
                id: market.market_id ?? market.id,
                distanceValue: calculateMarketDistanceValue(market)
            }))
            .filter(item => typeof item.distanceValue === 'number')
            .sort((a, b) => a.distanceValue - b.distanceValue);
        if (sortedByDistance.length > 0) {
            fallbackNearestId = sortedByDistance[0].id;
        }
    }
    
    // Add markers only for markets with the product
    markets.forEach((market, index) => {
        const marker = new google.maps.Marker({
            position: { 
                lat: parseFloat(market.latitude), 
                lng: parseFloat(market.longitude) 
            },
            map: map,
            title: `${market.market_name} - ${productName}: ₱${market.price}/${market.unit}`,
            animation: google.maps.Animation.DROP,
            icon: defaultMarkerIcon
        });
        marker.marketId = market.market_id ?? market.id;
        
        // Create info window with product price
        const userLat = userLocation ? userLocation.lat : null;
        const userLng = userLocation ? userLocation.lng : null;
        const infoContent = createProductInfoWindow(market, productName, userLat, userLng);
        
        const infoWindow = new google.maps.InfoWindow({
            content: infoContent
        });
        
        marker.addListener('click', () => {
            if (currentInfoWindow) {
                currentInfoWindow.close();
            }
            currentInfoWindow = infoWindow;
            infoWindow.open(map, marker);
            
            // Update sidebar selection
            document.querySelectorAll('.map-market-item').forEach((item, i) => {
                item.classList.toggle('active', i === index);
            });
            
            // Center map on marker
            map.setCenter(marker.getPosition());
            map.setZoom(15);
        });
        
        // Track info window for auto-close
        infoWindow.addListener('closeclick', () => {
            currentInfoWindow = null;
        });
        
        markers.push(marker);
    });
    
    // Fit bounds to show all markers
    if (markets.length > 0) {
        const bounds = new google.maps.LatLngBounds();
        markets.forEach(market => {
            bounds.extend({ 
                lat: parseFloat(market.latitude), 
                lng: parseFloat(market.longitude) 
            });
        });
        
        // If user location is available, include it in bounds
        if (userLocation) {
            bounds.extend(userLocation);
        }
        
        map.fitBounds(bounds);
        
        // Don't zoom in too much if there's only one market
        if (markets.length === 1 && map.getZoom() > 15) {
            map.setZoom(15);
        }
    }
    
    const markerIdToHighlight = latestNearestMarketId || fallbackNearestId;
    if (markerIdToHighlight) {
        highlightNearestMarker(markerIdToHighlight);
    }
}

// Create info window content for product search
function createProductInfoWindow(market, productName, userLat, userLng) {
    let distanceText = '';
    if (userLat && userLng) {
        const distance = calculateDistance(
            userLat, userLng,
            parseFloat(market.latitude), parseFloat(market.longitude)
        );
        distanceText = `${distance.toFixed(2)} km away`;
    }
    
    // Check if market is open
    const isOpen = isMarketOpen(market.operating_hours);
    
    // Price badge
    let priceBadge = '';
    if (market.is_cheapest) {
        priceBadge = '<span class="price-badge cheapest">CHEAPEST</span>';
    } else if (market.price_diff > 0) {
        priceBadge = `<span class="price-badge more-expensive">₱${market.price_diff.toFixed(2)} more</span>`;
    }
    
    const marketUrl = buildSpaMarketsUrl(market.market_id, userLat, userLng);
    
    const productLabel = escapeHtml(market.product_name || productName);
    return buildMapInfoCard({
        title: market.market_name,
        address: market.address,
        hours: market.operating_hours,
        distance: distanceText,
        sections: [
            {
                label: 'Product',
                content: `<span class="map-info-value">${productLabel}</span>`
            },
            {
                label: 'Price',
                content: `
                    <div class="map-info-price-row">
                        <span class="map-info-price">₱${parseFloat(market.price).toFixed(2)}/${market.unit}</span>
                        ${priceBadge}
                    </div>
                `
            }
        ],
        meta: [
            {
                label: 'Status',
                value: isOpen ? 'Open' : 'Closed',
                className: `map-info-status ${isOpen ? 'open' : 'closed'}`
            }
        ],
        actionUrl: marketUrl
    });
}

// Select market from search results
function selectMarketFromSearchResults(index, markets) {
    if (index < 0 || index >= markets.length) return;
    
    const market = markets[index];
    
    // Close any currently open info window
    if (currentInfoWindow) {
        currentInfoWindow.close();
        currentInfoWindow = null;
    }
    
    // Update active state in sidebar
    document.querySelectorAll('.map-market-item').forEach((item, i) => {
        item.classList.toggle('active', i === index);
    });
    
    // Center map on selected market
    if (map) {
        map.setCenter({ 
            lat: parseFloat(market.latitude), 
            lng: parseFloat(market.longitude) 
        });
        map.setZoom(15);
        
        // Find and trigger marker click to show info window
        const marker = markers.find(m => {
            const pos = m.getPosition();
            return Math.abs(pos.lat() - parseFloat(market.latitude)) < 0.001 &&
                   Math.abs(pos.lng() - parseFloat(market.longitude)) < 0.001;
        });
        if (marker) {
            google.maps.event.trigger(marker, 'click');
        }
    }
    
    // Scroll to selected item in sidebar
    const selectedItem = document.querySelector(`[data-market-index="${index}"]`);
    if (selectedItem) {
        selectedItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// Show no results message
function showNoResults(productName) {
    const marketsList = document.getElementById('mapMarketsList');
    const q = escapeHtml(productName);
    marketsList.innerHTML = `
        <div class="no-results-message">
            <h3>No matching listings</h3>
            <p>No active market in the database lists a product matching <strong>“${q}”</strong>.</p>
            <p class="no-results-hint">Try another spelling or a shorter word (e.g. <strong>kangkong</strong>). This search only shows markets that have that product and price in FarmScout.</p>
        </div>
    `;
    
    // Clear all markers
    markers.forEach(marker => marker.setMap(null));
    markers = [];
    sidebarMarkets = [];
}

// Clear product search
function clearProductSearch() {
    const searchInput = document.getElementById('productSearchInput');
    const clearBtn = document.getElementById('productSearchClear');
    if (mapAutocompleteTimer) {
        clearTimeout(mapAutocompleteTimer);
        mapAutocompleteTimer = null;
    }
    mapAutocompleteSeq++;
    hideMapProductAutocomplete();

    if (searchInput) searchInput.value = '';
    if (clearBtn) clearBtn.classList.remove('visible');
    isSearchActive = false;
    currentSearchResults = null;
    
    // Restore original markets list
    restoreOriginalMarketsList();
    restoreOriginalMarkers();
}

// Restore original markets list
function restoreOriginalMarketsList(autoSelect = true) {
    const marketsList = document.getElementById('mapMarketsList');
    marketsList.innerHTML = '';
    
    const marketsToUse = (userLocation && sortedMarkets.length > 0)
        ? sortedMarkets
        : initialSidebarMarkets;
    sidebarMarkets = marketsToUse.map(market => ({ ...market }));
    
    if (sidebarMarkets.length === 0) {
        marketsList.innerHTML = '<div class="no-results-message"><p>No markets available.</p></div>';
        return;
    }
    
    sidebarMarkets.forEach((market, index) => {
        const marketItem = document.createElement('div');
        const classes = ['map-market-item'];
        if (index === 0) {
            classes.push('active');
        }
        const rank = userLocation ? index + 1 : null;
        if (rank === 1) {
            classes.push('nearest');
        }
        marketItem.className = classes.join(' ');
        marketItem.setAttribute('data-market-index', index);
        const marketId = market.id !== undefined ? market.id : market.market_id;
        if (marketId !== undefined) {
            marketItem.setAttribute('data-market-id', marketId);
        }
        marketItem.onclick = () => selectMarketFromSidebar(index);
        
        // Calculate distance if user location is available
        let distanceHtml = '';
        if (userLocation) {
            const distance = market.distance !== undefined 
                ? market.distance 
                : calculateDistance(
                    userLocation.lat, userLocation.lng,
                    parseFloat(market.latitude), parseFloat(market.longitude)
                );
            distanceHtml = `<div class="map-market-distance">${distance.toFixed(2)} km away</div>`;
        }
        
        const badgeHtml = buildNearestBadge(rank);
        
        marketItem.innerHTML = `
            <div class="map-market-name">${market.market_name}${badgeHtml}</div>
            <div class="map-market-venue">${market.address}</div>
            <div class="map-market-location">${market.operating_hours || 'Monday-Sunday: 5:00 AM - 8:00 PM'}</div>
            ${distanceHtml}
        `;
        
        marketsList.appendChild(marketItem);
    });
    
    // Select first market
    if (sidebarMarkets.length > 0 && autoSelect) {
        selectMarketFromSidebar(0);
    }
}

// Restore original markers
function restoreOriginalMarkers() {
    const userLat = userLocation ? userLocation.lat : null;
    const userLng = userLocation ? userLocation.lng : null;
    addMarketMarkers(userLat, userLng);
    if (userLocation && sortedMarkets.length > 0) {
        latestNearestMarketId = sortedMarkets[0].id;
        highlightNearestMarker(latestNearestMarketId);
    }
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const mapModal = document.getElementById('mapModal');
    if (mapModal) {
        mapModal.addEventListener('click', function(e) {
            // Close if clicking on the backdrop (not the container)
            if (e.target === mapModal) {
                closeMapModal();
            }
        });
    }
    
    // Product search input event listener
    const productSearchInput = document.getElementById('productSearchInput');
    const productSearchClear = document.getElementById('productSearchClear');
    
    if (productSearchInput) {
        productSearchInput.setAttribute('autocomplete', 'off');
        if (mapAutocompleteEl()) {
            productSearchInput.setAttribute('aria-autocomplete', 'list');
            productSearchInput.setAttribute('aria-controls', 'productSearchAutocomplete');
            productSearchInput.setAttribute('aria-expanded', 'false');
        }

        productSearchInput.addEventListener('input', function(e) {
            const query = e.target.value.trim();
            
            // Show/hide clear button
            if (query.length > 0) {
                productSearchClear.classList.add('visible');
            } else {
                productSearchClear.classList.remove('visible');
                clearProductSearch();
                return;
            }
            
            scheduleMapProductAutocomplete(e.target.value);
            if (query.length >= 2) {
                searchProductMarkets(query);
            }
        });

        productSearchInput.addEventListener('keydown', function(e) {
            const dd = mapAutocompleteEl();
            const open = dd && !dd.hidden && mapAutocompleteItems.length > 0;

            if (e.key === 'ArrowDown') {
                if (open) {
                    e.preventDefault();
                    mapAutocompleteHandleArrow(1);
                }
                return;
            }
            if (e.key === 'ArrowUp') {
                if (open) {
                    e.preventDefault();
                    mapAutocompleteHandleArrow(-1);
                }
                return;
            }
            if (e.key === 'Escape') {
                if (open) {
                    e.preventDefault();
                    hideMapProductAutocomplete();
                }
                return;
            }
            if (e.key === 'Enter') {
                const query = e.target.value.trim();
                if (open && mapAutocompleteActiveIndex >= 0) {
                    const item = mapAutocompleteItems[mapAutocompleteActiveIndex];
                    if (item && item.product_name) {
                        e.preventDefault();
                        applyMapAutocompleteSelection(item.product_name);
                        return;
                    }
                }
                if (query.length >= 2) {
                    e.preventDefault();
                    hideMapProductAutocomplete();
                    if (searchTimeout) {
                        clearTimeout(searchTimeout);
                        searchTimeout = null;
                    }
                    performProductSearch(query);
                }
            }
        });

        initMapProductAutocomplete();
    }
});

// Prevent back button issues
window.addEventListener('popstate', function(event) {
    goBack();
});

// Scroll-triggered animations
function initScrollAnimations() {
    const animateElements = document.querySelectorAll('.scroll-animate, .scroll-animate-card');
    
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.classList.add('animate-in');
                }, parseFloat(entry.target.dataset.delay || 0) * 1000);
            }
        });
    }, observerOptions);
    
    animateElements.forEach(element => {
        observer.observe(element);
    });
}

// Typewriter animation function
function typewriterEffect(element, text, speed = 50, callback) {
    if (!element) return;
    
    element.textContent = '';
    element.classList.add('typewriter-text');
    
    let i = 0;
    function type() {
        if (i < text.length) {
            element.textContent += text.charAt(i);
            i++;
            setTimeout(type, speed);
        } else {
            element.classList.add('complete');
            if (callback) callback();
        }
    }
    
    type();
}

// Force navbar to be fully transparent
function forceNavbarTransparent() {
    const navbar = document.querySelector('.modern-header');
    const nav = navbar ? navbar.querySelector('nav') : null;
    
    if (navbar) {
        navbar.style.setProperty('background', 'transparent', 'important');
        navbar.style.setProperty('background-color', 'transparent', 'important');
    }
    
    if (nav) {
        nav.style.setProperty('background', 'transparent', 'important');
        nav.style.setProperty('background-color', 'transparent', 'important');
    }
}

// Initialize animations
document.addEventListener('DOMContentLoaded', function() {
    // Force navbar transparent immediately
    forceNavbarTransparent();
    
    // Also force it after a short delay to override any late-loading styles
    setTimeout(forceNavbarTransparent, 100);
    setTimeout(forceNavbarTransparent, 500);
    
    initScrollAnimations();
    
    // Typewriter animation for hero wordmark
    const heroWordmark = document.querySelector('.hero-wordmark');
    if (heroWordmark) {
        const marketSpan = heroWordmark.querySelector('span:first-child');
        const finderSpan = heroWordmark.querySelector('span:last-child');
        
        if (marketSpan && finderSpan) {
            const marketText = marketSpan.textContent;
            const finderText = finderSpan.textContent;
            
            // Start with MARKET
            typewriterEffect(marketSpan, marketText, 60, function() {
                // Then animate FINDER after a short delay
                setTimeout(function() {
                    typewriterEffect(finderSpan, finderText, 60);
                }, 300);
            });
        }
    }
    
    // Market Finder specific: Adjust hero container height when navbar is hidden
    const heroContainer = document.getElementById('heroContainer');
    if (heroContainer) {
        const navbar = document.querySelector('.modern-header');
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    if (navbar.classList.contains('navbar-hidden')) {
                        heroContainer.classList.add('navbar-hidden');
                    } else {
                        heroContainer.classList.remove('navbar-hidden');
                    }
                }
            });
        });
        
        if (navbar) {
            observer.observe(navbar, { attributes: true });
        }
    }
    
    // Show markets section if market is selected (products view)
    const urlParams = new URLSearchParams(window.location.search);
    const marketId = urlParams.get('market');
    
    if (marketId) {
        // Market is selected - show products section
        const marketsSection = document.getElementById('marketsSection');
        if (marketsSection) {
            marketsSection.classList.add('visible');
            const footer = document.querySelector('.mf-footer');
            if (footer) {
                footer.style.marginTop = '0';
            }
            setTimeout(() => {
                marketsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    } else if (urlParams.get('show_markets') === '1') {
        // Auto-show market list if show_markets parameter is present (from find nearest markets)
        // Remove the parameter from URL (clean URL)
        urlParams.delete('show_markets');
        const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        window.history.replaceState({}, '', newUrl);
        
        // Show and scroll to markets section
        setTimeout(() => {
            const marketsSection = document.getElementById('marketsSection');
            if (marketsSection) {
                marketsSection.classList.add('visible');
                const footer = document.querySelector('.mf-footer');
                if (footer) {
                    footer.style.marginTop = '0';
                }
                setTimeout(() => {
                    marketsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        }, 300);
    }
    
    // Show content after a short delay to allow scroll animations to work
    setTimeout(() => {
        const allElements = document.querySelectorAll('.scroll-animate, .scroll-animate-card');
        allElements.forEach((element, index) => {
            // Add animate-in class with staggered delay for elements in viewport
            if (element.getBoundingClientRect().top < window.innerHeight) {
                setTimeout(() => {
                    element.classList.add('animate-in');
                }, index * 50);
            }
        });
    }, 100);
    
    // Hide markets section when scrolling back to hero
    let scrollTimeout;
    const heroContainerForScroll = document.getElementById('heroContainer');
    const marketsSectionForScroll = document.getElementById('marketsSection');
    
    function checkScrollPosition() {
        if (!heroContainerForScroll || !marketsSectionForScroll) return;
        
        // Don't hide if market is selected in URL (products view)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('market')) return;
        
        const heroRect = heroContainerForScroll.getBoundingClientRect();
        const scrollY = window.scrollY || window.pageYOffset;
        
        // If hero is visible (top of hero is at or above viewport top, or we're at the top of the page)
        if (scrollY < 100 || (heroRect.top >= 0 && heroRect.top < window.innerHeight * 0.5)) {
            // Hide markets section
            if (marketsSectionForScroll.classList.contains('visible')) {
                marketsSectionForScroll.classList.remove('visible');
                
                // Restore footer margin
                const footer = document.querySelector('.mf-footer');
                if (footer) {
                    footer.style.marginTop = '';
                }
            }
        }
    }
    
    // Throttled scroll listener
    window.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(checkScrollPosition, 100);
    }, { passive: true });
    
    // Also check on initial load
    checkScrollPosition();
    
    // Quick Filters for Market Finder
    function applyQuickFilter(filterType) {
        const chips = document.querySelectorAll('.filter-chip');
        chips.forEach(chip => chip.classList.remove('active'));
        event.target.classList.add('active');
        
        const products = document.querySelectorAll('.product-card');
        const grid = document.getElementById('products-grid');
        
        if (!grid || !products.length) return;
        
        // Show loading skeleton
        showLoadingSkeletons(grid, products.length);
        
        // Filter products
        setTimeout(() => {
            products.forEach(card => {
                const priceText = card.querySelector('.text-2xl')?.textContent || '';
                const price = parseFloat(priceText.replace(/[^0-9.]/g, ''));
                const category = card.textContent.toLowerCase();
                
                let show = true;
                
                switch(filterType) {
                    case 'under50':
                        show = price < 50;
                        break;
                    case 'under100':
                        show = price < 100;
                        break;
                    case 'vegetables':
                        show = category.includes('gulay') || category.includes('vegetable');
                        break;
                    case 'fruits':
                        show = category.includes('prutas') || category.includes('fruit');
                        break;
                    case 'featured':
                        show = card.querySelector('.bg-blue-600') !== null;
                        break;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
            
            hideLoadingSkeletons();
        }, 300);
    }
    
    // Loading Skeletons
    function showLoadingSkeletons(container, count) {
        if (!container) return;
        const originalContent = container.innerHTML;
        container.innerHTML = '';
        for (let i = 0; i < Math.min(count, 8); i++) {
            const skeleton = document.createElement('div');
            skeleton.className = 'skeleton-card';
            skeleton.innerHTML = `
                <div class="skeleton skeleton-image"></div>
                <div class="skeleton skeleton-text"></div>
                <div class="skeleton skeleton-text short"></div>
            `;
            container.appendChild(skeleton);
        }
        setTimeout(() => {
            container.innerHTML = originalContent;
            initScrollAnimations();
        }, 300);
    }
    
    function hideLoadingSkeletons() {
        // Skeletons are replaced by actual content
    }
    
    // Lazy Loading Images with Intersection Observer
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src && img.src !== img.dataset.src) {
                        img.src = img.dataset.src;
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                }
            });
        }, {
            rootMargin: '50px'
        });
        
        document.querySelectorAll('.lazy-image').forEach(img => {
            imageObserver.observe(img);
        });
    } else {
        // Fallback for browsers without IntersectionObserver
        document.querySelectorAll('.lazy-image').forEach(img => {
            if (img.dataset.src) {
                img.src = img.dataset.src;
                img.classList.add('loaded');
            }
        });
    }
});

function updateProductUnitPrice(productId, selectEl) {
    if (!selectEl) {
        return;
    }
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    if (!selectedOption) {
        return;
    }

    const price = parseFloat(selectedOption.value);
    const displayLabel = selectedOption.dataset.display || '';

    const priceEl = document.querySelector(`[data-price-text="product-${productId}"]`);
    const unitEl = document.querySelector(`[data-unit-text="product-${productId}"]`);

    if (priceEl && !Number.isNaN(price)) {
        priceEl.textContent = `₱${price.toFixed(2)}`;
    }
    if (unitEl) {
        unitEl.textContent = displayLabel ? `per ${displayLabel}` : '';
    }
}
