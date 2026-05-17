<?php
require_once 'includes/enhanced_functions.php';
require_once 'config/maps.php';

$page_title = 'Interactive Market Map - FarmScout Online';
$page_description = 'Find nearest farmers markets and compare prices';

$conn = getDB();

// Get all markets with their products
$markets_query = "SELECT m.*, 
                  COUNT(DISTINCT mf.farmer_id) as farmer_count,
                  COUNT(DISTINCT mp.id) as product_count,
                  AVG(mr.rating) as average_rating
                  FROM markets m 
                  LEFT JOIN market_farmers mf ON m.id = mf.market_id AND mf.approval_status = 'approved'
                  LEFT JOIN market_products mp ON m.id = mp.market_id
                  LEFT JOIN market_reviews mr ON m.id = mr.market_id
                  WHERE m.status = 'active'
                  GROUP BY m.id
                  ORDER BY m.market_name";
$markets_stmt = $conn->prepare($markets_query);
$markets_stmt->execute();
$markets = $markets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate distances if user location is provided
$user_lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$user_lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$markets_with_distance = [];

if ($user_lat && $user_lng) {
    foreach ($markets as $market) {
        $earthRadius = 6371;
        $dLat = deg2rad($market['latitude'] - $user_lat);
        $dLon = deg2rad($market['longitude'] - $user_lng);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($user_lat)) * cos(deg2rad($market['latitude'])) *
             sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c;
        $market['distance'] = round($distance, 2);
        $markets_with_distance[] = $market;
    }
    usort($markets_with_distance, function($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });
} else {
    $markets_with_distance = $markets;
}

// Don't use noeinoi header for map page - use minimal header
$GLOBALS['extra_stylesheets'] = array_merge($GLOBALS['extra_stylesheets'] ?? [], ['css/market-finder.css']);

include 'includes/header.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=VT323&display=swap');
    
    :root {
        --bg-color: #ffffff;
        --text-color: #000000;
        --border-color: #000000;
    }
    
    body {
        margin: 0;
        padding: 0;
        overflow: hidden;
        font-family: 'VT323', monospace;
        background-color: var(--bg-color);
        color: var(--text-color);
    }
    
    /* Hide header for fullscreen map */
    .noeinoi-header,
    header:not(.map-header) {
        display: none !important;
    }
    
    /* Map Header Controls */
    .map-header-controls {
        position: absolute;
        top: 1rem;
        left: 1rem;
        z-index: 1001;
        display: flex;
        gap: 0.5rem;
    }
    
    .map-tab {
        padding: 0.5rem 1rem;
        background: var(--bg-color);
        border: 3px solid var(--border-color);
        border-radius: 0;
        cursor: pointer;
        font-family: 'VT323', monospace;
        font-weight: 700;
        font-size: 1.1rem;
        text-transform: uppercase;
        color: var(--text-color);
        transition: all 0.3s ease;
    }
    
    .map-tab:hover {
        background: var(--text-color);
        color: var(--bg-color);
    }
    
    .map-tab.active {
        background: var(--text-color);
        color: var(--bg-color);
    }
    
    .map-header-right {
        position: absolute;
        top: 1rem;
        right: 1rem;
        z-index: 1001;
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }
    
    .map-close-btn,
    .map-fullscreen-btn {
        padding: 0.5rem 1rem;
        background: var(--bg-color);
        border: 3px solid var(--border-color);
        border-radius: 0;
        cursor: pointer;
        font-family: 'VT323', monospace;
        font-weight: 700;
        font-size: 1.1rem;
        text-transform: uppercase;
        color: var(--text-color);
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .map-close-btn:hover,
    .map-fullscreen-btn:hover {
        background: var(--text-color);
        color: var(--bg-color);
    }
    
    /* Update sidebar header to match Market Finder theme */
    .map-sidebar-header h2 {
        font-family: 'VT323', monospace;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }
    
    /* Update market items to match Market Finder theme */
    .map-market-item {
        border: 3px solid var(--border-color) !important;
        border-radius: 0 !important;
        background: var(--bg-color) !important;
    }
    
    .map-market-item:hover {
        background: #f5f5f5 !important;
    }
    
    .map-market-item.active {
        background: var(--text-color) !important;
        color: var(--bg-color) !important;
        border-color: var(--border-color) !important;
    }
    
    .map-market-name {
        font-family: 'VT323', monospace;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .product-search-wrapper {
        position: relative;
    }

    .map-product-autocomplete {
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 4px);
        z-index: 50;
        max-height: min(280px, 42vh);
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        background: var(--bg-color);
        border: 3px solid var(--border-color);
        box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.12);
    }

    .map-product-autocomplete__item {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.2rem;
        width: 100%;
        margin: 0;
        padding: 0.65rem 0.85rem;
        min-height: 44px;
        box-sizing: border-box;
        font-family: 'VT323', monospace;
        font-size: 1rem;
        text-align: left;
        color: var(--text-color);
        background: var(--bg-color);
        border: none;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        touch-action: manipulation;
    }

    .map-product-autocomplete__item:last-child {
        border-bottom: none;
    }

    .map-product-autocomplete__item:hover,
    .map-product-autocomplete__item.is-active {
        background: var(--text-color);
        color: var(--bg-color);
    }

    .map-product-autocomplete__item:hover .map-product-autocomplete__meta,
    .map-product-autocomplete__item.is-active .map-product-autocomplete__meta {
        color: rgba(255, 255, 255, 0.85);
    }

    .map-product-autocomplete__name {
        font-weight: 700;
        letter-spacing: 0.02em;
        line-height: 1.2;
    }

    .map-product-autocomplete__meta {
        font-size: 0.9rem;
        opacity: 0.85;
        line-height: 1.25;
    }

    .map-market-product-line {
        font-family: 'VT323', monospace;
        font-size: 0.95rem;
        margin: 0.35rem 0 0.25rem;
        opacity: 0.92;
    }

    .map-market-item.active .map-market-product-line {
        opacity: 1;
    }

    .no-results-message .no-results-hint {
        margin-top: 0.75rem;
        font-size: 0.9rem;
        opacity: 0.85;
        line-height: 1.4;
    }
    
    .map-market-venue,
    .map-market-location,
    .map-market-distance {
        font-family: 'VT323', monospace;
    }
    
    /* Update search input to match Market Finder theme */
    .product-search-input {
        border: 3px solid var(--border-color) !important;
        border-radius: 0 !important;
        font-family: 'VT323', monospace !important;
        font-size: 1.1rem !important;
    }
    
    .product-search-input:focus {
        border-color: var(--border-color) !important;
        box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1) !important;
    }
</style>

<!-- Map Modal -->
<div class="map-modal active">
    <div class="map-container">
        <div class="map-content">
            <!-- Map Header Controls -->
            <div class="map-header-controls">
                <button class="map-tab active" id="mapTab">Map</button>
                <button class="map-tab" id="satelliteTab">Satellite</button>
            </div>
            
            <div class="map-header-right">
                <button class="map-fullscreen-btn" id="fullscreenBtn" title="Toggle Fullscreen">⛶</button>
                <a href="app/" class="map-close-btn" title="Back to FarmScout">CLOSE</a>
            </div>
            
            <!-- Map Section (Full Width) -->
            <div class="map-section">
                <div id="marketsMap"></div>
                
                <!-- Markets Floating Card (Left Side) -->
                <div class="map-markets-sidebar">
                    <div class="map-sidebar-header">
                        <h2>Markets</h2>
                    </div>
                    <!-- Product Search -->
                    <div class="product-search-container">
                        <div class="product-search-wrapper">
                            <input 
                                type="text" 
                                id="productSearchInput" 
                                class="product-search-input" 
                                placeholder="Search product (e.g., Kamatis, Rice...)"
                                autocomplete="off"
                            />
                            <button 
                                class="product-search-clear" 
                                id="productSearchClear"
                                onclick="clearProductSearch()"
                                title="Clear search"
                            >×</button>
                            <div class="product-search-icon"></div>
                            <div
                                id="productSearchAutocomplete"
                                class="map-product-autocomplete"
                                role="listbox"
                                aria-label="Product name suggestions"
                                hidden
                            ></div>
                        </div>
                    </div>
                    <div class="map-markets-list" id="mapMarketsList">
                        <?php 
                        $rankBadges = ['NEAREST', '2ND NEAREST', '3RD NEAREST'];
                        foreach ($markets_with_distance as $index => $market): 
                            $classes = ['map-market-item'];
                            if ($index === 0) {
                                $classes[] = 'active';
                            }
                            if ($index === 0 && isset($market['distance'])) {
                                $classes[] = 'nearest';
                            }
                            $badgeText = (isset($market['distance']) && isset($rankBadges[$index])) ? $rankBadges[$index] : null;
                            $badgeClass = $index === 0 ? 'primary' : 'secondary';
                        ?>
                        <div class="<?php echo implode(' ', $classes); ?>" 
                             data-market-index="<?php echo $index; ?>"
                             data-market-id="<?php echo $market['id']; ?>"
                             onclick="selectMarketFromSidebar(<?php echo $index; ?>)">
                            <div class="map-market-name">
                                <?php echo htmlspecialchars($market['market_name']); ?>
                                <?php if ($badgeText): ?>
                                    <span class="nearest-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="map-market-venue"><?php echo htmlspecialchars($market['address']); ?></div>
                            <div class="map-market-location"><?php echo htmlspecialchars($market['operating_hours'] ?? 'Monday-Sunday: 5:00 AM - 8:00 PM'); ?></div>
                            <?php if (isset($market['distance'])): ?>
                            <div class="map-market-distance"><?php echo $market['distance']; ?> km away</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.marketFinderData = <?php echo json_encode([
    'markets' => $markets,
    'marketsWithDistance' => $markets_with_distance,
    'selectedMarketId' => null,
    'selectedCategoryId' => null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

// Initialize map immediately (not as callback)
function initMap() {
    // This will be called by Google Maps API
}
</script>
<script src="js/market-finder.js?v=20260406"></script>
<!-- Google Maps API -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&amp;callback=initMap&amp;loading=async" async defer></script>

<script>
// Map/Satellite toggle
document.getElementById('mapTab').addEventListener('click', function() {
    if (map) {
        map.setMapTypeId(google.maps.MapTypeId.ROADMAP);
    }
    this.classList.add('active');
    document.getElementById('satelliteTab').classList.remove('active');
});

document.getElementById('satelliteTab').addEventListener('click', function() {
    if (map) {
        map.setMapTypeId(google.maps.MapTypeId.SATELLITE);
    }
    this.classList.add('active');
    document.getElementById('mapTab').classList.remove('active');
});

// Fullscreen toggle
document.getElementById('fullscreenBtn').addEventListener('click', function() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
});

// Override initMap to initialize the map for this standalone page
const originalInitMap = window.initMap;
window.initMap = function() {
    const defaultCenter = { lat: 16.8219, lng: 120.4042 };
    
    map = new google.maps.Map(document.getElementById('marketsMap'), {
        zoom: 11,
        center: defaultCenter,
        mapTypeControl: false,
        streetViewControl: true,
        fullscreenControl: false,
        styles: darkMapStyle,
        backgroundColor: '#1a1a1a'
    });
    
    // Add market markers
    addMarketMarkers();
    
    // Try to get user location
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            userLocation = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };
            
            // Add user location marker
            userLocationMarker = new google.maps.Marker({
                position: userLocation,
                map: map,
                title: 'Your Location',
                icon: {
                    url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png'
                }
            });
            
            // Update markets with distances
            updateMarketsWithDistance();
        });
    }
    
    // Select first market
    if (marketsData.length > 0) {
        selectMarketFromSidebar(0);
    }
};

function addMarketMarkers() {
    marketsData.forEach(market => {
        const marker = new google.maps.Marker({
            position: { 
                lat: parseFloat(market.latitude), 
                lng: parseFloat(market.longitude) 
            },
            map: map,
            title: market.market_name,
            icon: defaultMarkerIcon
        });
        marker.marketId = market.id;
        
        const infoContent = buildMapInfoCard({
            title: market.market_name,
            address: market.address,
            hours: market.operating_hours || 'Monday-Sunday: 5:00 AM - 8:00 PM',
            distance: market.distance ? `${market.distance} km away` : '',
            meta: [
                { label: 'Products', value: market.product_count || 0 },
                { label: 'Status', value: isMarketOpen(market.operating_hours) ? 'Open' : 'Closed', className: `map-info-status ${isMarketOpen(market.operating_hours) ? 'open' : 'closed'}` }
            ],
            actionUrl: buildSpaMarketsUrl(market.id, userLocation?.lat, userLocation?.lng)
        });
        
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
                if (parseInt(item.dataset.marketId) === market.id) {
                    item.classList.add('active');
                    selectMarketFromSidebar(i);
                } else {
                    item.classList.remove('active');
                }
            });
        });
        
        markers.push(marker);
    });
}

function updateMarketsWithDistance() {
    if (!userLocation) return;
    
    marketsData.forEach(market => {
        const distance = calculateDistance(
            userLocation.lat, userLocation.lng,
            parseFloat(market.latitude), parseFloat(market.longitude)
        );
        market.distance = parseFloat(distance.toFixed(2));
    });
    
    marketsData.sort((a, b) => (a.distance || 0) - (b.distance || 0));
}
</script>

<?php include 'includes/footer.php'; ?>
