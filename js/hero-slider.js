// Simple Hero Slider JavaScript - Fade Transitions Only (home / pages with .hero-slider-container only)
document.addEventListener('DOMContentLoaded', function() {
    if (!document.querySelector('.hero-slider-container')) {
        return;
    }
    new SimpleHeroSlider({
        container: '.hero-slider-container',
        slides: '.hero-slide',
        indicators: '.hero-indicator',
        prevBtn: '.hero-nav-prev',
        nextBtn: '.hero-nav-next',
        autoPlay: true,
        autoPlayInterval: 6000,
        pauseOnHover: true
    });
});

class SimpleHeroSlider {
    constructor(options) {
        // Configuration
        this.container = document.querySelector(options.container);
        this.slides = document.querySelectorAll(options.slides);
        this.indicators = document.querySelectorAll(options.indicators);
        this.prevBtn = document.querySelector(options.prevBtn);
        this.nextBtn = document.querySelector(options.nextBtn);
        this.autoPlay = options.autoPlay || false;
        this.autoPlayInterval = options.autoPlayInterval || 5000;
        this.pauseOnHover = options.pauseOnHover || false;
        
        // State
        this.currentSlide = 0;
        this.totalSlides = this.slides.length;
        this.isPlaying = false;
        this.autoPlayTimer = null;
        this.isTransitioning = false;
        
        // Initialize
        this.init();
    }
    
    init() {
        if (!this.container || this.totalSlides === 0) {
            return;
        }
        
        // Set up initial state
        this.setupSlides();
        this.setupIndicators();
        this.setupNavigation();
        this.setupEvents();
        
        // Start autoplay if enabled
        if (this.autoPlay) {
            this.startAutoPlay();
        }
    }
    
    setupSlides() {
        this.slides.forEach((slide, index) => {
            slide.classList.remove('active');
            if (index === this.currentSlide) {
                slide.classList.add('active');
            }
        });
    }
    
    setupIndicators() {
        this.indicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === this.currentSlide);
            indicator.addEventListener('click', () => {
                if (!this.isTransitioning) {
                    this.goToSlide(index);
                }
            });
        });
    }
    
    setupNavigation() {
        if (this.prevBtn) {
            this.prevBtn.addEventListener('click', () => {
                if (!this.isTransitioning) {
                    this.prevSlide();
                }
            });
        }
        
        if (this.nextBtn) {
            this.nextBtn.addEventListener('click', () => {
                if (!this.isTransitioning) {
                    this.nextSlide();
                }
            });
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (this.isTransitioning) return;
            
            switch(e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    this.prevSlide();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.nextSlide();
                    break;
                case ' ':
                    e.preventDefault();
                    this.toggleAutoPlay();
                    break;
            }
        });
    }
    
    setupEvents() {
        if (this.pauseOnHover) {
            this.container.addEventListener('mouseenter', () => {
                this.pauseAutoPlay();
            });
            
            this.container.addEventListener('mouseleave', () => {
                if (this.autoPlay) {
                    this.startAutoPlay();
                }
            });
        }
        
        // Touch/swipe support
        this.setupTouchEvents();
        
        // Visibility change handling
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseAutoPlay();
            } else if (this.autoPlay && !this.pauseOnHover) {
                this.startAutoPlay();
            }
        });
    }
    
    setupTouchEvents() {
        let startX = 0;
        let startY = 0;
        let isScrolling = false;
        
        this.container.addEventListener('touchstart', (e) => {
            if (this.isTransitioning) return;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            isScrolling = false;
        }, { passive: true });
        
        this.container.addEventListener('touchmove', (e) => {
            if (this.isTransitioning) return;
            
            const deltaX = e.touches[0].clientX - startX;
            const deltaY = e.touches[0].clientY - startY;
            
            // Determine if this is a horizontal swipe
            if (!isScrolling) {
                isScrolling = Math.abs(deltaY) > Math.abs(deltaX);
            }
            
            // Prevent default only for horizontal swipes
            if (!isScrolling && Math.abs(deltaX) > 10) {
                e.preventDefault();
            }
        }, { passive: false });
        
        this.container.addEventListener('touchend', (e) => {
            if (this.isTransitioning || isScrolling) return;
            
            const deltaX = e.changedTouches[0].clientX - startX;
            const threshold = 50; // minimum swipe distance
            
            if (Math.abs(deltaX) > threshold) {
                if (deltaX > 0) {
                    this.prevSlide();
                } else {
                    this.nextSlide();
                }
            }
        }, { passive: true });
    }
    
    goToSlide(index) {
        if (this.isTransitioning || index === this.currentSlide) return;
        
        this.isTransitioning = true;
        const previousSlide = this.currentSlide;
        this.currentSlide = index;
        
        // Simple fade transition - remove active from previous, add to new
        this.slides[previousSlide].classList.remove('active');
        this.slides[this.currentSlide].classList.add('active');
        
        // Update indicators
        this.indicators.forEach((indicator, i) => {
            indicator.classList.toggle('active', i === this.currentSlide);
        });
        
        // Reset transition flag
        setTimeout(() => {
            this.isTransitioning = false;
        }, 800); // Match CSS transition duration
        
        // Restart autoplay if active
        if (this.isPlaying) {
            this.restartAutoPlay();
        }
        
        // Trigger custom event
        this.container.dispatchEvent(new CustomEvent('slideChange', {
            detail: {
                currentSlide: this.currentSlide,
                previousSlide: previousSlide
            }
        }));
    }
    
    nextSlide() {
        const nextIndex = (this.currentSlide + 1) % this.totalSlides;
        this.goToSlide(nextIndex);
    }
    
    prevSlide() {
        const prevIndex = (this.currentSlide - 1 + this.totalSlides) % this.totalSlides;
        this.goToSlide(prevIndex);
    }
    
    startAutoPlay() {
        if (this.isPlaying) return;
        
        this.isPlaying = true;
        this.autoPlayTimer = setInterval(() => {
            this.nextSlide();
        }, this.autoPlayInterval);
    }
    
    pauseAutoPlay() {
        this.isPlaying = false;
        if (this.autoPlayTimer) {
            clearInterval(this.autoPlayTimer);
            this.autoPlayTimer = null;
        }
    }
    
    restartAutoPlay() {
        this.pauseAutoPlay();
        this.startAutoPlay();
    }
    
    toggleAutoPlay() {
        if (this.isPlaying) {
            this.pauseAutoPlay();
        } else {
            this.startAutoPlay();
        }
    }
    
    // Public API methods
    destroy() {
        this.pauseAutoPlay();
        
        // Remove event listeners
        this.indicators.forEach(indicator => {
            indicator.replaceWith(indicator.cloneNode(true));
        });
        
        if (this.prevBtn) this.prevBtn.replaceWith(this.prevBtn.cloneNode(true));
        if (this.nextBtn) this.nextBtn.replaceWith(this.nextBtn.cloneNode(true));
        
        // Reset classes
        this.slides.forEach(slide => {
            slide.classList.remove('active');
        });
    }
    
    getCurrentSlide() {
        return this.currentSlide;
    }
    
    getTotalSlides() {
        return this.totalSlides;
    }
    
    isAutoPlaying() {
        return this.isPlaying;
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { SimpleHeroSlider };
}