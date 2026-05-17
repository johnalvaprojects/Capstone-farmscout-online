<?php
require_once 'includes/enhanced_functions.php';

// Auto-redirect farmers and admins to their dashboards when they open the home page.
// Use ?view_home=1 to stay on the landing page (marketing, testing, or "Login" flow without bouncing away).
$skip_home_redirect = isset($_GET['view_home']) && $_GET['view_home'] === '1';
if (isLoggedIn() && !$skip_home_redirect) {
    $user_role = $_SESSION['user_role'] ?? '';
    
    if ($user_role === 'farmer') {
        header('Location: farmer-dashboard.php');
        exit();
    } elseif ($user_role === 'admin' || $user_role === 'super_admin') {
        header('Location: admin-console.php');
        exit();
    } elseif ($user_role === 'vendor') {
        header('Location: farmer-dashboard.php');
        exit();
    }
}

$page_title = 'Home - FarmScout Online';
$page_description = 'Discover fresh markets and products in La Union.';
$current_page = 'index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/gif" href="<?php echo htmlspecialchars(assetUrl('assets/images/demi doggu.gif')); ?>">

    <!-- GSAP (loaded early so curtain + hero animations fire on first paint) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=VT323&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        @font-face {
            font-family: 'Inter Display';
            src: url('assets/fonts/Inter-4.1/extras/otf/InterDisplay-Medium.otf') format('opentype');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }
        
        /* Akony Font */
        @font-face {
            font-family: 'Akony';
            src: url('assets/fonts/AKONY.otf') format('opentype'),
                 url('assets/fonts/AKONY.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        /* Ronzino Font */
        @font-face {
            font-family: 'Ronzino';
            src: url('assets/fonts/Ronzino-Regular.woff2') format('woff2'),
                 url('assets/fonts/Ronzino-Regular.otf') format('opentype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'Ronzino';
            src: url('assets/fonts/Ronzino-Bold.woff2') format('woff2'),
                 url('assets/fonts/Ronzino-Bold.otf') format('opentype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        
        /* Barracuda Font */
        @font-face {
            font-family: 'Barracuda';
            src: url('assets/fonts/Barracuda - typeface/Barracuda-regular.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'Barracuda';
            src: url('assets/fonts/Barracuda - typeface/Barracuda-Bold.ttf') format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'Barracuda';
            src: url('assets/fonts/Barracuda - typeface/Barracuda-Light.ttf') format('truetype');
            font-weight: 300;
            font-style: normal;
            font-display: swap;
        }
        
        /* Modern Society Font */
        @font-face {
            font-family: 'Modern Society';
            src: url('assets/fonts/modern_society/ModernSociety-Regular.otf') format('opentype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        
        /* Geist Sans Font */
        @font-face {
            font-family: 'Geist';
            src: url('assets/fonts/Geist/Geist-Regular.otf') format('opentype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'Geist';
            src: url('assets/fonts/Geist/Geist-Bold.otf') format('opentype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'Geist';
            src: url('assets/fonts/Geist/Geist-Medium.otf') format('opentype');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'Geist';
            src: url('assets/fonts/Geist/Geist-Light.otf') format('opentype');
            font-weight: 300;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'Geist';
            src: url('assets/fonts/Geist/Geist-SemiBold.otf') format('opentype');
            font-weight: 600;
            font-style: normal;
            font-display: swap;
        }
        
        /* Geist Mono Font - Check if available */
        @font-face {
            font-family: 'Geist Mono';
            src: url('assets/fonts/Geist/GeistMono-Regular.otf') format('opentype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'Geist Mono';
            src: url('assets/fonts/Geist/GeistMono-Bold.otf') format('opentype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-color: #ffffff;
            --text-color: #000000;
            --border-color: #000000;
        }
        
        html {
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
        }
        
        body {
            font-family: 'VT323', monospace;
            background-color: #000000; /* Black background for video section */
            color: var(--text-color);
            position: relative;
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
        }
        
        /* Fixed Navbar - unaffected by scroll */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 2rem;
            font-size: 1.25rem;
            letter-spacing: 0.05em;
            background-color: transparent;
            pointer-events: none;
        }
        
        .header .logo,
        .header .nav a {
            color: #ffffff;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
            transition: color 0.3s ease, text-shadow 0.3s ease;
        }
        
        /* Navbar on white background */
        .header.on-white .logo,
        .header.on-white .nav a {
            color: #000000;
            text-shadow: none;
        }
        
        .header.on-white .icon-box {
            border-color: #000000;
        }
        
        .header.on-white .login-btn {
            border-color: #000000;
            color: #000000;
        }
        
        .header.on-white .login-btn:hover {
            background-color: #000000;
            color: #ffffff;
        }
        
        .header .icon-box {
            border-color: #ffffff;
            background-color: transparent;
        }
        
        .header .login-btn {
            border-color: #ffffff;
            background-color: transparent;
            color: #ffffff;
        }
        
        .header .login-btn:hover {
            background-color: #ffffff;
            color: #000000;
        }
        
        .header > * {
            pointer-events: auto;
        }
        
        .logo {
            font-weight: bold;
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .logo img {
            height: 2rem;
            width: auto;
            object-fit: contain;
        }
        
        .nav {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .nav a {
            color: var(--text-color);
            text-decoration: none;
            transition: color 0.3s ease;
            font-size: 1.1rem;
        }
        
        .nav a:hover {
            opacity: 0.7;
        }
        
        .nav-icons {
            display: flex;
            gap: 0.5rem;
            margin-left: 1rem;
        }
        
        .icon-box {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            background-color: var(--bg-color);
        }
        
        .login-btn {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border-color);
            background-color: var(--bg-color);
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .login-btn:hover {
            background-color: var(--text-color);
            color: var(--bg-color);
        }
        
        /* Account Dropdown */
        .account-dropdown {
            position: relative;
        }
        
        .account-dropdown-toggle {
            cursor: pointer;
            position: relative;
        }
        
        .account-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background-color: var(--bg-color);
            border: 2px solid var(--border-color);
            min-width: 150px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .account-dropdown.active .account-dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .account-dropdown-menu a {
            display: block;
            padding: 0.75rem 1rem;
            color: var(--text-color);
            text-decoration: none;
            font-family: 'VT323', monospace;
            font-size: 1rem;
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--border-color);
        }
        
        .account-dropdown-menu a:last-child {
            border-bottom: none;
        }
        
        .account-dropdown-menu a:hover {
            background-color: var(--text-color);
            color: var(--bg-color);
        }
        
        /* Body background - transitions from transparent to light pale brown */
        /* Scroll Container - normal scroll flow */
        .scroll-container {
            position: relative;
            width: 100%;
        }

        /* Ensure GSAP pin spacer stays responsive */
        .pin-spacer {
            width: 100% !important;
            max-width: 100vw !important;
            box-sizing: border-box;
        }
        
        /* Editorial Section */
        .editorial-section {
            position: relative;
            width: 100vw;
            min-height: 100vh;
            background: #f5f3f0;
            color: #000000;
            padding: 0;
            margin: 0;
            z-index: 10;
            overflow-x: hidden;
            left: 0;
            right: 0;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        .editorial-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            padding: 8rem 4rem 0;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
            align-items: start;
            justify-content: center;
            min-height: 100vh;
        }
        
        .editorial-text-block {
            display: flex;
            flex-direction: column;
            gap: 2rem;
            position: relative;
            z-index: 2; /* Ensure text is above wordmark */
        }
        
        .editorial-text-block p {
            font-size: 1.1rem;
            line-height: 1.7;
            font-weight: 400;
            color: #000000;
            letter-spacing: -0.01em;
            font-family: 'Geist', 'Modern Society', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            opacity: 0;
            transform: translateY(50px);
            position: relative;
            z-index: 2; /* Ensure text is above wordmark */
        }
        
        /* Video Placeholder */
        .editorial-video-placeholder {
            width: 100%;
            aspect-ratio: 16 / 9;
            background: #000000;
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-top: 0;
            opacity: 0;
            transform: translateY(50px);
            position: relative;
            z-index: 2; /* Above wordmark */
        }
        
        .editorial-video-placeholder video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        .editorial-bottom-wordmark {
            position: absolute;
            bottom: -2%;
            right: 0%;
            left: auto;
            font-size: clamp(8rem, 25vw, 22rem);
            font-weight: 700;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: -0.03em;
            line-height: 0.85;
            padding: 0;
            text-align: right;
            pointer-events: none;
            z-index: 0; /* Lower z-index to prevent overlapping */
            overflow: visible;
            font-family: 'Barracuda', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            opacity: 0;
            transform: translateY(50px);
        }

        @media (max-width: 968px) {
            .editorial-content {
                grid-template-columns: 1fr;
                gap: 3rem;
                padding: 6rem 2rem 10rem !important; /* Increased bottom padding */
                position: relative;
            }
            
            .editorial-text-block {
                position: relative;
                z-index: 2; /* Above wordmark */
            }
            
            .editorial-bottom-wordmark {
                padding: 0;
                bottom: 3% !important; /* Moved up */
                right: 2% !important;
                left: auto;
                font-size: clamp(3rem, 12vw, 8rem) !important; /* Smaller to prevent overlap */
                z-index: 0 !important; /* Behind content */
                opacity: 0.25 !important; /* More transparent */
                max-width: 70% !important; /* Limit width */
            }
        }
        
        /* Footer Section (Section 3) */
        .footer-section {
            position: relative;
            width: 100vw;
            height: 100vh;
            background: #f5f3f0;
            z-index: 15;
            overflow: hidden;
        }

        .footer-section-container {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        /* Footer Content */
        .footer-section .footer {
            position: relative;
            width: 100%;
            background: #f5f3f0;
            padding: 2rem 2rem 1rem;
            z-index: 2;
            margin: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
        }

        .footer-section .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .footer-section .footer-columns {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 3rem;
            margin-bottom: 1.5rem;
        }

        .footer-section .footer-column h3 {
            font-size: 0.875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1.5rem;
            color: #1a1a1a;
        }

        .footer-section .footer-column ul {
            list-style: none;
        }

        .footer-section .footer-column li {
            margin-bottom: 0.75rem;
        }

        .footer-section .footer-column a {
            color: #1a1a1a;
            text-decoration: none;
            font-size: 0.9375rem;
            transition: opacity 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .footer-section .footer-column a:hover {
            opacity: 0.7;
        }

        .footer-section .footer-column a .external-icon {
            font-size: 0.75rem;
            opacity: 0.6;
        }

        .footer-section .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: auto;
        }

        .footer-section .footer-bottom-left {
            display: flex;
            gap: 2rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .footer-section .footer-bottom-left a {
            color: #1a1a1a;
            text-decoration: none;
            transition: opacity 0.2s ease;
        }

        .footer-section .footer-bottom-left a:hover {
            opacity: 0.7;
        }

        .footer-section .footer-bottom-right {
            font-size: 0.75rem;
            color: #1a1a1a;
        }

        /* Background Video Section (replaces purple wave) */
        .footer-section .purple-wave-container {
            position: relative;
            width: 100%;
            height: 40vh;
            flex-shrink: 0;
            overflow: hidden;
            margin: 0;
            z-index: 1;
        }

        /* Background Video */
        .footer-section .footer-background-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            z-index: 1;
            pointer-events: none;
        }

        /* Fallback Purple Gradient (shown if video fails to load) */
        .footer-section .purple-wave-fallback {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            display: none; /* Hidden by default, shown if video fails */
        }

        .footer-section .purple-wave-fallback.show {
            display: block;
        }

        .footer-section .purple-wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(ellipse 80% 60% at 20% 50%, rgba(138, 43, 226, 0.6) 0%, transparent 50%),
                radial-gradient(ellipse 60% 80% at 80% 70%, rgba(186, 85, 211, 0.5) 0%, transparent 50%),
                radial-gradient(ellipse 70% 50% at 50% 100%, rgba(75, 0, 130, 0.8) 0%, rgba(75, 0, 130, 0.4) 50%, transparent 100%),
                linear-gradient(180deg, rgba(138, 43, 226, 0.1) 0%, rgba(75, 0, 130, 0.9) 100%);
            filter: blur(40px);
            transform: scaleY(1.3) scaleX(1.1);
            animation: waveFlow 20s ease-in-out infinite;
        }

        .footer-section .purple-wave-2 {
            position: absolute;
            bottom: -15%;
            left: -5%;
            width: 110%;
            height: 110%;
            background: radial-gradient(
                ellipse 60% 70% at 30% 60%,
                rgba(186, 85, 211, 0.5) 0%,
                rgba(138, 43, 226, 0.3) 40%,
                rgba(75, 0, 130, 0.2) 70%,
                transparent 100%
            );
            filter: blur(60px);
            animation: waveFlow2 25s ease-in-out infinite;
        }

        .footer-section .purple-wave-3 {
            position: absolute;
            bottom: -10%;
            right: -5%;
            width: 90%;
            height: 90%;
            background: radial-gradient(
                ellipse 50% 60% at 70% 50%,
                rgba(147, 112, 219, 0.6) 0%,
                rgba(138, 43, 226, 0.4) 50%,
                transparent 100%
            );
            filter: blur(50px);
            animation: waveFlow3 18s ease-in-out infinite;
        }

        .footer-section .blend-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 15vh;
            background: linear-gradient(
                180deg,
                transparent 0%,
                rgba(245, 243, 240, 0.2) 20%,
                rgba(245, 243, 240, 0.5) 50%,
                rgba(245, 243, 240, 0.8) 80%,
                #f5f3f0 100%
            );
            pointer-events: none;
            z-index: 3;
        }

        .footer-section .organic-curve {
            position: absolute;
            bottom: -3vh;
            left: -8%;
            width: 35%;
            height: 18vh;
            background: #f5f3f0;
            border-radius: 50% 50% 0 0 / 60% 60% 0 0;
            transform: rotate(-12deg);
            z-index: 2;
            opacity: 0.95;
        }

        .footer-section .organic-curve-2 {
            position: absolute;
            bottom: -5vh;
            left: 8%;
            width: 28%;
            height: 22vh;
            background: #f5f3f0;
            border-radius: 50% 50% 0 0 / 70% 70% 0 0;
            transform: rotate(8deg);
            z-index: 2;
            opacity: 0.9;
        }

        /* Horizontal Transition Container */
        .horizontal-transition-wrapper {
            position: relative;
            width: 100vw;
            height: 100vh;
            will-change: transform;
            overflow: hidden;
            transform: translateZ(0); /* Force hardware acceleration */
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }

        .horizontal-transition-wrapper .editorial-section {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            will-change: transform;
            transform: translateZ(0); /* Force hardware acceleration */
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }

        .horizontal-transition-wrapper .footer-section {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            will-change: transform;
            transform: translateZ(0); /* Force hardware acceleration */
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }

        @media (max-width: 640px) {
            .editorial-content {
                padding: 4rem 1rem 12rem !important; /* Increased bottom padding significantly */
                gap: 2rem;
                position: relative;
            }
            
            .editorial-text-block {
                padding: 1rem;
                position: relative;
                z-index: 2; /* Ensure text is above wordmark */
            }
            
            .editorial-text-block p {
                font-size: 0.95rem;
                line-height: 1.6;
                position: relative;
                z-index: 2;
            }
            
            .editorial-bottom-wordmark {
                bottom: 5% !important; /* Moved up more to prevent overlap */
                right: 2% !important; /* Closer to edge but still visible */
                left: auto !important;
                font-size: clamp(2rem, 10vw, 4rem) !important; /* Much smaller to prevent overlap */
                opacity: 0.2 !important; /* More transparent */
                z-index: 0 !important; /* Behind content */
                max-width: 60% !important; /* Limit width */
                word-break: break-word !important;
            }
            
            .editorial-video-placeholder {
                margin: 1rem 0;
                position: relative;
                z-index: 2;
            }
            
            .editorial-video-placeholder video {
                max-width: 100%;
                height: auto;
            }
        }
        
        @media (max-width: 480px) {
            .editorial-content {
                padding: 3rem 0.75rem 14rem !important; /* Even more bottom padding */
            }
            
            .editorial-text-block {
                padding: 0.75rem;
            }
            
            .editorial-text-block p {
                font-size: 0.9rem;
                margin-bottom: 1.5rem; /* Extra spacing between paragraphs */
            }
            
            .editorial-bottom-wordmark {
                bottom: 8% !important; /* Moved up even more */
                right: 1% !important;
                font-size: clamp(1.5rem, 8vw, 3rem) !important; /* Smaller on very small screens */
                max-width: 50% !important; /* Even more limited width */
            }
        }
        
        /* Video Sections - fixed position, fullscreen */
        .video-section {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            overflow: hidden;
            opacity: 0;
            pointer-events: none;
            z-index: 0;
            visibility: hidden;
        }
        
        /* About section mobile — hidden on desktop, shown in 768px block */
        .about-section-mobile {
            display: none;
        }

        /* Spacer for video section to allow scrolling past it */
        .video-section-spacer {
            height: 100vh;
            width: 100%;
            position: relative;
        }
        
        .video-section.active {
            opacity: 1 !important;
            pointer-events: auto;
            z-index: 1;
            visibility: visible;
        }
        
        /* Ensure first video section is visible on load - override GSAP */
        #videoSection1.active {
            opacity: 1 !important;
            display: block !important;
            visibility: visible !important;
        }
        
        /* Hide video section when scrolled past */
        .video-section.scrolled-past {
            opacity: 0 !important;
            visibility: hidden !important;
            z-index: -1;
        }
        
        .video-wrapper {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 120%;
            height: 120%;
            transform: translate(-50%, -50%) scale(1);
            will-change: transform;
        }
        
        .video-section video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .video-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0);
            z-index: 1;
            will-change: background-color;
        }
        
        /* Make first video darker */
        #overlay1 {
            background: rgba(0, 0, 0, 0.35); /* Increased from 0.2 to 0.35 (15% more dark) */
        }
        
        /* Text Content - Jesko Jets Style Layout */
        .video-content {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
            pointer-events: none;
        }
        
        /* Left Side Large Text - "We are movement" style */
        .video-title-left {
            position: absolute;
            left: clamp(3rem, 6vw, 6rem);
            top: 17%; /* Moved up 3% more closer to navbar */
            transform: translateY(0);
            font-family: 'Geist', 'Barracuda', 'Ronzino', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            font-size: clamp(4rem, 12vw, 10rem);
            font-weight: 300;
            text-transform: none;
            color: #ffffff;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
            line-height: 1.05;
            margin: 0;
            padding: 0;
            text-align: left;
            width: auto;
            max-width: 45%;
            opacity: 1;
            will-change: opacity, transform;
            white-space: normal;
            letter-spacing: -0.02em;
        }
        
        /* Right Side Large Text - "We are distinction" style */
        .video-title-right {
            position: absolute;
            right: clamp(3rem, 6vw, 6rem);
            top: 59%; /* Moved down 1% from 58% */
            transform: translateY(0);
            font-family: 'Geist', 'Barracuda', 'Ronzino', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            font-size: clamp(3.04rem, 9.12vw, 7.6rem); /* 5% smaller: 3.2rem*0.95=3.04rem, 9.6vw*0.95=9.12vw, 8rem*0.95=7.6rem */
            font-weight: 300;
            text-transform: none;
            color: #ffffff;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
            line-height: 1.05;
            margin: 0;
            padding: 0;
            text-align: right;
            width: auto;
            max-width: 45%;
            opacity: 1;
            will-change: opacity, transform;
            white-space: normal;
            letter-spacing: -0.02em;
            padding-bottom: 0;
            margin-bottom: 0;
        }
        
        /* Bottom Left Supporting Text Block */
        .video-subtitle {
            position: absolute;
            left: clamp(3rem, 6vw, 6rem);
            bottom: clamp(6rem, 12vh, 10rem);
            font-family: 'Modern Society', 'Inter', sans-serif;
            font-size: clamp(0.9rem, 1.5vw, 1.2rem);
            font-weight: 400;
            color: #ffffff;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.4);
            max-width: clamp(300px, 40vw, 500px);
            line-height: 1.6;
            opacity: 1;
            will-change: opacity, transform;
        }
        
        .video-subtitle-title {
            font-family: 'Barracuda', 'Ronzino', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            font-size: clamp(1.2rem, 2vw, 1.8rem);
            font-weight: 400;
            margin-bottom: 0.8rem;
            display: block;
            letter-spacing: -0.01em;
        }
        
        .video-subtitle-text {
            font-family: 'Modern Society', 'Inter', sans-serif;
            font-size: clamp(0.85rem, 1.3vw, 1.1rem);
            font-weight: 400;
            line-height: 1.7;
            opacity: 0.9;
        }
        
        /* Bottom Right Scroll Hint */
        .video-scroll-hint {
            position: absolute;
            right: clamp(3rem, 6vw, 6rem);
            bottom: clamp(4rem, 10vh, 8rem);
            font-family: 'Inter', sans-serif;
            font-size: clamp(0.7rem, 1.2vw, 0.9rem);
            font-weight: 400;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            text-align: right;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.4);
            opacity: 0.8;
            line-height: 1.8;
            animation: waveScroll 2s ease-in-out infinite;
        }
        
        @keyframes waveScroll {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-8px);
            }
        }
        
        .video-scroll-hint-line {
            width: 60px;
            height: 1px;
            background-color: #ffffff;
            margin: 0.5rem 0 0.5rem auto;
            opacity: 0.6;
            animation: waveLine 2s ease-in-out infinite;
        }
        
        @keyframes waveLine {
            0%, 100% {
                transform: translateX(0);
                opacity: 0.6;
            }
            50% {
                transform: translateX(-5px);
                opacity: 0.9;
            }
        }

        @keyframes scrollBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }
        
        /* Hide text in inactive sections */
        .video-section:not(.active) .video-title-left,
        .video-section:not(.active) .video-title-right,
        .video-section:not(.active) .video-subtitle,
        .video-section:not(.active) .video-scroll-hint {
            opacity: 0;
        }
        
        
        .black-main-title {
            position: absolute;
            left: 2rem;
            top: 15%;
            font-size: clamp(3.5rem, 10vw, 8rem);
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: -0.02em;
            z-index: 3;
            font-family: 'Geist', 'Barracuda', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
        }
        
        .black-coordinates {
            position: absolute;
            left: 2rem;
            top: 45%;
            font-size: 0.975rem;
            font-family: 'VT323', monospace;
            line-height: 1.8;
            z-index: 3;
        }
        
        .black-subtitle {
            position: absolute;
            right: 2rem;
            top: 25%;
            font-size: clamp(0.9rem, 1.8vw, 1.3rem);
            font-weight: 400;
            text-align: right;
            padding: 0.5rem 1rem;
            z-index: 3;
            max-width: 400px;
        }
        
        .black-subtitle::before {
            content: '「';
            font-size: 1.2em;
            margin-right: 0.3em;
        }
        
        .black-subtitle::after {
            content: '」';
            font-size: 1.2em;
            margin-left: 0.3em;
        }
        
        .black-credits {
            position: absolute;
            left: 2rem;
            bottom: 10rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            z-index: 3;
        }
        
        .black-credits-name {
            font-size: 1.2rem;
            font-weight: 700;
            margin-top: 0.5rem;
            letter-spacing: 0.05em;
        }
        
        .black-barcode {
            position: absolute;
            left: 2rem;
            bottom: 5rem;
            width: 200px;
            height: 40px;
            background: repeating-linear-gradient(
                90deg,
                #ffffff 0px,
                #ffffff 2px,
                transparent 2px,
                transparent 4px,
                #ffffff 4px,
                #ffffff 6px,
                transparent 6px,
                transparent 8px,
                #ffffff 8px,
                #ffffff 10px,
                transparent 10px,
                transparent 12px
            );
            z-index: 3;
        }
        
        .black-info {
            position: absolute;
            right: 2rem;
            bottom: 5rem;
            font-size: 0.75rem;
            text-align: right;
            line-height: 2;
            z-index: 3;
        }
        
        .black-info-header {
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .black-info-item {
            margin-bottom: 0.3rem;
        }
        
        .black-info-divider {
            width: 100px;
            height: 1px;
            background-color: #ffffff;
            margin: 0.5rem 0 0.5rem auto;
        }

        /* Mobile-only year label (shown in 768px block); hidden on desktop */
        .black-info-mobile-year {
            display: none;
        }
        
        /* Legacy support - keep for backwards compatibility */
        .video-title {
            display: none;
        }
        
        /* Responsive adjustments to prevent overlap - non-fullscreen mode */
        @media (max-height: 1000px) {
            .video-title-right {
                top: 58% !important; /* Near scroll hint for non-fullscreen */
                max-height: 40vh; /* Limit height to prevent overlap */
                font-size: clamp(2.9rem, 8.7vw, 7.2rem) !important; /* Slightly smaller for non-fullscreen */
            }
            
            .video-scroll-hint {
                bottom: clamp(3rem, 8vh, 6rem) !important; /* Ensure enough space */
            }
        }
        
        @media (max-height: 900px) {
            .video-title-right {
                top: 57% !important; /* Near scroll hint for non-fullscreen */
                font-size: clamp(2.9rem, 8.7vw, 7.2rem) !important;
            }
            
            .video-scroll-hint {
                bottom: clamp(2.5rem, 7vh, 5rem) !important;
            }
        }
        
        @media (max-width: 1400px) and (max-height: 1000px) {
            .video-title-right {
                top: 58% !important; /* Near scroll hint for non-fullscreen */
                font-size: clamp(2.9rem, 8.7vw, 7.2rem) !important;
            }
            
            .video-scroll-hint {
                bottom: clamp(3rem, 8vh, 6rem) !important;
            }
        }
        
        /* Additional check for non-fullscreen windows */
        @media (max-height: 800px) {
            .video-title-right {
                top: 56% !important; /* Near scroll hint for non-fullscreen */
                font-size: clamp(2.9rem, 8.7vw, 7.2rem) !important;
            }
            
            .video-scroll-hint {
                bottom: clamp(2rem, 6vh, 4rem) !important;
            }
        }
        
        /* Hero hamburger: only visible on mobile (when header is hidden); desktop uses .hamburger-menu in header */
        .hero-hamburger {
            display: none;
        }

        /* Hamburger Menu Styles for Homepage */
        .hamburger-menu {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 0.5rem;
            background: none;
            border: 2px solid #ffffff;
            min-width: 44px;
            min-height: 44px;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease;
            pointer-events: auto;
        }
        
        .hamburger-menu:hover {
            background-color: #ffffff;
        }
        
        .hamburger-menu:hover .hamburger-line {
            background-color: #000000;
        }
        
        .hamburger-line {
            width: 24px;
            height: 3px;
            background-color: #ffffff;
            transition: all 0.3s ease;
        }
        
        .hamburger-menu.active .hamburger-line:nth-child(1) {
            transform: rotate(45deg) translate(7px, 7px);
        }
        
        .hamburger-menu.active .hamburger-line:nth-child(2) {
            opacity: 0;
        }
        
        .hamburger-menu.active .hamburger-line:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }
        
        /* Mobile Menu Overlay */
        .mobile-menu-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 9998;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .mobile-menu-overlay.active {
            display: block;
            opacity: 1;
        }
        
        .mobile-menu {
            position: fixed;
            top: 0;
            right: -100%;
            width: 80%;
            max-width: 320px;
            height: 100%;
            background-color: #ffffff;
            border-left: 3px solid #000000;
            z-index: 9999;
            transition: right 0.3s ease;
            overflow-y: auto;
            padding: 2rem 1.5rem;
            font-family: 'VT323', monospace;
            display: flex;
            flex-direction: column;
        }
        
        .mobile-menu.active {
            right: 0;
        }
        
        .mobile-menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #000000;
            flex-shrink: 0;
        }
        
        /* Full-screen slide-down menu: center nav vertically for Valiente-style layout */
        @media (max-width: 768px) {
            .mobile-menu-header {
                margin-bottom: 2rem;
            }
        }
        
        .mobile-menu-header .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #000000;
        }
        
        .mobile-menu-close {
            background: none;
            border: 2px solid #000000;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.5rem;
            color: #000000;
            transition: all 0.3s ease;
        }
        
        .mobile-menu-close:hover {
            background-color: #000000;
            color: #ffffff;
        }
        
        .mobile-menu-nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
            min-height: 0;
        }
        
        .mobile-menu-nav a {
            display: block;
            padding: 1rem;
            border: 2px solid #000000;
            color: #000000;
            text-decoration: none;
            font-size: 1.1rem;
            text-transform: uppercase;
            transition: all 0.3s ease;
            min-height: 44px;
            display: flex;
            align-items: center;
        }
        
        .mobile-menu-nav a:hover,
        .mobile-menu-nav a.active {
            background-color: #000000;
            color: #ffffff;
        }
        
        .mobile-menu-nav .login-btn {
            margin-top: 1rem;
        }
        
        .mobile-menu-footer {
            display: none;
        }
        
        /* Mobile-only: hidden on desktop (the black "FarmScout Online™" + tagline is inside .mobile-hero-text-overlay) */
        .mobile-hero-video-box,
        .mobile-hero-text-overlay,
        .hero-top-left,
        .hero-top-right {
            display: none;
        }
        
        /* Desktop only: hero text in .video-content must stay white (never show mobile black text on desktop) */
        @media (min-width: 769px) {
            .video-content .black-main-title,
            .video-content .black-subtitle,
            .video-content .black-coordinates,
            .video-content .black-credits,
            .video-content .black-info,
            .video-content .black-info-header,
            .video-content .black-info-item {
                color: #ffffff !important;
            }
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            /* Mobile: allow document scroll so user can scroll to about section */
            html {
                overflow-x: hidden;
                overflow-y: auto;
                scroll-behavior: smooth;
                height: auto;
                min-height: 100%;
            }
            body {
                margin: 0;
                min-height: 100%;
                height: auto;
                overflow-x: hidden !important;
                overflow-y: scroll !important; /* force scroll so next section is reachable */
                -webkit-overflow-scrolling: touch;
                background: #dddbd6;
            }

            /* Hide these on mobile; do NOT hide .video-section-spacer or .about-section-mobile (needed for scroll) */
            .header,
            .horizontal-transition-wrapper,
            .editorial-section,
            .footer-section {
                display: none !important;
            }

            /* Full-screen overlay that slides down from top (Valiente-style) */
            .mobile-menu-overlay {
                display: none !important;
            }
            .mobile-menu {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                max-width: none;
                transform: translateY(-100%);
                transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 10001;
                background-color: #ffffff;
                border-left: none;
                display: flex;
                flex-direction: column;
                overflow-y: auto;
                padding: 0;
                pointer-events: none;
                right: auto;
            }
            .mobile-menu.active {
                transform: translateY(0);
                pointer-events: auto;
            }
            /* Valiente-style layout: header = close only (top-right), nav = large stacked links, footer = branding */
            .mobile-menu-header {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                justify-content: flex-end;
                padding: 1.5rem 1.5rem 0;
                margin-bottom: 0;
                padding-bottom: 0;
                border-bottom: none;
            }
            .mobile-menu-header .logo {
                display: none;
            }
            .mobile-menu-close {
                position: relative;
                top: 0;
                right: 0;
            }
            .mobile-menu-nav {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: flex-start;
                padding: 4rem 1.5rem 3rem;
                gap: 1.25rem;
            }
            .mobile-menu-nav a,
            .mobile-menu-nav .account-dropdown-toggle,
            .mobile-menu-nav .login-btn,
            .mobile-menu-nav .notification-bell {
                display: block;
                width: 100%;
                border: none;
                padding: 0;
                margin: 0;
                min-height: auto;
                font-size: clamp(1.75rem, 6vw, 2.5rem);
                line-height: 1.2;
                letter-spacing: -0.03em;
                text-transform: uppercase;
                text-align: left;
                color: #000000;
            }
            .mobile-menu-nav .notification-bell {
                background: transparent;
                appearance: none;
                -webkit-appearance: none;
                font-family: inherit;
                cursor: pointer;
            }
            .mobile-menu-nav .login-btn {
                margin-top: 0;
            }
            .mobile-menu-nav a:hover {
                background-color: transparent;
                color: #000000;
            }
            .mobile-menu-nav a.active {
                background-color: transparent;
                color: #000000;
                text-decoration: underline;
                text-underline-offset: 0.25em;
            }
            .mobile-menu-nav .account-dropdown {
                margin-top: 0;
                width: 100%;
            }
            .mobile-menu-nav .account-dropdown-toggle {
                margin-top: 0;
            }
            .mobile-menu-nav .account-dropdown-menu {
                position: static !important;
                opacity: 1 !important;
                visibility: visible !important;
                transform: none !important;
                box-shadow: none !important;
                background: transparent !important;
                border: none !important;
                border-top: 2px solid var(--border-color, #000000) !important;
                margin-top: 0.5rem !important;
                min-width: 0 !important;
                padding-top: 0.75rem !important;
                display: block !important;
            }
            .mobile-menu-nav .account-dropdown-menu a {
                font-size: clamp(1.75rem, 6vw, 2.5rem);
                line-height: 1.2;
                letter-spacing: -0.03em;
                padding: 0;
                border: none !important;
                border-bottom: none !important;
                min-height: auto;
            }
            .mobile-menu-footer {
                display: block;
                flex-shrink: 0;
                padding: 2rem 1.5rem 2.5rem;
                border-top: none;
            }
            .mobile-menu-footer-brand {
                font-size: clamp(1.5rem, 5vw, 2rem);
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 0.02em;
                margin-bottom: 0.75rem;
            }
            .mobile-menu-footer-meta {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .mobile-menu-footer-meta a {
                text-decoration: none;
            }
            /* Hide hero hamburger when menu is open – use × in panel to close */
            .hero-hamburger.active {
                visibility: hidden;
                pointer-events: none;
            }

            /* Scroll container: natural height so scroll distance is reasonable */
            .scroll-container {
                position: relative;
                width: 100%;
                min-height: auto !important;
                overflow: visible;
            }
            .scroll-container > .video-section-spacer {
                display: block !important;
                height: 25vh !important;
                min-height: 25vh !important;
                background: #dddbd6;
                margin: 0;
                padding: 0;
            }
            .scroll-container > .about-section-mobile {
                display: block !important;
                min-height: 100vh !important;
            }
            /* Keep GSAP pin spacer from adding extra height on mobile */
            .scroll-container .pin-spacer {
                display: none !important;
            }

            /* About section mobile — Valiente-style layout (red text, light bg, black bottom headline) */
            .about-section-mobile {
                background: #dddbd6;
                min-height: 140vh;
                padding: 0;
                position: relative;
            }
            .about-mobile-inner {
                max-width: none;
                margin: 0 auto;
                padding: 2rem 1.5rem 8rem; /* extra bottom room so last line can reveal */
            }
            .about-mobile-hero {
                font-family: 'Geist', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                font-size: clamp(0.85rem, 2.2vw, 0.98rem);
                font-weight: 400;
                line-height: 1.45;
                letter-spacing: -0.01em;
                text-transform: uppercase;
                color: #000000;
                margin: 0 0 1.1rem;
                opacity: 1;
                transform: none;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
            .about-mobile-hero-line {
                display: block;
                opacity: 0;
                transform: translateY(40px);
            }
            .about-mobile-text-block {
                margin-top: 0;
                max-width: 360px;
            }
            .about-mobile-p {
                font-family: 'Geist', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                font-size: clamp(0.78rem, 2vw, 0.95rem);
                font-weight: 400;
                line-height: 1.45;
                letter-spacing: -0.01em;
                text-transform: uppercase;
                color: #000000;
                margin: 0 0 0.8rem;
                opacity: 0;
                transform: translateY(30px);
            }
            .about-mobile-p:last-of-type {
                margin-bottom: 0;
            }
            .about-mobile-bottom {
                font-family: 'Barracuda', 'Geist', 'Inter', sans-serif;
                font-size: clamp(2.5rem, 12vw, 5rem);
                font-weight: 700;
                line-height: 0.95;
                letter-spacing: -0.03em;
                text-transform: uppercase;
                color: #000000;
                margin: 3rem 0 0;
                opacity: 0;
                transform: translateY(40px);
            }
            .about-mobile-video-box {
                margin: 0;
                border: none;
                background: #dddbd6;
                width: 100vw;
                height: 50vh;
                min-height: 46vh;
                max-height: 56vh;
                overflow: hidden;
                position: relative;
                left: 50%;
                transform: translateX(-50%);
            }
            .about-mobile-video {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }
            .about-mobile-video-overlay {
                position: absolute;
                inset: 0;
                display: flex;
                flex-direction: column;
                justify-content: flex-end;
                align-items: flex-start;
                padding: 1.25rem; /* ~20px safe padding */
                pointer-events: none;
            }
            .about-mobile-video-text {
                font-family: 'Barracuda', 'Geist', 'Inter', sans-serif;
                font-size: clamp(2.4rem, 11vw, 4.6rem);
                font-weight: 700;
                line-height: 0.92;
                letter-spacing: -0.04em;
                text-transform: uppercase;
                color: #ffffff;
                text-shadow: 0 2px 8px rgba(0, 0, 0, 0.45);
            }

            .video-section,
            .video-section.active {
                position: relative !important;
                top: auto;
                left: auto;
                width: 100%;
                height: 100vh;
                min-height: 100vh;
                opacity: 1 !important;
                visibility: visible !important;
                pointer-events: auto !important;
                z-index: 1;
                background: #dddbd6;
            }

            .video-wrapper {
                width: 100%;
                height: 100%;
                transform: translate(-50%, -50%) scale(1);
            }

            .video-section video {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .video-overlay {
                display: block !important;
                background: rgba(0, 0, 0, 0.25);
                pointer-events: none;
            }

            .video-content {
                display: flex !important;
                z-index: 4;
            }

            .black-main-title,
            .black-subtitle,
            .black-coordinates,
            .black-info {
                color: #000000 !important;
            }

            .video-content::before {
                content: "FARMSCOUT";
                position: absolute;
                top: 1.25rem;
                left: 1.25rem;
                font-size: 0.85rem;
                font-weight: 700;
                letter-spacing: 0.08em;
                color: #000000;
                text-transform: uppercase;
            }

            .video-content::after {
                content: "";
                position: absolute;
                top: 1rem;
                right: 1.25rem;
                width: 26px;
                height: 2px;
                background-color: #000000;
                box-shadow: 0 7px 0 #000000, 0 14px 0 #000000;
            }

            .video-content::after {
                display: none;
            }

            .hero-hamburger {
                position: fixed;
                top: 1rem;
                right: 1rem;
                width: 44px;
                height: 44px;
                min-width: 44px;
                min-height: 44px;
                border: 2px solid #000000;
                background: rgba(255,255,255,0.9);
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                gap: 5px;
                padding: 4px;
                cursor: pointer;
                z-index: 10001;
                pointer-events: auto;
                -webkit-tap-highlight-color: transparent;
                touch-action: manipulation;
            }

            .hero-hamburger span {
                width: 18px;
                height: 2px;
                background-color: #000000;
                display: block;
            }

            .header {
                padding: 1rem;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                gap: 1rem;
            }
            
            .logo {
                font-size: 1.5rem;
            }
            
            .logo img {
                height: 1.5rem;
            }
            
            /* Hide regular nav on mobile */
            .nav {
                display: none;
            }
            
            /* Show hamburger menu */
            .hamburger-menu {
                display: flex;
            }
            
            .video-title {
                font-size: clamp(2rem, 6vw, 4rem);
            }
            
            .video-subtitle {
                font-size: clamp(0.9rem, 2vw, 1.2rem);
            }
            
            .video-title-right {
                top: 35%; /* Further adjustment for mobile */
            }
            
            .video-scroll-hint {
                bottom: clamp(2rem, 8vh, 4rem);
            }

            /* Keep hero text readable on narrow screens */
            .black-main-title,
            .black-subtitle,
            .black-coordinates,
            .black-info {
                overflow-wrap: break-word;
                word-break: break-word;
            }

            .video-wrapper {
                width: 100%;
                height: 100%;
                transform: translate(-50%, -50%) scale(1);
            }

            .black-info {
                font-size: 0.9rem;
                line-height: 1.6;
                right: 1rem;
                bottom: 4rem;
            }

            .black-info-header,
            .black-info-item {
                margin-bottom: 0.5rem;
            }

            .black-credits {
                left: 1rem;
                bottom: 7rem;
            }

            .black-barcode {
                left: 1rem;
                bottom: 3.5rem;
                width: 160px;
                height: 32px;
            }
            
            .search-form input {
                min-height: 44px; /* Touch-friendly */
            }
            
            .search-form button {
                min-height: 44px; /* Touch-friendly */
                min-width: 44px;
            }
            
            /* Editorial section mobile improvements */
            .editorial-section {
                min-height: auto; /* Allow natural height on mobile */
            }
            
            .black-main-title {
                font-size: clamp(2.5rem, 8vw, 6rem) !important;
            }
            
            .black-coordinates {
                font-size: 0.875rem !important; /* Slightly smaller on mobile */
            }
            
            .black-subtitle {
                font-size: clamp(0.8rem, 2vw, 1.1rem) !important;
                max-width: 90% !important;
            }

            /* Hero container: relative + height so overlay centering works */
            .video-content {
                position: relative;
                height: 100vh;
                min-height: 100vh;
            }

            /* Reset desktop positioning for mobile — but NOT .black-coordinates / .black-info (they stay absolute, below video) */
            .black-main-title,
            .black-subtitle,
            .black-credits,
            .black-barcode {
                position: static !important;
                left: auto !important;
                right: auto !important;
                top: auto !important;
                bottom: auto !important;
                transform: none !important;
                max-width: 100% !important;
            }

            /* Hide original hero text on mobile — use overlay and corner layout */
            .video-content .black-main-title,
            .video-content .black-subtitle {
                display: none !important;
            }

            /* Valiente-style: BRAVERY IN PRACTICE + SCROLL TO VIEW MORE — theme font */
            .hero-top-left,
            .hero-top-right {
                display: block;
                position: absolute;
                bottom: calc(7.5rem + 62.5vw + 0.25rem); /* just above video (video at 7.5rem) */
                top: auto;
                z-index: 3;
                font-size: 0.7rem;
                font-family: 'VT323', monospace !important;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: #000000;
            }
            .hero-top-left {
                left: 1.5rem;
            }
            .hero-top-right {
                right: 1.5rem;
                text-align: right;
                animation: scrollBounce 1.5s ease-in-out infinite;
            }

            /* Hero video container — raised so text below fits in viewport and isn’t clipped */
            .mobile-hero-video-box {
                display: block;
                order: 3;
                position: absolute !important;
                bottom: 7.5rem !important;
                left: 0 !important;
                right: 0 !important;
                top: auto !important;
                width: 100% !important;
                max-width: none;
                margin: 0;
                aspect-ratio: 16 / 10;
                overflow: hidden;
                border: 2px solid #000000;
                border-left: none;
                border-right: none;
                background: #000000;
                pointer-events: none;
            }
            .mobile-hero-video-box-video {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            /* Hero text overlay: positioned relative to hero section; moved up ~3% (text only) */
            .mobile-hero-text-overlay {
                position: absolute;
                top: calc(50% - 3vh);
                left: 50%;
                transform: translate(-50%, -50%);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                width: 90%;
                max-width: 90%;
                z-index: 2;
                pointer-events: none;
            }
            .mobile-hero-title,
            .mobile-hero-subtitle {
                display: block !important;
                margin: 0;
            }
            /* Valiente-style: bold, uppercase, tight letter-spacing, black */
            .mobile-hero-title {
                font-size: clamp(2.5rem, 9vw, 4rem);
                font-weight: 700;
                line-height: 1.2;
                letter-spacing: -0.03em;
                text-transform: uppercase;
                color: #000000;
                font-family: 'Geist', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                margin-bottom: 0.5rem;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
            .mobile-hero-subtitle {
                font-size: clamp(0.9rem, 3.2vw, 1.1rem);
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: #000000;
                font-family: 'VT323', monospace !important;
            }

            /* Text BELOW video — same row; closer to video but not too close */
            .black-coordinates {
                position: absolute !important;
                left: 1.5rem !important;
                top: auto !important;
                bottom: calc(6rem + 0.25rem) !important;
                font-size: 0.7rem !important;
                font-family: 'VT323', monospace !important;
                line-height: 1.4 !important; /* override inline 1.8 so matches .black-info */
                text-transform: uppercase;
                letter-spacing: 0.08em;
                z-index: 3;
            }

            .black-info {
                position: absolute !important;
                right: 2.5rem !important; /* bring inward so gap matches “BRAVERY IN PRACTICE” / “SCROLL TO VIEW MORE” */
                top: auto !important;
                bottom: calc(6rem + 0.25rem) !important;
                font-size: 0.7rem !important;
                font-family: 'VT323', monospace !important;
                line-height: 1.4 !important; /* override inline 2 so same row as .black-coordinates */
                text-transform: uppercase;
                letter-spacing: 0.08em;
                text-align: right;
                z-index: 3;
            }

            .black-info-divider {
                display: none;
            }

            .black-credits,
            .black-barcode {
                display: none;
            }

            /* Valiente-style: text BELOW video (bottom-left, bottom-right) */
            .black-coordinates {
                color: #000000;
            }

            .black-coordinates::after {
                display: none; /* "BRAVERY IN PRACTICE" moved to .hero-top-left */
            }

            .black-coordinates br {
                display: none;
            }

            .black-info-header,
            .black-info-item {
                display: none;
            }

            /* Show mobile year as real DOM content; same line-height so it aligns with left text */
            .black-info-mobile-year {
                display: block !important;
                color: #000000;
                font-size: inherit;
                line-height: 1.4 !important;
                margin: 0;
                padding: 0;
            }

            .black-info::after {
                content: none;
                display: none;
            }

            /* Simplify hero background on mobile */
            .video-section {
                background: #e7e2db;
            }

            .video-wrapper,
            .video-section video,
            .video-overlay {
                display: none !important;
            }

            /* Keep mobile hero video box visible (override .video-section video { display: none }) */
            .video-section .mobile-hero-video-box,
            .video-section .mobile-hero-video-box .mobile-hero-video-box-video {
                display: block !important;
            }
        }
        
        @media (max-width: 480px) {
            .header {
                padding: 0.75rem;
            }
            
            .logo {
                font-size: 1.25rem;
            }
            
            .nav {
                flex-direction: column;
                width: 100%;
            }
            
            .nav a {
                width: 100%;
                justify-content: center;
            }
            
            .video-title-left,
            .video-title-right {
                max-width: 90%;
                left: 5%;
                right: 5%;
            }
            
            /* Keep full viewport hero on small mobile */
            .video-content {
                padding: 0;
            }

            .mobile-hero-title {
                font-size: clamp(2.2rem, 10vw, 3.6rem) !important;
            }

            .mobile-hero-subtitle {
                font-size: clamp(0.85rem, 3.4vw, 1rem) !important;
            }

            /* Keep same size as top pair (BRAVERY IN PRACTICE / SCROLL TO VIEW MORE) */
            .black-coordinates,
            .black-info {
                font-size: 0.7rem !important;
            }
        }

        /* Born&Bred-style first section */
        .header,
        .hero-hamburger,
        .mobile-menu-overlay,
        #mobileMenu {
            display: none !important;
        }

        #videoSection1 {
            background: #ececec;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 0.7rem;
        }

        #videoSection1 .video-wrapper {
            position: relative;
            width: min(99vw, 1540px);
            height: min(95vh, 900px);
            border: 1px solid rgba(0, 0, 0, 0.12);
            border-radius: 8px;
            overflow: hidden;
            background: #dddddd;
        }

        #videoSection1 .video-overlay {
            background: rgba(255, 255, 255, 0.08);
        }

        #videoSection1 .video-content {
            position: absolute;
            inset: 0;
            z-index: 3;
            padding: 0;
        }

        .bb-topbar {
            position: absolute;
            left: 1rem;
            right: 1rem;
            top: 0.8rem;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 1rem;
            z-index: 4;
            font-family: 'Inter Display', 'Inter', sans-serif;
            font-size: 0.8rem;
            letter-spacing: 0.04em;
            color: #111;
            text-transform: uppercase;
        }

        .bb-logo {
            font-size: 2rem;
            line-height: 1;
            color: #ef130f;
            font-weight: 700;
            transform: rotate(-12deg);
        }

        .bb-top-copy {
            text-align: center;
            font-weight: 600;
        }

        .bb-top-right {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }

        .bb-work-btn {
            border: none;
            border-radius: 999px;
            background: #ef130f;
            color: #fff;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 0.5rem 1rem;
        }

        .bb-menu {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #111;
        }

        .bb-menu::before {
            content: "\2261  ";
            font-weight: 700;
        }

        .bb-title {
            position: absolute;
            left: 0.9rem;
            right: 0.9rem;
            top: 2.45rem;
            z-index: 3;
            font-family: 'Inter Display', 'Inter', sans-serif;
            color: #a5a5a5;
            font-size: clamp(4rem, 15vw, 11rem);
            font-weight: 700;
            line-height: 0.88;
            letter-spacing: -0.03em;
            text-transform: none;
        }

        .bb-media {
            position: absolute;
            left: 0.9rem;
            right: 0.9rem;
            top: 11.2rem;
            bottom: 0.9rem;
            border-radius: 6px;
            overflow: hidden;
            background:
                linear-gradient(rgba(255,255,255,0.24), rgba(255,255,255,0.24)),
                radial-gradient(circle at 50% 10%, rgba(255,214,180,0.4), rgba(0,0,0,0.12) 60%),
                linear-gradient(120deg, #8a664d, #d7b3a6 35%, #8a796f 70%, #5f5d5d 100%);
        }

        .bb-media::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.18), rgba(0,0,0,0.02));
        }

        @media (max-width: 900px) {
            #videoSection1 .video-wrapper {
                width: 100%;
                height: 100%;
                border-radius: 0;
                border: none;
            }

            .bb-topbar {
                grid-template-columns: auto 1fr;
                grid-template-areas:
                    "logo right"
                    "copy copy";
                row-gap: 0.5rem;
            }

            .bb-logo { grid-area: logo; }
            .bb-top-right { grid-area: right; justify-self: end; }
            .bb-top-copy { grid-area: copy; font-size: 0.68rem; }

            .bb-title {
                top: 4.6rem;
                font-size: clamp(3.4rem, 18vw, 7rem);
            }

            .bb-media {
                top: 10.4rem;
            }
        }

        /* ═══════════════════════════════════════════════════════════
           NEW HERO SECTION — FarmScout first section
           All rules scoped so they don't break sections below
        ═══════════════════════════════════════════════════════════ */

        @font-face {
            font-family: 'InterDisplay';
            src: url('assets/fonts/Inter-4.1/extras/otf/InterDisplay-Bold.otf') format('opentype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }

        /* Override body for new hero */
        body { background: #ffffff; }

        /* ── CURTAIN ─────────────────────────────────────────────── */
        #fsCurtain {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            pointer-events: none;
        }
        .fs-curtain-panel {
            flex: none;
            width: calc(100vw / 4 + 2px);
            height: 100%;
            background: #0a0a0a;
            transform-origin: top center;
            will-change: transform;
            margin-right: -2px;
        }

        /* ── MENU PANEL ──────────────────────────────────────────── */
        #fsMenuPanel {
            position: fixed;
            inset: 0;
            z-index: 9998;
            background: #0a0a0a;
            display: flex;
            flex-direction: column;
            transform: translateY(-100%);
            pointer-events: none;
            will-change: transform;
            font-family: 'InterDisplay', system-ui, sans-serif;
        }
        #fsMenuPanel.is-open { pointer-events: auto; }
        .fs-mp-inner {
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 1.1rem 2rem 2rem;
        }
        .fs-mp-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 1.2rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .fs-mp-logo {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            text-decoration: none;
        }
        .fs-mp-logo img {
            height: 36px; width: 36px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }
        .fs-mp-logo span {
            font-size: 1.1rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.01em;
        }
        .fs-mp-close {
            position: relative;
            width: 2.6rem; height: 2.6rem;
            background: none; border: none;
            cursor: pointer; padding: 0;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .fs-mp-close span {
            position: absolute;
            width: 1.4rem; height: 2px;
            background: #ffffff;
            border-radius: 2px;
            transition: background 0.2s;
        }
        .fs-mp-close span:nth-child(1) { transform: rotate(45deg); }
        .fs-mp-close span:nth-child(2) { transform: rotate(-45deg); }
        .fs-mp-close:hover span { background: #aaaaaa; }
        .fs-mp-nav {
            flex: 1;
            display: flex; flex-direction: column;
            justify-content: center;
            padding: 1.5rem 0;
            overflow: hidden;
        }
        .fs-mp-link-wrap { overflow: hidden; }
        .fs-mp-link {
            display: block;
            font-size: clamp(2.8rem, 7.5vw, 5.5rem);
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -0.03em;
            color: #ffffff;
            text-decoration: none;
            line-height: 1.08;
            transform: translateY(105%);
            transition: color 0.22s ease;
        }
        .fs-mp-link:hover { color: rgba(255,255,255,0.45); }
        .fs-mp-footer {
            border-top: 1px solid rgba(255,255,255,0.08);
            padding-top: 1.4rem;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            opacity: 0;
        }
        .fs-mp-footer-brand {
            font-size: 0.9rem; font-weight: 700;
            color: #ffffff;
            text-transform: uppercase; letter-spacing: 0.1em;
        }
        .fs-mp-footer-meta {
            display: flex; gap: 2rem;
            font-size: 0.68rem;
            color: rgba(255,255,255,0.35);
            text-transform: uppercase; letter-spacing: 0.08em;
        }

        /* ── NAV ─────────────────────────────────────────────────── */
        .fs-nav {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.6rem 1.4rem;
            background: #ffffff;
            font-family: 'InterDisplay', system-ui, sans-serif;
        }
        .fs-nav-logo {
            display: flex; align-items: center; gap: 0.5rem;
            text-decoration: none;
        }
        .fs-nav-logo img {
            height: 2rem; width: auto;
            object-fit: contain;
        }
        .fs-nav-logo-text {
            font-family: 'InterDisplay', system-ui, sans-serif;
            font-size: 1.1rem; font-weight: 800;
            color: #000000; letter-spacing: -0.02em; line-height: 1;
        }
        .fs-nav-tagline {
            font-family: 'InterDisplay', system-ui, sans-serif;
            font-size: 0.73rem; font-weight: 600;
            letter-spacing: 0.07em; text-transform: uppercase;
            color: #111;
            position: absolute; left: 50%; transform: translateX(-50%);
        }
        .fs-nav-right {
            display: flex; align-items: center; gap: 1rem;
        }
        .fs-nav-menu-wrap {
            display: flex; flex-direction: row; align-items: center; gap: 0.45rem;
        }
        .fs-nav-menu-label {
            font-size: 0.62rem; font-weight: 700;
            letter-spacing: 0.16em; text-transform: uppercase;
            color: #111;
            opacity: 0; transform: translateX(-6px);
            transition: opacity 0.22s ease-out, transform 0.22s ease-out;
            user-select: none;
        }
        .fs-nav-menu-wrap:hover .fs-nav-menu-label {
            opacity: 1; transform: translateX(0);
        }
        .fs-nav-menu {
            display: flex; flex-direction: column;
            justify-content: center; align-items: center; gap: 5px;
            width: 2.5rem; height: 2.5rem;
            background: none; border: none; cursor: pointer; padding: 0;
            color: #111; transition: color 0.2s;
        }
        .fs-nav-menu span {
            display: block; width: 1.5rem; height: 2px;
            background: currentColor; border-radius: 2px;
        }
        .fs-nav-menu-wrap:hover .fs-nav-menu { color: #1a5c2a; }

        /* ── HERO ────────────────────────────────────────────────── */
        .fs-hero {
            display: flex;
            flex-direction: column;
            width: 100%;
            height: calc(100vh - 54px); /* 54px = fs-nav approx height */
            padding: 0 1.5%;
            background: #ffffff;
            font-family: 'InterDisplay', system-ui, sans-serif;
        }
        .fs-hero-content {
            display: flex; flex-direction: column;
            flex: 1; min-height: 0;
        }
        .fs-title-wrap {
            display: block; flex-shrink: 0;
            width: 100%; margin: 0; padding: 0;
            line-height: 0; overflow: hidden; text-align: center;
        }
        .fs-brand-title {
            display: block; width: 100%; margin: 0; padding: 0;
            font-size: 15.5vw; font-weight: 900; line-height: 1;
            letter-spacing: -0.02em; text-transform: uppercase;
            white-space: nowrap; color: transparent;
            background: linear-gradient(105deg,
                #4a0e08 0%, #9e1e0e 20%, #c03818 40%,
                #b04030 60%, #7a1810 80%, #3d0908 100%);
            background-size: 100% 100%; background-position: 0 0;
            -webkit-background-clip: text; background-clip: text;
            user-select: none;
            transform: translateY(115%); /* hidden below clip container */
        }
        .fs-video-wrap {
            position: relative; flex-grow: 1;
            width: 100%; min-height: 0;
            margin: 0; padding: 0;
            overflow: hidden; border-radius: 10px;
            transform: scaleX(0) scaleY(0);
            transform-origin: center center;
        }
        .fs-hero-video {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            object-fit: cover; opacity: 0;
            transition: opacity 1.2s ease;
        }
        .fs-hero-video.active { opacity: 1; }
        #fsFrameCanvas { display: none; }

        @media (max-width: 768px) {
            .fs-nav-tagline { display: none; }
        }
        /* ══════════════════════════════════════════════════════════ */
        
    </style>

    <!-- Homepage re-skin (storefront / minimal, like reference screenshot) -->
    <style id="fs-home-storefront">
        :root{
            --fs-bg: #f4f1ea;
            --fs-surface: #ece8e0;
            --fs-surface-2: #e8e3da;
            --fs-ink: #111111;
            --fs-muted: rgba(17,17,17,.62);
            --fs-border: rgba(17,17,17,.12);
            --fs-accent: #f6c300; /* warm market highlight */
            --fs-radius: 18px;
        }

        /* Override the older “comic/video” baseline styles without deleting them. */
        body.fs-home-min {
            background: var(--fs-bg) !important;
            color: var(--fs-ink) !important;
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif !important;
            overflow-x: hidden;
        }

        .fs-home-min a { color: inherit; }

        .fs-sf-wrap{
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .fs-sf-nav{
            position: sticky;
            top: 0;
            z-index: 20;
            background: var(--fs-bg);
            border-bottom: 1px solid transparent;
        }

        .fs-sf-nav-inner{
            width: min(1280px, calc(100% - 48px));
            margin: 0 auto;
            padding: 18px 0;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 18px;
        }

        .fs-sf-nav-left,
        .fs-sf-nav-right{
            display: flex;
            align-items: center;
            gap: 18px;
            min-width: 0;
        }

        .fs-sf-nav-left a{
            text-decoration: none;
            font-size: 14px;
            letter-spacing: .01em;
            color: rgba(17,17,17,.82);
            white-space: nowrap;
        }
        .fs-sf-nav-left a:hover{ color: var(--fs-ink); }

        .fs-sf-brand{
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-weight: 800;
            letter-spacing: .02em;
        }
        .fs-sf-brand-mark{
            width: 28px; height: 28px;
            border-radius: 8px;
            background: var(--fs-ink);
            display: inline-grid;
            place-items: center;
            color: var(--fs-bg);
            font-size: 14px;
            line-height: 1;
        }

        .fs-sf-search{
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            min-width: 0;
        }
        .fs-sf-search input{
            width: min(360px, 42vw);
            max-width: 360px;
            background: rgba(255,255,255,.55);
            border: 1px solid var(--fs-border);
            border-radius: 999px;
            padding: 10px 14px;
            font-size: 14px;
            outline: none;
        }
        .fs-sf-search input:focus{
            border-color: rgba(17,17,17,.28);
            background: rgba(255,255,255,.8);
        }

        .fs-sf-nav-icons{
            display: inline-flex;
            align-items: center;
            gap: 14px;
            color: rgba(17,17,17,.75);
            font-size: 14px;
            white-space: nowrap;
        }

        .fs-sf-main{
            flex: 1;
            width: min(1280px, calc(100% - 48px));
            margin: 0 auto;
            padding: clamp(28px, 6vh, 68px) 0 clamp(36px, 7vh, 92px);
        }

        .fs-sf-hero h1{
            margin: 0;
            font-weight: 900;
            letter-spacing: -0.04em;
            line-height: .92;
            font-size: clamp(54px, 8.5vw, 124px);
        }

        .fs-sf-grid{
            margin-top: clamp(26px, 4vh, 40px);
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .fs-sf-card{
            background: var(--fs-surface);
            border: 1px solid var(--fs-border);
            border-radius: var(--fs-radius);
            padding: 22px;
            min-height: 220px;
            display: grid;
            grid-template-rows: 1fr auto;
            text-decoration: none;
            transition: transform .15s ease, background .15s ease, border-color .15s ease;
        }
        .fs-sf-card:hover{
            transform: translateY(-2px);
            background: rgba(255,255,255,.38);
            border-color: rgba(17,17,17,.2);
        }

        .fs-sf-card--accent{
            background: var(--fs-accent);
            border-color: rgba(17,17,17,.18);
        }
        .fs-sf-card--accent:hover{
            background: #ffd95a;
        }

        .fs-sf-card-top{
            display: grid;
            align-content: start;
            gap: 14px;
        }

        .fs-sf-card-title{
            font-size: 14px;
            color: rgba(17,17,17,.78);
        }

        .fs-sf-illus{
            width: 100%;
            display: grid;
            place-items: center;
            padding: 10px 0 0;
            color: rgba(17,17,17,.55);
        }
        .fs-sf-illus svg{
            width: min(220px, 100%);
            height: auto;
        }

        .fs-sf-card-link{
            font-size: 12px;
            color: rgba(17,17,17,.68);
            letter-spacing: .04em;
        }

        .fs-sf-footer{
            border-top: 1px solid rgba(17,17,17,.08);
            background: var(--fs-bg);
        }
        .fs-sf-footer-inner{
            width: min(1280px, calc(100% - 48px));
            margin: 0 auto;
            padding: 18px 0 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            color: rgba(17,17,17,.62);
            font-size: 13px;
        }

        @media (max-width: 980px){
            .fs-sf-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        /* Hide the old homepage (video/comic) when storefront skin is active */
        body.fs-home-min #fsCurtain,
        body.fs-home-min #fsMenuPanel,
        body.fs-home-min .fs-nav,
        body.fs-home-min .fs-hero,
        body.fs-home-min #fsFrameCanvas,
        body.fs-home-min .scroll-container{
            display: none !important;
            visibility: hidden !important;
        }

        @media (max-width: 680px){
            .fs-sf-nav-inner{
                grid-template-columns: 1fr auto;
                grid-template-areas:
                    "brand brand"
                    "left right";
                row-gap: 12px;
            }
            .fs-sf-brand{ grid-area: brand; justify-self: start; }
            .fs-sf-nav-left{ grid-area: left; gap: 12px; overflow: auto; }
            .fs-sf-nav-right{ grid-area: right; justify-self: end; }
            .fs-sf-search input{ width: 46vw; }
            .fs-sf-grid{ grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="fs-home-min">

    <!-- Storefront-style homepage (matches the provided reference layout) -->
    <div class="fs-sf-wrap">
        <nav class="fs-sf-nav" aria-label="Primary">
            <div class="fs-sf-nav-inner">
                <div class="fs-sf-nav-left">
                    <a href="market-finder.php">Shop</a>
                    <a href="categories.php">Category</a>
                    <a href="price-alerts.php">Featured</a>
                    <a href="price-history.php">Price</a>
            </div>

                <a class="fs-sf-brand" href="index.php" aria-label="FarmScout home">
                    <span class="fs-sf-brand-mark" aria-hidden="true">F</span>
                </a>

                <div class="fs-sf-nav-right">
                    <form class="fs-sf-search" action="categories.php" method="get" role="search" aria-label="Search products">
                        <input name="q" type="search" placeholder="Search product…" autocomplete="off" />
                    </form>
                    <div class="fs-sf-nav-icons" aria-label="Quick actions">
                        <span>₱ PHP</span>
                        <span>Trade</span>
                        <?php if (isLoggedIn()): ?>
                            <a href="user-account.php" style="text-decoration:none;">Account</a>
                        <?php else: ?>
                            <a href="login.php" style="text-decoration:none;">Login</a>
                        <?php endif; ?>
            </div>
        </div>
            </div>
        </nav>

        <main class="fs-sf-main">
            <section class="fs-sf-hero" aria-label="Homepage hero">
                <h1>FarmScout</h1>
            </section>

            <section class="fs-sf-grid" aria-label="Homepage quick links">
                <a class="fs-sf-card fs-sf-card--accent" href="categories.php">
                    <div class="fs-sf-card-top">
                        <div class="fs-sf-card-title">Explore our range of farm products and market listings</div>
                        <div class="fs-sf-illus" aria-hidden="true">
                            <svg viewBox="0 0 260 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M40 120c26-38 60-56 90-56s64 18 90 56" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M70 116c10-24 28-40 50-40s40 16 50 40" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M112 70c0-20 10-34 18-34s18 14 18 34" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M130 36v32" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M95 54c14 2 26 9 35 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M165 54c-14 2-26 9-35 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                </div>
            </div>
                    <div class="fs-sf-card-link">Browse products</div>
                </a>

                <a class="fs-sf-card" href="market-finder.php">
                    <div class="fs-sf-card-top">
                        <div class="fs-sf-illus" aria-hidden="true">
                            <svg viewBox="0 0 260 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M70 118V66h120v52" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M62 70l18-22h100l18 22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M92 118V86h76v32" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M104 98h52" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
            </div>
        </div>
                    <div class="fs-sf-card-link">Shop Markets</div>
                </a>

                <a class="fs-sf-card" href="price-history.php">
                    <div class="fs-sf-card-top">
                        <div class="fs-sf-illus" aria-hidden="true">
                            <svg viewBox="0 0 260 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M66 118h128" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M70 110l34-28 30 16 54-46" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M180 52h16v16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M196 52l-24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </div>
                    </div>
                    <div class="fs-sf-card-link">Shop Price Metrics</div>
                </a>

                <a class="fs-sf-card" href="<?php echo isLoggedIn() ? 'user-account.php' : 'register.php'; ?>">
                    <div class="fs-sf-card-top">
                        <div class="fs-sf-illus" aria-hidden="true">
                            <svg viewBox="0 0 260 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M98 64c0-18 14-32 32-32s32 14 32 32-14 32-32 32-32-14-32-32Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M70 126c10-22 33-36 60-36s50 14 60 36" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M182 44h10m-5-5v10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </div>
                    </div>
                    <div class="fs-sf-card-link"><?php echo isLoggedIn() ? 'My Account' : 'Join FarmScout'; ?></div>
                </a>
    </section>
</main>

        <footer class="fs-sf-footer">
            <div class="fs-sf-footer-inner">
                <span>La Union • FarmScout</span>
                <span>&copy;<?php echo date('Y'); ?> FarmScout</span>
            </div>
        </footer>
    </div>

    <!-- ── CURTAIN PANELS (wiped away by GSAP on load) ── -->
    <div id="fsCurtain">
        <div class="fs-curtain-panel"></div>
        <div class="fs-curtain-panel"></div>
        <div class="fs-curtain-panel"></div>
        <div class="fs-curtain-panel"></div>
            </div>

    <!-- ── FULLSCREEN MENU PANEL ── -->
    <div id="fsMenuPanel" aria-hidden="true">
        <div class="fs-mp-inner">
            <div class="fs-mp-header">
                <a href="index.php" class="fs-mp-logo">
                    <img src="assets/images/gif-wazulafu-no-bg.gif" alt="FarmScout" />
                    <span>FARMSCOUT</span>
                </a>
                <button class="fs-mp-close" id="fsMenuClose" aria-label="Close menu">
                    <span></span><span></span>
                </button>
            </div>
            <nav class="fs-mp-nav">
                <div class="fs-mp-link-wrap"><a href="index.php"         class="fs-mp-link">Home</a></div>
                <div class="fs-mp-link-wrap"><a href="market-finder.php" class="fs-mp-link">Market Finder</a></div>
                <div class="fs-mp-link-wrap"><a href="price-alerts.php"  class="fs-mp-link">Price Alerts</a></div>
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="fs-mp-link-wrap"><a href="user-account.php"  class="fs-mp-link">Account</a></div>
                <div class="fs-mp-link-wrap"><a href="logout.php"        class="fs-mp-link">Logout</a></div>
                <?php else: ?>
                <div class="fs-mp-link-wrap"><a href="login.php"         class="fs-mp-link">Login</a></div>
                <?php endif; ?>
            </nav>
            <footer class="fs-mp-footer" id="fsMenuFooter">
                <div class="fs-mp-footer-brand">FarmScout</div>
                <div class="fs-mp-footer-meta">
                    <span>La Union</span>
                    <span>&copy;<?php echo date('Y'); ?> FarmScout</span>
                </div>
            </footer>
        </div>
    </div>

    <!-- ── NAV ── -->
    <nav class="fs-nav">
        <a href="index.php" class="fs-nav-logo">
            <img src="assets/images/gif-wazulafu-no-bg.gif" alt="FarmScout" />
            <span class="fs-nav-logo-text">FARMSCOUT</span>
        </a>
        <span class="fs-nav-tagline">Tapat na Presyo &mdash; Tunay na Halaga</span>
        <div class="fs-nav-right">
            <div class="fs-nav-menu-wrap">
                <span class="fs-nav-menu-label">Menu</span>
                <button class="fs-nav-menu" id="fsMenuOpen" aria-label="Open menu">
                    <span></span><span></span><span></span>
            </button>
        </div>
        </div>
    </nav>

    <!-- ── HERO SECTION ── -->
    <section class="fs-hero">
        <div class="fs-hero-content">
            <div class="fs-title-wrap" id="fsTitleWrap">
                <h1 class="fs-brand-title" id="fsBrandTitle">FarmScout</h1>
                </div>
            <div class="fs-video-wrap" id="fsVideoWrap">
                <video id="fsHeroVideo0" class="fs-hero-video active"
                       src="assets/video/homefarm.mp4"
                       autoplay muted loop playsinline preload="auto"></video>
                <video id="fsHeroVideo1" class="fs-hero-video"
                       src="assets/video/marketegg.mp4"
                       autoplay muted loop playsinline preload="auto"></video>
            </div>
        </div>
    </section>

    <canvas id="fsFrameCanvas"></canvas>

    <!-- Sections that follow scroll normally -->
    <div class="scroll-container">
    
    <!-- About section (mobile only) removed for now -->
    
    <!-- Horizontal Transition Wrapper for Sections 2 & 3 -->
    <div class="horizontal-transition-wrapper" id="horizontalTransitionWrapper">
    <!-- Editorial Section (Section 2) -->
    <section class="editorial-section" id="editorialSection">
        <div class="editorial-content">
            <!-- Left Text Block -->
            <div class="editorial-text-block">
                <p>
                    FarmScout Online is an agricultural marketplace platform connecting farmers, markets, and consumers across La Union, Philippines.
                </p>
                
                <!-- Video Placeholder -->
                <div class="editorial-video-placeholder">
                    <video autoplay muted loop playsinline preload="auto">
                        <source src="assets/video/secondvidhome.mp4" type="video/mp4">
                    </video>
                </div>
            </div>
            
            <!-- Right Text Block -->
            <div class="editorial-text-block">
                <p>
                    We provide real-time price monitoring, transparent market information, and seamless connections between local farmers and the community.
                </p>
                <p>
                    Our platform empowers farmers to showcase their produce directly to consumers, while helping buyers discover fresh, locally-sourced products at competitive prices. Customers can make reservations, communicate directly with farmers through our integrated chat system, and receive price alerts for their favorite products.
                </p>
                <p>
                    Through innovative technology and community-driven values, FarmScout Online bridges the gap between agricultural producers and urban consumers, fostering sustainable local economies and supporting the growth of La Union's farming communities.
                </p>
            </div>
        </div>
        
        <!-- Bottom Large Wordmark -->
        <div class="editorial-bottom-wordmark">FARMSCOUT</div>
    </section>

    <!-- Footer Section (Section 3) -->
    <section class="footer-section" id="footerSection">
        <div class="footer-section-container">
            <!-- Footer Content -->
            <footer class="footer">
                <div class="footer-content">
                    <div class="footer-columns">
                        <!-- Platform Column -->
                        <div class="footer-column">
                            <h3>Platform</h3>
                            <ul>
                                <li><a href="index.php">Home</a></li>
                                <li><a href="market-finder.php">Market Finder</a></li>
                                <li><a href="price-alerts.php">Price Alerts</a></li>
                                <li><a href="categories.php">Products</a></li>
                                <li><a href="register.php">Join Us</a></li>
                            </ul>
                        </div>

                        <!-- For Users Column -->
                        <div class="footer-column">
                            <h3>For Users</h3>
                            <ul>
                                <?php if (isLoggedIn()): ?>
                                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'farmer'): ?>
                                        <li><a href="farmer-dashboard.php">Farmer Dashboard</a></li>
                                    <?php else: ?>
                                        <li><a href="user-account.php">My Account</a></li>
                                        <li><a href="my-reservations.php">My Reservations</a></li>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <li><a href="login.php">Login</a></li>
                                    <li><a href="register.php">Register</a></li>
                                <?php endif; ?>
                                <li><a href="market-map.php">Market Map</a></li>
                                <li><a href="categories.php">Browse Products</a></li>
                            </ul>
                        </div>

                        <!-- About Column -->
                        <div class="footer-column">
                            <h3>About</h3>
                            <ul>
                                <li><a href="index.php#editorialSection">Our Mission</a></li>
                                <li><a href="index.php#editorialSection">How It Works</a></li>
                                <li><a href="register.php">For Farmers</a></li>
                                <li><a href="register.php">For Consumers</a></li>
                                <li><a href="index.php#footerSection">Contact</a></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Bottom Row -->
                    <div class="footer-bottom">
                        <div class="footer-bottom-left">
                            <a href="#">TERMS OF SERVICE</a>
                            <a href="#">PRIVACY POLICY</a>
                        </div>
                        <div class="footer-bottom-right">
                            © <?php echo date('Y'); ?> FARMSCOUT
                        </div>
                    </div>
                </div>
            </footer>

            <!-- Organic Curve Shapes -->
            <div class="organic-curve"></div>
            <div class="organic-curve-2"></div>

            <!-- Background Video Section (replaces purple wave) -->
            <div class="purple-wave-container">
                <!-- Background Video -->
                <video class="footer-background-video" autoplay muted loop playsinline preload="auto">
                    <source src="assets/video/thirdvidhome.mp4" type="video/mp4">
                </video>
                
                <!-- Fallback Purple Gradient (shown if video fails) -->
                <div class="purple-wave-fallback">
                    <div class="purple-wave"></div>
                    <div class="purple-wave-2"></div>
                    <div class="purple-wave-3"></div>
                </div>
        </div>
    </div>
</section>
    </div>
    <!-- End Horizontal Transition Wrapper -->
    
    <script>
        // Mobile Menu Functions (work with both header hamburger and hero hamburger)
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('mobileMenuOverlay');
            if (!mobileMenu || !overlay) return;
            
            const headerHamburger = document.getElementById('hamburgerMenu');
            const heroHamburger = document.querySelector('.hero-hamburger');
            [headerHamburger, heroHamburger].forEach(el => { if (el) el.classList.toggle('active'); });
            
            mobileMenu.classList.toggle('active');
            overlay.classList.toggle('active');
            
            if (mobileMenu.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }

        function closeMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('mobileMenuOverlay');
            if (!mobileMenu || !overlay) return;
            
            const headerHamburger = document.getElementById('hamburgerMenu');
            const heroHamburger = document.querySelector('.hero-hamburger');
            [headerHamburger, heroHamburger].forEach(el => { if (el) el.classList.remove('active'); });
            
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Leaving for login/register: close the menu before the browser caches this page (bfcache).
        // Otherwise "Back" restores the black full-screen nav and looks like an "old" homepage.
        window.addEventListener('pagehide', function () {
            if (typeof closeMobileMenu === 'function') {
                closeMobileMenu();
            }
        });
        window.addEventListener('pageshow', function (ev) {
            if (ev.persisted && typeof closeMobileMenu === 'function') {
                closeMobileMenu();
            }
        });

        // Close mobile menu when clicking outside (include hero hamburger so it doesn't close when opening)
        document.addEventListener('click', function(event) {
            const mobileMenu = document.getElementById('mobileMenu');
            const headerHamburger = document.getElementById('hamburgerMenu');
            const heroHamburger = document.querySelector('.hero-hamburger');
            
            if (mobileMenu && mobileMenu.classList.contains('active')) {
                const isHamburger = (headerHamburger && headerHamburger.contains(event.target)) ||
                    (heroHamburger && heroHamburger.contains(event.target));
                if (!mobileMenu.contains(event.target) && !isHamburger) {
                    closeMobileMenu();
                }
            }
        });

        // Close mobile menu on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMobileMenu();
            }
        });

        // Hero hamburger - click and touch so it works on mobile
        (function initHeroHamburger() {
            function openMenu(e) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                toggleMobileMenu();
            }
            var heroBtn = document.getElementById('heroHamburger');
            if (heroBtn) {
                heroBtn.addEventListener('click', openMenu);
                heroBtn.addEventListener('touchend', function(e) {
                    e.preventDefault();
                    openMenu(e);
                }, { passive: false });
            }
        })();

        // Mobile hero video box: background-style autoplay inside box only (muted, loop, playsinline)
        (function initMobileHeroVideoBox() {
            var box = document.querySelector('.mobile-hero-video-box');
            if (!box) return;
            var video = box.querySelector('.mobile-hero-video-box-video');
            if (!video) return;
            var isMobile = function() { return window.matchMedia && window.matchMedia('(max-width: 768px)').matches; };

            video.muted = true;
            video.volume = 0;
            video.setAttribute('muted', '');
            video.setAttribute('playsinline', '');
            video.setAttribute('webkit-playsinline', '');
            if (video.playInline !== undefined) video.playInline = true;

            try {
                video.src = new URL('assets/video/firstvidhome.mp4', window.location.href).href;
            } catch (e) {
                video.src = 'assets/video/firstvidhome.mp4';
            }

            function tryAutoplay() {
                if (!isMobile()) return;
                if (!video.paused) return;
                video.play().catch(function() {});
            }

            video.addEventListener('error', function() {
                console.warn('Mobile hero video failed to load. Ensure assets/video/firstvidhome.mp4 exists.');
            });
            video.addEventListener('loadeddata', tryAutoplay, { once: true });
            video.addEventListener('canplay', tryAutoplay, { once: true });
            video.addEventListener('canplaythrough', tryAutoplay, { once: true });

            if (isMobile()) {
                video.load();
                tryAutoplay();
                setTimeout(tryAutoplay, 200);
                setTimeout(tryAutoplay, 600);
                setTimeout(tryAutoplay, 1200);
            }

            if (typeof IntersectionObserver !== 'undefined') {
                var obs = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting && isMobile()) {
                            tryAutoplay();
                            setTimeout(tryAutoplay, 150);
                        }
                    });
                }, { root: null, rootMargin: '0px', threshold: 0.25 });
                obs.observe(box);
            }
        })();

        // Mobile account dropdown toggle
        function toggleMobileAccountDropdown(event) {
            event.stopPropagation();
            const dropdown = event.currentTarget.closest('.account-dropdown');
            if (dropdown) {
                dropdown.classList.toggle('active');
            }
        }

        // Notifications in shared mobile panel: go to notifications section
        function toggleNotificationDropdown(event) {
            event.preventDefault();
            if (typeof closeMobileMenu === 'function') closeMobileMenu();
            window.location.href = 'user-account.php?section=notifications';
        }

        // Account dropdown toggle (desktop)
        function toggleAccountDropdown(event) {
            event.stopPropagation();
            const dropdown = event.currentTarget.closest('.account-dropdown');
            const isActive = dropdown.classList.contains('active');
            
            // Close all dropdowns
            document.querySelectorAll('.account-dropdown').forEach(d => d.classList.remove('active'));
            
            // Toggle this dropdown
            if (!isActive) {
                dropdown.classList.add('active');
            }
        }

        // Mobile-only: prevent browser from restoring scroll position on reload (so hero shows first)
        const isMobileView = () => window.matchMedia('(max-width: 768px)').matches;
        if (isMobileView() && typeof history !== 'undefined' && history.scrollRestoration) {
            history.scrollRestoration = 'manual';
        }
        
        // Register ScrollTrigger plugin
        gsap.registerPlugin(ScrollTrigger);
        
        // Set up scroll container
        const scrollContainer = document.querySelector('.scroll-container');
        const sections = document.querySelectorAll('.video-section');
        const videos = document.querySelectorAll('.video-section video');
        const overlays = document.querySelectorAll('.video-overlay');
        const titlesLeft = document.querySelectorAll('.video-title-left');
        const titlesRight = document.querySelectorAll('.video-title-right');
        const subtitles = document.querySelectorAll('.video-subtitle');
        const videoWrappers = document.querySelectorAll('.video-wrapper');
        
        // Store ScrollTrigger instance for manual control
        let scrollTriggerInstance;
        
        // Mobile only: start at top so hero shows first (desktop uses window scroll, don't touch)
        if (isMobileView() && scrollContainer) {
            scrollContainer.scrollTop = 0;
        }
        
        // Ensure first video section is visible immediately (before GSAP runs)
        const videoSection1 = document.getElementById('videoSection1');
        if (videoSection1) {
            videoSection1.classList.add('active');
            gsap.set('#videoSection1', { 
                opacity: 1, 
                display: 'block',
                visibility: 'visible'
            });
        }
        
        // Create main timeline with ScrollTrigger for video section only (mobile uses window scroll)
        const mainTimeline = gsap.timeline({
            scrollTrigger: {
                trigger: '#videoSection1',
                start: 'top top',
                end: 'bottom top',
                scrub: 1.5,
                pin: false,
                anticipatePin: 1,
                invalidateOnRefresh: true,
                refreshPriority: 1
            }
        });
        
        // Store the ScrollTrigger instance
        scrollTriggerInstance = mainTimeline.scrollTrigger;
        
        // Initialize page state - ensure we start at the first section
        function initializePageState() {
            window.scrollTo(0, 0);
            // Mobile only: reset scroll container and video section state (desktop unchanged)
            if (isMobileView()) {
                if (scrollContainer) scrollContainer.scrollTop = 0;
                const vs1 = document.getElementById('videoSection1');
                if (vs1) {
                    vs1.classList.remove('scrolled-past');
                    vs1.classList.add('active');
                }
                gsap.set('#videoSection1', { opacity: 1, display: 'block', visibility: 'visible' });
            }
            
            // Reset GSAP properties (shared)
            gsap.set('#overlay1', { backgroundColor: 'rgba(0, 0, 0, 0.35)' });
            gsap.set('#videoSection1 .video-wrapper', { scale: 1 });
            gsap.set('#title1-left, #title1-right, #subtitle1', { opacity: 1, y: 0 });
            
            // Reset editorial section text for reveal animation (desktop only)
            if (!isMobileView()) {
                gsap.set('.editorial-text-block p', { opacity: 0, y: 50 });
                gsap.set('.editorial-video-placeholder', { opacity: 0, y: 50 });
                gsap.set('.editorial-bottom-wordmark', { opacity: 0, y: 50 });
            }
            // Reset about section mobile for ScrollTrigger reveal
            gsap.set('.about-mobile-hero-line', { opacity: 0, y: 40 });
            gsap.set('.about-mobile-p', { opacity: 0, y: 40 });
            gsap.set('.about-mobile-bottom', { opacity: 0, y: 40 });
            
            
            // Reset timeline progress
            if (mainTimeline) {
                mainTimeline.progress(0);
            }
            
            // Refresh ScrollTrigger but preserve animations
            ScrollTrigger.refresh(false);
        }
        
        // Initialize on page load - always ensure first section is visible
        // Run initialization immediately to show first section
        initializePageState();
        
        // Also ensure first section is visible after a short delay (in case of timing issues)
        setTimeout(() => {
            const videoSection1 = document.getElementById('videoSection1');
            if (videoSection1) {
                videoSection1.classList.add('active');
                gsap.set('#videoSection1', { 
                    opacity: 1, 
                    display: 'block',
                    visibility: 'visible'
                });
            }
        }, 100);
        
        // Section 1: First video - keep existing video animations
        mainTimeline
            // Video 1 zoom in
            .to('#videoSection1 .video-wrapper', {
                scale: 1.3,
                duration: 1,
                ease: 'none'
            }, 0)
            // Video 1 overlay fade
            .to('#overlay1', {
                backgroundColor: 'rgba(0, 0, 0, 0.4)',
                duration: 0.5,
                ease: 'none'
            }, 0.5)
            // Title 1 left fade in
            .to('#title1-left', {
                opacity: 1,
                y: 0,
                duration: 0.5,
                ease: 'power2.out'
            }, 0.3)
            // Title 1 right fade in
            .to('#title1-right', {
                opacity: 1,
                y: 0,
                duration: 0.5,
                ease: 'power2.out'
            }, 0.3)
            // Subtitle 1 fade in
            .to('#subtitle1', {
                opacity: 1,
                y: 0,
                duration: 0.5,
                ease: 'power2.out'
            }, 0.5);
        
        // About section mobile — ScrollTrigger scramble reveal for all text
        if (isMobileView()) {
            const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const headlineLines = gsap.utils.toArray('.about-mobile-hero-line');
            const aboutParas = gsap.utils.toArray('.about-mobile-p');
            const allTextNodes = [...headlineLines, ...aboutParas];

            allTextNodes.forEach((el) => {
                const finalText = el.dataset.text || el.textContent;
                el.dataset.text = finalText;
                if (!prefersReduced) {
                    el.textContent = '';
                }
            });

            const scrambleChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#$%*@!?';
            const scrambleLine = (el, finalText, durationMs) => {
                const start = performance.now();
                const total = finalText.length;
                const frame = (now) => {
                    const t = Math.min((now - start) / durationMs, 1);
                    const reveal = Math.floor(t * total);
                    let out = '';
                    for (let i = 0; i < total; i++) {
                        const ch = finalText[i];
                        if (ch === ' ') {
                            out += ' ';
                        } else if (i < reveal) {
                            out += ch;
                        } else {
                            out += scrambleChars[Math.floor(Math.random() * scrambleChars.length)];
                        }
                    }
                    el.textContent = out;
                    if (t < 1) {
                        requestAnimationFrame(frame);
                    } else {
                        el.textContent = finalText;
                    }
                };
                requestAnimationFrame(frame);
            };

            let aboutTextPlayed = false;
            ScrollTrigger.create({
                trigger: '.about-mobile-inner',
                start: 'top 85%',
                once: true,
                onEnter: () => {
                    if (aboutTextPlayed) return;
                    aboutTextPlayed = true;
                    if (prefersReduced) {
                        allTextNodes.forEach((el) => {
                            el.textContent = el.dataset.text || el.textContent;
                            gsap.set(el, { opacity: 1, y: 0 });
                        });
                        return;
                    }
                    allTextNodes.forEach((el, idx) => {
                        const delay = idx * 0.12;
                        gsap.to(el, {
                            opacity: 1,
                            y: 0,
                            duration: 1.1,
                            ease: 'power2.out',
                            delay
                        });
                        scrambleLine(el, el.dataset.text || '', 1400);
                    });
                }
            });
        }
        gsap.to('.about-mobile-bottom', {
            opacity: 1,
            y: 0,
            duration: 1,
            ease: 'power2.out',
            scrollTrigger: {
                trigger: '.about-mobile-bottom',
                start: 'top 78%',
                toggleActions: 'play none none none',
                once: true
            }
        });

        // Editorial Section Text Reveal Animations
        // Animate text when editorial section enters viewport
        gsap.utils.toArray('.editorial-text-block p').forEach((paragraph, index) => {
            gsap.to(paragraph, {
                opacity: 1,
                y: 0,
                duration: 0.8,
                ease: 'power2.out',
                scrollTrigger: {
                    trigger: paragraph,
                    start: 'top 80%',
                    end: 'top 50%',
                    toggleActions: 'play none none none',
                    once: true
                }
            });
        });
        
        // Animate video placeholder
        gsap.to('.editorial-video-placeholder', {
            opacity: 1,
            y: 0,
            duration: 0.8,
            ease: 'power2.out',
            scrollTrigger: {
                trigger: '.editorial-video-placeholder',
                start: 'top 80%',
                end: 'top 50%',
                toggleActions: 'play none none none',
                once: true
            }
        });
        
        // Animate bottom wordmark
        gsap.to('.editorial-bottom-wordmark', {
            opacity: 1,
            y: 0,
            duration: 1,
            ease: 'power2.out',
            scrollTrigger: {
                trigger: '.editorial-section',
                start: 'top 70%',
                end: 'top 30%',
                toggleActions: 'play none none none',
                once: true
            }
        });
        
        // Hide video section when scrolled past (desktop uses original timing, mobile delays until spacer hits top)
        ScrollTrigger.create({
            trigger: '.video-section-spacer',
            start: isMobileView() ? 'top top' : 'top bottom',
            end: 'bottom top',
            onEnter: () => {
                const vs1 = document.getElementById('videoSection1');
                if (vs1) {
                    vs1.classList.add('scrolled-past');
                    vs1.classList.remove('active');
                }
            },
            onLeaveBack: () => {
                const vs1 = document.getElementById('videoSection1');
                if (vs1) {
                    vs1.classList.remove('scrolled-past');
                    vs1.classList.add('active');
                }
            }
        });
        
        // Navbar color transition based on section
        ScrollTrigger.create({
            trigger: '.editorial-section',
            start: 'top 80%',
            end: 'top 20%',
            onEnter: () => {
                document.querySelector('.header').classList.add('on-white');
            },
            onLeaveBack: () => {
                document.querySelector('.header').classList.remove('on-white');
            }
        });

        // Horizontal Transition: Section 2 → Section 3
        // Pin the wrapper and animate horizontal slide
        const horizontalWrapper = document.getElementById('horizontalTransitionWrapper');
        const editorialSection = document.getElementById('editorialSection');
        const footerSection = document.getElementById('footerSection');

        if (horizontalWrapper && editorialSection && footerSection) {
            // Enable hardware acceleration for smoother performance and higher FPS
            gsap.set([editorialSection, footerSection, horizontalWrapper], {
                force3D: true,
                willChange: 'transform'
            });
            
            // Set initial positions
            // Layout: [Section 3 (left, off-screen)] [Section 2 (right, visible)]
            // We want Section 3 to slide in from LEFT, so it starts at -100vw (off-screen left)
            // Section 2 is at 0 (visible)
            gsap.set(horizontalWrapper, { 
                x: 0,
                force3D: true,
                willChange: 'transform'
            });
            
            // Use CSS order or reposition
            gsap.set(footerSection, { 
                x: '-100vw',
                force3D: true,
                willChange: 'transform'
            }); // Section 3 starts off-screen to the left
            gsap.set(editorialSection, { 
                x: 0,
                force3D: true,
                willChange: 'transform'
            }); // Section 2 starts visible

            // Create horizontal transition timeline with ultra-smooth scrubbing
            const horizontalTimeline = gsap.timeline({
                scrollTrigger: {
                    trigger: horizontalWrapper,
                    start: 'top top',
                    end: '+=1000vh', // Very long scroll distance for cinematic transition
                    pin: true,
                    scrub: 0.5, // Lower value = smoother scrubbing, higher FPS (0.5 = very smooth)
                    anticipatePin: 1,
                    invalidateOnRefresh: true
                }
            });

            // Animate: Section 3 slides in from left (x: -100vw → 0)
            // Section 2 slides out to right (x: 0 → 100vw)
            // Using force3D for GPU acceleration and maximum smoothness
            horizontalTimeline
                .to(footerSection, {
                    x: 0, // Slide in from left to center
                    ease: 'none',
                    force3D: true
                }, 0)
                .to(editorialSection, {
                    x: '100vw', // Slide out to the right
                    ease: 'none',
                    force3D: true
                }, 0);
        }

        // Initialize footer background video and fallback
        const footerVideo = document.querySelector('.footer-background-video');
        const fallbackGradient = document.querySelector('.purple-wave-fallback');
        
        if (footerVideo) {
            // Try to play video
            footerVideo.play().catch(error => {
                console.log('Video autoplay prevented or failed:', error);
                // Show fallback gradient if video fails
                if (fallbackGradient) {
                    fallbackGradient.classList.add('show');
                }
            });
            
            // Handle video error (file not found, etc.)
            footerVideo.addEventListener('error', () => {
                console.log('Video failed to load, showing fallback gradient');
                if (fallbackGradient) {
                    fallbackGradient.classList.add('show');
                }
            });
            
            // Ensure video plays when it becomes visible
            footerVideo.addEventListener('loadeddata', () => {
                footerVideo.play().catch(e => {
                    console.log('Video play error:', e);
                    if (fallbackGradient) {
                        fallbackGradient.classList.add('show');
                    }
                });
            });
        }

        
        
        // Ensure videos play
        videos.forEach(video => {
            video.play().catch(e => {
                console.log('Video autoplay prevented:', e);
            });
        });
        
        // Ensure editorial video plays when it becomes visible
        const editorialVideo = document.querySelector('.editorial-video-placeholder video');
        if (editorialVideo) {
            // Play video when it enters viewport
            ScrollTrigger.create({
                trigger: '.editorial-video-placeholder',
                start: 'top 80%',
                onEnter: () => {
                    editorialVideo.play().catch(e => {
                        console.log('Editorial video autoplay prevented:', e);
                    });
                }
            });
            
            // Try to play immediately if already in viewport
            setTimeout(() => {
                editorialVideo.play().catch(e => {
                    console.log('Editorial video autoplay prevented:', e);
                });
            }, 500);
        }
        
        // Make first section text visible on load
        gsap.set('#title1-left, #title1-right, #subtitle1', {
            opacity: 1,
            y: 0
        });
        
        // Smooth scroll behavior
        let isScrolling = false;
        window.addEventListener('wheel', (e) => {
            if (!isScrolling) {
                isScrolling = true;
                setTimeout(() => {
                    isScrolling = false;
                }, 100);
            }
        }, { passive: true });
        
        // Handle fullscreen changes - only refresh ScrollTrigger, don't reset
        const handleFullscreenChange = () => {
            setTimeout(() => {
                // Only refresh ScrollTrigger to recalculate positions
                // Do NOT reset scroll, animations, or sections
                ScrollTrigger.refresh(false); // false = don't kill animations
            }, 150);
        };
        
        document.addEventListener('fullscreenchange', handleFullscreenChange);
        document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
        document.addEventListener('mozfullscreenchange', handleFullscreenChange);
        document.addEventListener('MSFullscreenChange', handleFullscreenChange);
        
        // Handle window resize - refresh ScrollTrigger safely
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                // Refresh ScrollTrigger but preserve current state
                ScrollTrigger.refresh(false); // false = don't kill animations
            }, 250);
        });
        
        // Mobile only: on load force scroll to hero so about section doesn't appear first (desktop unchanged)
        function resetScrollToHero() {
            window.scrollTo(0, 0);
            if (!isMobileView()) return;
            if (scrollContainer) scrollContainer.scrollTop = 0;
            const vs1 = document.getElementById('videoSection1');
            if (vs1) {
                vs1.classList.remove('scrolled-past');
                vs1.classList.add('active');
            }
            gsap.set('#videoSection1', { opacity: 1, display: 'block', visibility: 'visible' });
        }
        window.addEventListener('load', () => {
            resetScrollToHero();
            if (isMobileView()) {
                requestAnimationFrame(resetScrollToHero);
                setTimeout(resetScrollToHero, 50);
                setTimeout(resetScrollToHero, 150);
                setTimeout(resetScrollToHero, 300);
            }
            setTimeout(() => ScrollTrigger.refresh(false), 350);
        });
        if (document.readyState === 'complete') {
            resetScrollToHero();
        }
        
        // Handle page visibility change - only refresh ScrollTrigger, don't reset
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // Only refresh ScrollTrigger to recalculate positions
                // Do NOT reset scroll or animations
                ScrollTrigger.refresh(false); // false = don't kill animations
            }
        });
    </script>

    <script>
    function toggleAccountDropdown(event) {
        event.stopPropagation();
        const dropdown = event.currentTarget.closest('.account-dropdown');
        const isActive = dropdown.classList.contains('active');
        
        // Close all dropdowns
        document.querySelectorAll('.account-dropdown').forEach(d => d.classList.remove('active'));
        
        // Toggle this dropdown
        if (!isActive) {
            dropdown.classList.add('active');
        }
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.account-dropdown')) {
            document.querySelectorAll('.account-dropdown').forEach(d => d.classList.remove('active'));
        }
    });
    </script>

    <!-- ══════════════════════════════════════════════════════
         NEW HERO: video cycling + canvas + curtain + menu
    ══════════════════════════════════════════════════════ -->

    <!-- Video cycling + canvas background-clip for title -->
    <script>
    (function () {
        var videos    = [document.getElementById('fsHeroVideo0'), document.getElementById('fsHeroVideo1')];
        var titleEl   = document.getElementById('fsBrandTitle');
        var titleWrap = document.getElementById('fsTitleWrap');
        var videoWrap = document.getElementById('fsVideoWrap');
        var canvas    = document.getElementById('fsFrameCanvas');
        if (!canvas || !titleEl) return;
        var ctx = canvas.getContext('2d');
        var raf = null;
        var current = 0;

        function paint() {
            var heroW  = window.innerWidth;
            var titleH = titleWrap.offsetHeight;
            var videoH = videoWrap.offsetHeight;
            if (canvas.width !== heroW || canvas.height !== titleH) {
                canvas.width  = heroW;
                canvas.height = titleH;
            }
            ctx.drawImage(videos[current], 0, 0, heroW, titleH + videoH);
            titleEl.style.backgroundImage    = 'url(' + canvas.toDataURL('image/jpeg', 0.92) + ')';
            titleEl.style.backgroundSize     = '100% 100%';
            titleEl.style.backgroundPosition = '0 0';
            raf = requestAnimationFrame(paint);
        }

        function startPaint() { if (!raf) paint(); }

        function switchVideo() {
            videos[current].classList.remove('active');
            current = (current + 1) % videos.length;
            videos[current].currentTime = 0;
            videos[current].play();
            videos[current].classList.add('active');
        }

        setInterval(switchVideo, 5000);

        if (videos[0].readyState >= 2) { startPaint(); }
        else { videos[0].addEventListener('canplay', startPaint, { once: true }); }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) { cancelAnimationFrame(raf); raf = null; }
            else { startPaint(); }
        });
    }());
    </script>

    <!-- Curtain reveal + hero content entrance -->
    <script>
    (function () {
        var curtain = document.getElementById('fsCurtain');

        /* ── 1. Curtain wipe (4 panels, power2.in, stagger 0.18) ── */
        gsap.to('.fs-curtain-panel', {
            yPercent: -100,
            duration: 1.2,
            ease: 'power2.in',
            stagger: 0.18,
            delay: 0.4,
            onComplete: function () {
                if (curtain && curtain.parentNode) curtain.parentNode.removeChild(curtain);
            }
        });

        /* ── 2. Hero text: mask reveal, rises from below ─────────── */
        gsap.to('#fsBrandTitle', {
            y: 0,
            duration: 1.0,
            ease: 'power3.out',
            delay: 1.05
        });

        /* ── 3. Video box: CRT TV turn-on ───────────────────────── */
        var crtTl = gsap.timeline({ delay: 1.0 });
        crtTl.set('#fsVideoWrap', {
            scaleX: 1, scaleY: 0.012,
            transformOrigin: 'center center'
        });
        crtTl.to('#fsVideoWrap', {
            scaleY: 1, duration: 1.3,
            ease: 'power3.out',
            transformOrigin: 'center center'
        }, '+=0.08');
    }());
    </script>

    <!-- Menu panel open / close + SPA panel switching -->
    <script>
    (function () {
        var panel    = document.getElementById('fsMenuPanel');
        var closeBtn = document.getElementById('fsMenuClose');
        var openBtn  = document.getElementById('fsMenuOpen');
        var links    = document.querySelectorAll('.fs-mp-link');
        var footer   = document.getElementById('fsMenuFooter');
        var isOpen   = false;

        function openMenu() {
            isOpen = true;
            panel.classList.add('is-open');
            panel.setAttribute('aria-hidden', 'false');
            gsap.to(panel, { y: '0%', duration: 0.72, ease: 'power3.inOut' });
            gsap.to(links,  { y: 0, duration: 0.55, ease: 'power3.out', stagger: 0.08, delay: 0.35 });
            gsap.to(footer, { opacity: 1, duration: 0.4, ease: 'power2.out', delay: 0.7 });
        }

        function closeMenu() {
            isOpen = false;
            gsap.to(links,  { y: '105%', duration: 0.35, ease: 'power2.in', stagger: { each: 0.05, from: 'end' } });
            gsap.to(footer, { opacity: 0, duration: 0.25, ease: 'power2.in' });
            gsap.to(panel,  {
                y: '-100%', duration: 0.65, ease: 'power3.inOut', delay: 0.2,
                onComplete: function () {
                    panel.classList.remove('is-open');
                    panel.setAttribute('aria-hidden', 'true');
                }
            });
        }

        // menu links now navigate normally via href; no SPA switching
        if (openBtn)  openBtn.addEventListener('click',  function () { isOpen ? closeMenu() : openMenu(); });
        if (closeBtn) closeBtn.addEventListener('click', closeMenu);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && isOpen) closeMenu(); });
    }());
    </script>


</body>
</html>
