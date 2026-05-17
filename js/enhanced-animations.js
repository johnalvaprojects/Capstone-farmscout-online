/**
 * Enhanced Animation System for FarmScout Online
 * Using GSAP for professional-grade animations
 */

// Initialize GSAP animations after DOM load
document.addEventListener('DOMContentLoaded', function() {
    initializeAnimations();
});

function initializeAnimations() {
    // If GSAP is not available, gracefully fall back to simple CSS animations
    if (typeof window.gsap === 'undefined') {
        // Immediately reveal any elements that rely on GSAP-driven classes
        var fallbackCards = document.querySelectorAll('.scroll-animate-card, .product-card');
        fallbackCards.forEach(function (el) {
            el.classList.add('animate-in');
            el.style.opacity = '1';
            el.style.transform = 'none';
        });
        return;
    }

    // Hero section enhanced animation
    animateHeroSection();
    
    // Product cards staggered animation
    animateProductCards();
    
    // Price change indicators
    initializePriceAnimations();
    
    // Search results animation
    initializeSearchAnimations();
    
    // Shopping list interactions
    initializeShoppingListAnimations();
}

/**
 * Enhanced Hero Section Animation
 */
function animateHeroSection() {
    if (typeof window.gsap === 'undefined') {
        return;
    }
    // Enhanced title animation with GSAP
    const titleText = document.getElementById('reveal-title');
    if (titleText) {
        const words = titleText.textContent.split(' ');
        titleText.innerHTML = '';
        
        words.forEach((word, index) => {
            const wordSpan = document.createElement('span');
            wordSpan.style.display = 'inline-block';
            wordSpan.style.marginRight = '0.3em';
            
            // Split word into letters
            const letters = word.split('');
            letters.forEach((letter, letterIndex) => {
                const letterSpan = document.createElement('span');
                letterSpan.textContent = letter;
                letterSpan.style.display = 'inline-block';
                wordSpan.appendChild(letterSpan);
            });
            
            titleText.appendChild(wordSpan);
        });
        
        // GSAP animation for letters
        gsap.from('#reveal-title span span', {
            duration: 0.8,
            y: 100,
            opacity: 0,
            stagger: 0.03,
            ease: "power3.out",
            delay: 0.2
        });
        
        // Subtitle animation
        gsap.from('#subtitle', {
            duration: 1,
            opacity: 0,
            y: 30,
            delay: 2,
            ease: "power2.out"
        });
    }
}

/**
 * Product Cards Animation
 */
function animateProductCards() {
    if (typeof window.gsap === 'undefined') {
        return;
    }
    var cardEls = document.querySelectorAll('.product-card, .scroll-animate-card');
    if (!cardEls.length) {
        return;
    }
    // Animate product cards on scroll
    gsap.registerPlugin(ScrollTrigger);
    
    gsap.from('.product-card, .scroll-animate-card', {
        scrollTrigger: {
            trigger: '.product-card, .scroll-animate-card',
            start: 'top 80%',
            stagger: 0.1
        },
        duration: 0.6,
        y: 30,
        opacity: 0,
        scale: 0.95,
        ease: "power2.out"
    });
    
    // Hover animations for product cards
    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('mouseenter', () => {
            gsap.to(card, {
                duration: 0.3,
                y: -8,
                scale: 1.02,
                boxShadow: '0 20px 40px rgba(0,0,0,0.1)',
                ease: "power2.out"
            });
        });
        
        card.addEventListener('mouseleave', () => {
            gsap.to(card, {
                duration: 0.3,
                y: 0,
                scale: 1,
                boxShadow: '0 4px 6px rgba(0,0,0,0.1)',
                ease: "power2.out"
            });
        });
    });
}

/**
 * Price Change Animations
 */
function initializePriceAnimations() {
    if (typeof window.gsap === 'undefined') {
        return;
    }
    // Price update animation function
    window.animatePriceChange = function(element, oldPrice, newPrice) {
        const isIncrease = parseFloat(newPrice) > parseFloat(oldPrice);
        
        // Create price change indicator
        const indicator = document.createElement('div');
        indicator.className = `price-indicator ${isIncrease ? 'price-up' : 'price-down'}`;
        indicator.innerHTML = isIncrease ? '↗' : '↘';
        
        element.appendChild(indicator);
        
        // Animate the price change
        const tl = gsap.timeline();
        
        tl.to(element, {
            duration: 0.2,
            scale: 1.1,
            ease: "power2.out"
        })
        .to(indicator, {
            duration: 0.3,
            opacity: 1,
            y: -20,
            ease: "power2.out"
        }, 0)
        .to(element, {
            duration: 0.3,
            scale: 1,
            ease: "power2.out"
        })
        .to(indicator, {
            duration: 0.3,
            opacity: 0,
            y: -40,
            ease: "power2.in",
            onComplete: () => indicator.remove()
        }, "-=0.1");
    };
    
    // Add CSS for price indicators
    const style = document.createElement('style');
    style.textContent = `
        .price-indicator {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            opacity: 0;
            pointer-events: none;
        }
        .price-up {
            background: #DC3545;
            color: white;
        }
        .price-down {
            background: #28A745;
            color: white;
        }
    `;
    document.head.appendChild(style);
}

/**
 * Search Results Animation
 */
function initializeSearchAnimations() {
    if (typeof window.gsap === 'undefined') {
        return;
    }
    // Animate search results when they appear
    const searchContainer = document.querySelector('.search-results');
    if (searchContainer) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.addedNodes.length > 0) {
                    const newResults = Array.from(mutation.addedNodes)
                        .filter(node => node.classList && node.classList.contains('search-result'));
                    
                    if (newResults.length > 0) {
                        gsap.from(newResults, {
                            duration: 0.4,
                            x: -30,
                            opacity: 0,
                            stagger: 0.05,
                            ease: "power2.out"
                        });
                    }
                }
            });
        });
        
        observer.observe(searchContainer, { childList: true });
    }
}

/**
 * Shopping List Animations
 */
function initializeShoppingListAnimations() {
    // Add to cart animation
    window.animateAddToCart = function(button, productCard) {
        const cart = document.querySelector('.shopping-cart-icon') || document.querySelector('[data-cart]');
        
        if (cart) {
            // Create flying product animation
            const flyingProduct = productCard.cloneNode(true);
            flyingProduct.style.position = 'fixed';
            flyingProduct.style.zIndex = '9999';
            flyingProduct.style.pointerEvents = 'none';
            flyingProduct.style.width = '60px';
            flyingProduct.style.height = '60px';
            
            const rect = productCard.getBoundingClientRect();
            const cartRect = cart.getBoundingClientRect();
            
            flyingProduct.style.left = rect.left + 'px';
            flyingProduct.style.top = rect.top + 'px';
            
            document.body.appendChild(flyingProduct);
            
            // Animate to cart
            gsap.to(flyingProduct, {
                duration: 0.8,
                x: cartRect.left - rect.left,
                y: cartRect.top - rect.top,
                scale: 0.1,
                ease: "power2.inOut",
                onComplete: () => {
                    flyingProduct.remove();
                    // Animate cart icon
                    gsap.to(cart, {
                        duration: 0.2,
                        scale: 1.2,
                        yoyo: true,
                        repeat: 1,
                        ease: "power2.inOut"
                    });
                }
            });
        }
        
        // Button feedback
        gsap.to(button, {
            duration: 0.1,
            scale: 0.95,
            yoyo: true,
            repeat: 1,
            ease: "power2.inOut"
        });
    };
    
    // Shopping list item animations
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-add-to-cart]')) {
            const button = e.target;
            const productCard = button.closest('.product-card') || button.closest('[data-product]');
            
            if (productCard) {
                animateAddToCart(button, productCard);
            }
        }
    });
}

/**
 * Page Transition Animations
 */
function initializePageTransitions() {
    // Smooth page transitions
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href]:not([href^="#"]):not([target="_blank"])');
        if (link && link.hostname === window.location.hostname) {
            e.preventDefault();
            
            gsap.to('body', {
                duration: 0.3,
                opacity: 0,
                ease: "power2.inOut",
                onComplete: () => {
                    window.location.href = link.href;
                }
            });
        }
    });
    
    // Fade in on page load
    gsap.from('body', {
        duration: 0.5,
        opacity: 0,
        ease: "power2.out"
    });
}

/**
 * Notification Animations
 */
window.showNotification = function(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${type === 'success' ? '✓' : '⚠'}</span>
            <span class="notification-message">${message}</span>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28A745' : '#DC3545'};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 10000;
        opacity: 0;
        transform: translateX(100%);
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    gsap.to(notification, {
        duration: 0.4,
        opacity: 1,
        x: 0,
        ease: "power2.out"
    });
    
    // Animate out after delay
    gsap.to(notification, {
        duration: 0.4,
        opacity: 0,
        x: '100%',
        delay: 3,
        ease: "power2.in",
        onComplete: () => notification.remove()
    });
};

/**
 * Mobile-specific animations
 */
function initializeMobileAnimations() {
    if (window.innerWidth <= 768) {
        // Reduce animation complexity on mobile
        gsap.config({ force3D: false });
        
        // Touch feedback for mobile
        document.addEventListener('touchstart', function(e) {
            if (e.target.matches('button, .btn, .card, .product-card')) {
                gsap.to(e.target, {
                    duration: 0.1,
                    scale: 0.98,
                    ease: "power2.out"
                });
            }
        });
        
        document.addEventListener('touchend', function(e) {
            if (e.target.matches('button, .btn, .card, .product-card')) {
                gsap.to(e.target, {
                    duration: 0.2,
                    scale: 1,
                    ease: "power2.out"
                });
            }
        });
    }
}

// Initialize mobile animations
if (window.innerWidth <= 768) {
    initializeMobileAnimations();
}

// Reinitialize on resize
window.addEventListener('resize', () => {
    if (window.innerWidth <= 768) {
        initializeMobileAnimations();
    }
});