<!-- Footer -->
    <?php 
    // Use new footer design by default, unless explicitly disabled
    // Set $use_old_footer = true; in any page to use the old footer design
    if (!isset($use_old_footer) || !$use_old_footer): 
    ?>
        <style>
            @font-face {
                font-family: 'Ronzino';
                src: url('assets/fonts/Ronzino-Bold.woff2') format('woff2'),
                     url('assets/fonts/Ronzino-Bold.otf') format('opentype');
                font-weight: 700;
                font-style: normal;
                font-display: swap;
            }
            @font-face {
                font-family: 'Ronzino';
                src: url('assets/fonts/Ronzino-Regular.woff2') format('woff2'),
                     url('assets/fonts/Ronzino-Regular.otf') format('opentype');
                font-weight: 400;
                font-style: normal;
                font-display: swap;
            }
            .mf-footer {
                background: #030303;
                color: #f3f2ee;
                padding: clamp(3rem, 6vw, 6rem) clamp(1.5rem, 6vw, 6rem);
                margin-top: clamp(3rem, 6vw, 6rem);
                font-family: 'AileronRegular', 'Inter', sans-serif;
            }
            .mf-footer-wordmark {
                font-family: 'Ronzino', 'AileronRegular', sans-serif;
                font-size: clamp(3rem, 20vw, 14rem);
                letter-spacing: -0.04em;
                text-transform: uppercase;
                margin-bottom: clamp(2rem, 4vw, 3rem);
            }
            .mf-footer-copy {
                color: rgba(243, 242, 238, 0.65);
                max-width: 520px;
                line-height: 1.8;
                font-size: 0.95rem;
                margin-bottom: clamp(2rem, 4vw, 3rem);
            }
            .mf-footer-support {
                border-bottom: 1px solid rgba(243, 242, 238, 0.3);
                padding-bottom: clamp(2rem, 4vw, 3rem);
                margin-bottom: clamp(3rem, 5vw, 4rem);
            }
            .mf-footer-support-title {
                font-family: 'Ronzino', sans-serif;
                font-size: 0.85rem;
                letter-spacing: 0.3em;
                text-transform: uppercase;
                color: rgba(243, 242, 238, 0.7);
                margin-bottom: 1.5rem;
            }
            .mf-footer-support-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
                margin-bottom: 1.25rem;
            }
            .mf-footer-support-btn {
                background: transparent;
                border: 1px solid rgba(243, 242, 238, 0.3);
                color: #f3f2ee;
                padding: 0.75rem 1.5rem;
                font-family: 'Ronzino', sans-serif;
                font-size: 0.85rem;
                letter-spacing: 0.15em;
                text-transform: uppercase;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            .mf-footer-support-btn:hover {
                border-color: #f4e000;
                color: #f4e000;
                transform: translateY(-2px);
            }
            .mf-footer-support-btn.primary {
                background: #f4e000;
                border-color: #f4e000;
                color: #050505;
            }
            .mf-footer-support-btn.primary:hover {
                background: #f5e500;
                border-color: #f5e500;
                transform: translateY(-2px);
            }
            .mf-footer-support-note {
                color: rgba(243, 242, 238, 0.5);
                font-size: 0.8rem;
                letter-spacing: 0.1em;
                line-height: 1.6;
            }
            .mf-footer-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 2rem;
                margin-bottom: clamp(2rem, 4vw, 3rem);
            }
            .mf-footer-column-title {
                font-family: 'Ronzino', sans-serif;
                text-transform: uppercase;
                letter-spacing: 0.25em;
                font-size: 0.75rem;
                margin-bottom: 0.75rem;
                color: rgba(243, 242, 238, 0.7);
            }
            .mf-footer-column a,
            .mf-footer-column p {
                display: block;
                color: #f3f2ee;
                text-decoration: none;
                margin-bottom: 0.35rem;
                font-size: 0.95rem;
                letter-spacing: 0.05em;
            }
            .mf-footer-column a:hover {
                color: #f4e000;
            }
            .mf-footer-bottom {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                gap: 1rem;
                font-size: 0.8rem;
                letter-spacing: 0.2em;
                color: rgba(243, 242, 238, 0.6);
                text-transform: uppercase;
            }
            @media (max-width: 640px) {
                .mf-footer-support-buttons {
                    flex-direction: column;
                }
                .mf-footer-support-btn {
                    width: 100%;
                }
            }
        </style>
        <footer class="mf-footer">
            <div class="mf-footer-wordmark">FARMSCOUT</div>
            <p class="mf-footer-copy">
                Help keep real-time prices free for La Union shoppers. Your support powers market data collection, price alerts, and helps us onboard new markets across the region.
            </p>
            <div class="mf-footer-support">
                <div class="mf-footer-support-title">Market Support Fund</div>
                <div class="mf-footer-support-buttons">
                    <button class="mf-footer-support-btn" onclick="window.open('https://www.gcash.com', '_blank')">₱100</button>
                    <button class="mf-footer-support-btn" onclick="window.open('https://www.gcash.com', '_blank')">₱300</button>
                    <button class="mf-footer-support-btn" onclick="window.open('https://www.gcash.com', '_blank')">₱500</button>
                    <button class="mf-footer-support-btn primary" onclick="window.open('https://www.gcash.com', '_blank')">Custom</button>
                </div>
                <p class="mf-footer-support-note">
                    Fully transparent monthly updates on how funds are used. Every peso helps keep FarmScout free and accessible for Filipino families.
                </p>
            </div>
            <div class="mf-footer-grid">
                <div class="mf-footer-column">
                    <div class="mf-footer-column-title">La Union</div>
                    <p>Balaoan Public Market<br>La Union, 2517</p>
                    <p>San Fernando City Market<br>La Union, 2500</p>
                    <p>San Juan Night Market<br>La Union, 2511</p>
                </div>
                <div class="mf-footer-column">
                    <div class="mf-footer-column-title">FarmScout</div>
                    <a href="index.php">Home</a>
                    <a href="market-finder.php">Market Finder</a>
                    <a href="price-alerts.php">Price Alerts</a>
                </div>
                <div class="mf-footer-column">
                    <div class="mf-footer-column-title">Contact</div>
                    <p>community@farmscout.ph</p>
                    <p>+63 927 555 1122</p>
                    <div style="margin-top: 0.75rem;">
                        <a href="https://facebook.com" target="_blank" rel="noopener">Facebook</a>
                        <a href="https://instagram.com" target="_blank" rel="noopener">Instagram</a>
                        <a href="https://youtube.com" target="_blank" rel="noopener">YouTube</a>
                    </div>
                </div>
            </div>
            <div class="mf-footer-bottom">
                <span>&copy; <?php echo date('Y'); ?> FarmScout Online</span>
                <span>Privacy &nbsp;/&nbsp; Terms</span>
            </div>
        </footer>
    <?php else: ?>
        <style>
            /* Finland Rounded Thin Font for Footer */
            @font-face {
                font-family: 'Finland Rounded Thin';
                src: url('./assets/fonts/Finland Rounded Thin.otf') format('opentype');
                font-style: normal;
                font-weight: 300;
                text-rendering: optimizeLegibility;
                font-display: swap;
            }
            
            /* Finland font classes for footer */
            .footer-finland-font {
                font-family: 'Finland Rounded Thin', 'Helvetica Neue', Arial, sans-serif !important;
                font-weight: 300 !important;
                text-rendering: optimizeLegibility;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
            
            .footer-finland-heading {
                font-family: 'Finland Rounded Thin', 'Helvetica Neue', Arial, sans-serif !important;
                font-weight: 400 !important;
                text-rendering: optimizeLegibility;
            }
        </style>
        <footer class="bg-black text-white py-12" style="background-color: #000000 !important;">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- Brand -->
                    <div class="col-span-1 md:col-span-2">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 mr-4">
                                <img src="assets/images/gif-wazulafu-no-bg.gif" 
                                     alt="FarmScout - Tapat na Presyo" 
                                     class="h-12 w-12 md:h-14 md:w-14 object-contain" 
                                     onerror="this.src='assets/images/farmscoutlogo.png'; this.onerror=null;" />
                            </div>
                            <div>
                                <h3 class="modern-logo-text text-white" style="font-weight: 800; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important; letter-spacing: -0.03em; color: #ffffff !important; font-size: 1.5rem;">FARMSCOUT</h3>
                                <p class="modern-tagline text-gray-300" style="font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important; color: #cccccc !important; font-size: 0.75rem; letter-spacing: 0.05em; text-transform: uppercase;">Tapat na Presyo</p>
                            </div>
                        </div>
                        <p class="text-gray-300 mb-4 footer-finland-font" style="line-height: 1.6; letter-spacing: 0.02em; font-size: 1rem;">
                            Your trusted digital guide to Baloan Public Market. Empowering Filipino families with transparent, real-time pricing information for smarter market shopping.
                        </p>
                    </div>
    
                    <!-- Quick Links -->
                    <div>
                        <h4 class="text-lg font-semibold mb-4 footer-finland-heading" style="letter-spacing: 0.05em; text-transform: uppercase; font-size: 1.1rem;">QUICK LINKS</h4>
                        <ul class="space-y-2">
                            <li><a href="index.php" class="text-gray-300 hover:text-white transition-colors footer-finland-font" style="letter-spacing: 0.02em; font-size: 0.95rem;">Home</a></li>
                            <li><a href="market-finder.php" class="text-gray-300 hover:text-white transition-colors footer-finland-font" style="letter-spacing: 0.02em; font-size: 0.95rem;">Market Finder</a></li>
                            <li><a href="market-finder.php" class="text-gray-300 hover:text-white transition-colors footer-finland-font" style="letter-spacing: 0.02em; font-size: 0.95rem;">Market Info</a></li>
                            <li><a href="index.php" class="text-gray-300 hover:text-white transition-colors footer-finland-font" style="letter-spacing: 0.02em; font-size: 0.95rem;">Mobile Checker</a></li>
                        </ul>
                    </div>
    
                    <!-- Contact -->
                    <div>
                        <h4 class="text-lg font-semibold mb-4 footer-finland-heading" style="letter-spacing: 0.05em; text-transform: uppercase; font-size: 1.1rem;">CONTACT</h4>
                        <ul class="space-y-2 text-gray-300">
                            <li class="footer-finland-font" style="letter-spacing: 0.02em; font-size: 0.95rem;">Baloan Public Market</li>
                            <li class="footer-finland-font" style="letter-spacing: 0.02em; font-size: 0.95rem;">La Union, Philippines</li>
                            <li class="footer-finland-font" style="letter-spacing: 0.02em; font-size: 0.95rem;">farmscout@email.com</li>
                            <li class="footer-finland-font" style="letter-spacing: 0.02em; font-size: 0.95rem;">+63 912 345 6789</li>
                        </ul>
                    </div>
                </div>
    
                <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-300">
                    <p class="footer-finland-font" style="letter-spacing: 0.02em; font-size: 0.9rem;">&copy; 2025 FarmScout Online. All Rights Reserved. | Serving the Filipino community with transparency and trust.</p>
                </div>
            </div>
        </footer>
    <?php endif; ?>

    <!-- Hero Slider JavaScript -->
    <script src="js/hero-slider.js?v=20260405"></script>
    
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('mobile-menu');
            const button = e.target.closest('button');
            
            if (!menu.contains(e.target) && !button) {
                menu.classList.add('hidden');
            }
        });
        
        // Navbar scroll hide functionality DISABLED - navbar stays visible always
        // Removed scroll hide functionality as per user request
        
    </script>
</body>
</html>
