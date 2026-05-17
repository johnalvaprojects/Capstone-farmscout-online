<?php
/**
 * Email Template Configuration for FarmScout Online
 * 
 * This file allows you to easily switch between different email template styles
 * without modifying the core code.
 */

// Default email template configuration
return [
    // Main template settings
    'default_price_alert_template' => 'standard', // New premium Discord-inspired template
    'default_welcome_template' => 'welcome_professional',
    'default_test_template' => 'test_template',
    
    // Available price alert templates
    'available_price_alert_templates' => [
        'special_event' => [
            'name' => 'Special Event (Original)',
            'description' => 'Bold, promotional style with modern CSS features',
            'best_for' => 'Modern email clients, marketing campaigns',
            'file' => 'price_alert_special_event.html'
        ],
        'special_event_v2' => [
            'name' => 'Special Event V2 (Optimized)',
            'description' => 'Email client optimized version with table-based layout',
            'best_for' => 'Maximum compatibility across all email clients',
            'file' => 'price_alert_special_event_v2.html'
        ],
        'standard' => [
            'name' => 'Standard Template',
            'description' => 'Clean, simple design with FarmScout branding',
            'best_for' => 'General use, professional communication',
            'file' => 'price_alert_template.html'
        ],
        'professional' => [
            'name' => 'Professional Template',
            'description' => 'Clean, business-oriented design',
            'best_for' => 'B2B communication, formal alerts',
            'file' => 'price_alert_professional.html'
        ],
        'dark' => [
            'name' => 'Dark Theme',
            'description' => 'Dark mode design for modern look',
            'best_for' => 'Tech-savvy users, night mode preference',
            'file' => 'price_alert_dark_theme.html'
        ],
        'premium_nitro' => [
            'name' => 'Premium Nitro (Discord-Inspired)',
            'description' => 'Beautiful gradient design with premium aesthetics, inspired by Discord Nitro',
            'best_for' => 'Premium feel, modern users, promotional campaigns',
            'file' => 'price_alert_premium_nitro.html'
        ]
    ],
    
    // Template features
    'template_features' => [
        'special_event' => [
            'mobile_responsive' => true,
            'product_images' => true,
            'price_comparison' => true,
            'savings_highlight' => true,
            'cta_buttons' => true,
            'social_proof' => false,
            'branding' => 'strong',
            'color_scheme' => 'red_green_black'
        ],
        'special_event_v2' => [
            'mobile_responsive' => true,
            'product_images' => true,
            'price_comparison' => true,
            'savings_highlight' => true,
            'cta_buttons' => true,
            'social_proof' => false,
            'branding' => 'strong',
            'color_scheme' => 'red_green_black',
            'email_client_optimized' => true
        ],
        'premium_nitro' => [
            'mobile_responsive' => true,
            'product_images' => true,
            'price_comparison' => true,
            'savings_highlight' => true,
            'cta_buttons' => true,
            'social_proof' => false,
            'branding' => 'premium',
            'color_scheme' => 'gradient_purple_blue',
            'email_client_optimized' => true,
            'premium_design' => true,
            'celebration_elements' => true
        ]
    ],
    
    // Template settings
    'settings' => [
        'use_product_images' => true,
        'show_savings_percentage' => true,
        'include_market_branding' => true,
        'enable_cta_tracking' => true,
        'responsive_design' => true
    ],
    
    // Color schemes for templates
    'color_schemes' => [
        'farmscout_green' => [
            'primary' => '#2D5016',
            'accent' => '#75A347',
            'error' => '#dc2626',
            'success' => '#059669'
        ],
        'special_event' => [
            'primary' => '#2D5016',
            'accent' => '#FF3333',
            'highlight' => '#75A347',
            'background' => '#1a1a1a'
        ],
        'gradient_purple_blue' => [
            'primary' => '#667eea',
            'secondary' => '#764ba2',
            'accent' => '#f093fb',
            'success' => '#38a169',
            'background' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'text_primary' => '#1a202c',
            'text_secondary' => '#718096'
        ]
    ],
    
    // Email client compatibility
    'email_client_support' => [
        'gmail' => ['premium_nitro', 'special_event_v2', 'standard', 'professional'],
        'outlook' => ['special_event_v2', 'standard', 'professional'],
        'apple_mail' => ['premium_nitro', 'special_event', 'special_event_v2', 'standard'],
        'yahoo' => ['special_event_v2', 'standard'],
        'thunderbird' => ['standard', 'professional'],
        'mobile' => ['premium_nitro', 'special_event_v2', 'standard']
    ],
    
    // Usage recommendations
    'recommendations' => [
        'marketing_campaigns' => 'premium_nitro',
        'daily_alerts' => 'standard',
        'premium_users' => 'premium_nitro',
        'mobile_heavy' => 'premium_nitro',
        'corporate' => 'professional',
        'celebration_events' => 'premium_nitro',
        'modern_users' => 'premium_nitro'
    ]
];
?>