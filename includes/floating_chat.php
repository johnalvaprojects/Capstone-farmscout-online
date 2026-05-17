<?php
/**
 * Floating Chat Component
 * Reusable component for floating chat icon and panel
 * 
 * Usage: Include this file in pages where chat should be available
 * 
 * Requirements:
 * - User must be logged in
 * - Requires includes/enhanced_functions.php
 * - Requires includes/security.php
 */

if (!isLoggedIn()) {
    return; // Don't show chat if not logged in
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'user';
$csrf_token = getCSRFToken();
?>

<style>
/* Floating Chat Icon */
.floating-chat-icon {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 60px;
    height: 60px;
    background-color: #000000;
    border: 3px solid #000000;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 9998;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    transition: all 0.3s ease;
    font-size: 1.5rem;
}

.floating-chat-icon:hover {
    transform: scale(1.1);
    background-color: #333333;
}

.floating-chat-icon.active {
    background-color: #000000;
}

.floating-chat-icon svg {
    width: 30px;
    height: 30px;
    fill: #ffffff;
}

/* Unread Badge */
.chat-unread-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: #dc3545;
    color: #ffffff;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: bold;
    font-family: 'VT323', monospace;
    border: 2px solid #ffffff;
    min-width: 24px;
}

.chat-unread-badge.hidden {
    display: none;
}

/* Chat Panel */
.floating-chat-panel {
    position: fixed;
    bottom: 90px;
    right: 20px;
    width: 400px;
    height: 600px;
    background-color: #ffffff;
    border: 3px solid #000000;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    z-index: 9999;
    display: none;
    flex-direction: column;
    font-family: 'VT323', monospace;
    transform: translateY(20px);
    opacity: 0;
    transition: all 0.3s ease;
}

.floating-chat-panel.open {
    display: flex;
    transform: translateY(0);
    opacity: 1;
}

/* Panel Header */
.chat-panel-header {
    padding: 1rem;
    border-bottom: 3px solid #000000;
    display: grid;
    grid-template-columns: 30px 1fr 30px;
    align-items: center;
    background-color: #000000;
    color: #ffffff;
    position: relative;
}

.chat-panel-header h3 {
    margin: 0;
    font-size: 1.5rem;
    text-transform: uppercase;
    font-family: 'VT323', monospace;
    text-align: center;
    grid-column: 2;
}

.chat-panel-header > div {
    grid-column: 3;
    display: flex;
    gap: 0.5rem;
    align-items: center;
    justify-content: flex-end;
}

.chat-panel-close {
    background: none;
    border: none;
    color: #ffffff;
    font-size: 2rem;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    transition: transform 0.2s ease;
}

.chat-panel-close:hover {
    transform: rotate(90deg);
}

/* Panel Content */
.chat-panel-content {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Back Button */
.chat-back-btn {
    background: none;
    border: none;
    color: #ffffff;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: none;
    align-items: center;
    justify-content: center;
    margin-right: 0.5rem;
    transition: transform 0.2s ease;
}

.chat-back-btn:hover {
    transform: translateX(-3px);
}

.chat-back-btn.visible {
    display: flex;
}

/* Conversation List View */
.chat-conversation-list-view {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.chat-conversation-list-view.hidden {
    display: none;
}

.chat-conversations-container {
    flex: 1;
    overflow-y: auto;
    padding: 0;
    background-color: #f9f9f9;
}

.chat-conversation-item {
    padding: 1rem;
    border-bottom: 2px solid #e0e0e0;
    cursor: pointer;
    transition: all 0.2s ease;
    background-color: #ffffff;
}

.chat-conversation-item:hover {
    background-color: #f5f5f5;
}

.chat-conversation-item.active {
    background-color: #e8e8e8;
    border-left: 4px solid #000000;
}

.chat-conversation-item.grouped {
    border-left: 3px solid #cccccc;
    padding-left: 1.25rem;
    background-color: #fafafa;
}

.chat-conversation-item.grouped.first-of-group {
    border-left: none;
    padding-left: 1rem;
    margin-top: 0.5rem;
    border-top: 2px solid #e0e0e0;
    padding-top: 1.25rem;
    background-color: #ffffff;
}

.chat-conversation-item.grouped:hover {
    background-color: #f0f0f0;
}

.chat-conversation-item.grouped.first-of-group:hover {
    background-color: #f5f5f5;
}

.chat-conversation-item-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 0.5rem;
}

.chat-conversation-item-name {
    font-size: 1rem;
    font-weight: bold;
    color: #000000;
    text-transform: uppercase;
    flex: 1;
}

.chat-conversation-item-time {
    font-size: 0.8rem;
    color: #666;
    white-space: nowrap;
    margin-left: 0.5rem;
}

.chat-conversation-item-product {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 0.25rem;
}

.chat-conversation-item-preview {
    font-size: 0.85rem;
    color: #999;
    font-style: italic;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.chat-conversation-item-unread {
    display: inline-block;
    background-color: #dc3545;
    color: #ffffff;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7rem;
    text-align: center;
    line-height: 20px;
    margin-left: 0.5rem;
    font-weight: bold;
}

/* Message View */
.chat-message-view {
    flex: 1;
    display: none;
    flex-direction: column;
    overflow: hidden;
}

.chat-message-view.active {
    display: flex;
}

.chat-message-view-header {
    padding: 1rem;
    border-bottom: 3px solid #000000;
    background-color: #000000;
    color: #ffffff;
    display: flex;
    align-items: center;
}

.chat-message-view-header-info {
    flex: 1;
}

.chat-message-view-header-title {
    font-size: 1.1rem;
    font-weight: bold;
    text-transform: uppercase;
    margin-bottom: 0.25rem;
}

.chat-message-view-header-subtitle {
    font-size: 0.9rem;
    opacity: 0.8;
}

/* Chat View - Always Visible */
.chat-view {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Reservation Header (between message groups) */
.chat-reservation-header {
    margin: 1.5rem 0 0.75rem 0;
    padding: 0.75rem 1rem;
    background-color: #e8e8e8;
    border: 2px solid #000000;
    border-radius: 4px;
    text-align: center;
    font-family: 'VT323', monospace;
}

.chat-reservation-header:first-child {
    margin-top: 0;
}

.chat-reservation-header-title {
    font-size: 1.1rem;
    font-weight: bold;
    text-transform: uppercase;
    color: #000000;
    margin-bottom: 0.25rem;
}

.chat-reservation-header-subtitle {
    font-size: 0.9rem;
    color: #666;
}

.chat-hide-reservation-btn {
    padding: 0.25rem 0.75rem;
    border: 2px solid #000000;
    background: none;
    font-family: 'VT323', monospace;
    font-size: 0.8rem;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s ease;
    color: #000000;
    white-space: nowrap;
}

.chat-hide-reservation-btn:hover {
    background-color: #000000;
    color: #ffffff;
}

.chat-reservation-header-icon {
    width: 20px;
    height: 20px;
    object-fit: contain;
    vertical-align: middle;
    margin-right: 0.5rem;
}

.chat-message-delete-btn {
    position: absolute;
    top: 0.25rem;
    right: 0.25rem;
    background: rgba(0, 0, 0, 0.7);
    color: #ffffff;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    cursor: pointer;
    font-size: 0.7rem;
    display: none;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    font-family: 'VT323', monospace;
}

.chat-message-item:hover .chat-message-delete-btn {
    display: flex;
}

.chat-message-delete-btn:hover {
    background: rgba(220, 38, 38, 0.9);
    transform: scale(1.1);
}

.chat-message-bubble {
    position: relative;
}

.chat-clear-btn {
    background: none;
    border: 2px solid #000000;
    padding: 0.25rem 0.75rem;
    cursor: pointer;
    font-family: 'VT323', monospace;
    font-size: 0.8rem;
    text-transform: uppercase;
    transition: all 0.2s ease;
    color: #000000;
}

.chat-clear-btn:hover {
    background-color: #000000;
    color: #ffffff;
}

/* Clear Chat Modal */
.chat-clear-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.chat-clear-modal.active {
    display: flex;
}

.chat-clear-modal-content {
    background-color: #ffffff;
    border: 3px solid #000000;
    padding: 2rem;
    max-width: 400px;
    width: 90%;
    text-align: center;
    font-family: 'VT323', monospace;
}

.chat-clear-modal-content h3 {
    margin: 0 0 1rem 0;
    font-size: 1.5rem;
    text-transform: uppercase;
}

.chat-clear-modal-content p {
    margin: 0 0 1.5rem 0;
    font-size: 1rem;
    color: #666;
}

.chat-clear-modal-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.chat-clear-modal-btn {
    padding: 0.5rem 1.5rem;
    border: 2px solid #000000;
    background: none;
    font-family: 'VT323', monospace;
    font-size: 1rem;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s ease;
}

.chat-clear-modal-btn.confirm {
    background-color: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
}

.chat-clear-modal-btn.confirm:hover {
    background-color: #b91c1c;
}

.chat-clear-modal-btn.cancel:hover {
    background-color: #000000;
    color: #ffffff;
}

.chat-messages-container {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    background-color: #f9f9f9;
}

.chat-message-item {
    margin-bottom: 1rem;
    display: flex;
    justify-content: flex-start;
}

.chat-message-item.sender {
    justify-content: flex-end;
}

.chat-message-item.system {
    justify-content: center;
}

.chat-message-bubble {
    max-width: 70%;
    padding: 0.75rem 1rem;
    border: 2px solid #000000;
    border-radius: 4px;
    font-size: 1rem;
    word-wrap: break-word;
}

.chat-message-item.sender .chat-message-bubble {
    background-color: #000000;
    color: #ffffff;
}

.chat-message-item:not(.sender):not(.system) .chat-message-bubble {
    background-color: #e0e0e0;
    color: #000000;
}

.chat-message-item.system .chat-message-bubble {
    background-color: #e8e8e8;
    color: #000000;
    text-align: center;
    font-style: italic;
    max-width: 80%;
}

.chat-message-item.system.accepted .chat-message-bubble {
    background-color: #d4edda;
    color: #155724;
    border-color: #155724;
}

.chat-message-item.system.declined .chat-message-bubble {
    background-color: #f8d7da;
    color: #721c24;
    border-color: #721c24;
}

.chat-message-item.system.cancelled .chat-message-bubble {
    background-color: #fff3cd;
    color: #856404;
    border-color: #856404;
}

.chat-message-item.system.completed .chat-message-bubble {
    background-color: #cce5ff;
    color: #004085;
    border-color: #004085;
}

.chat-message-sender {
    font-size: 0.8rem;
    margin-bottom: 0.25rem;
    opacity: 0.8;
}

.chat-message-time {
    font-size: 0.7rem;
    margin-top: 0.25rem;
    opacity: 0.7;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.chat-message-seen {
    font-size: 0.65rem;
    color: #666;
    font-style: italic;
    opacity: 0.8;
}

/* Typing Indicator */
.chat-typing-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
    color: #666;
    font-style: italic;
    margin: 0.5rem 0;
}

.chat-typing-dots {
    display: flex;
    gap: 0.25rem;
}

.chat-typing-dots span {
    width: 6px;
    height: 6px;
    background-color: #666;
    border-radius: 50%;
    animation: typing-dot 1.4s infinite;
}

.chat-typing-dots span:nth-child(1) {
    animation-delay: 0s;
}

.chat-typing-dots span:nth-child(2) {
    animation-delay: 0.2s;
}

.chat-typing-dots span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing-dot {
    0%, 60%, 100% {
        transform: translateY(0);
        opacity: 0.7;
    }
    30% {
        transform: translateY(-8px);
        opacity: 1;
    }
}

.chat-typing-text {
    font-family: 'VT323', monospace;
}

.chat-input-container {
    padding: 1rem;
    border-top: 3px solid #000000;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    background-color: #ffffff;
}

.chat-input-row {
    display: flex;
    gap: 0.5rem;
}

.chat-reservation-selector {
    padding: 0.5rem;
    border: 2px solid #000000;
    font-family: 'VT323', monospace;
    font-size: 0.9rem;
    background-color: #ffffff;
    color: #000000;
    cursor: pointer;
}

.chat-reservation-selector:focus {
    outline: none;
    border-color: #333;
}

.chat-input {
    flex: 1;
    padding: 0.75rem;
    border: 2px solid #000000;
    font-family: 'VT323', monospace;
    font-size: 1rem;
    resize: none;
    min-height: 50px;
    max-height: 120px;
}

.chat-send-btn {
    padding: 0.75rem 1.5rem;
    border: 2px solid #000000;
    background-color: #000000;
    color: #ffffff;
    font-family: 'VT323', monospace;
    font-size: 1rem;
    cursor: pointer;
    text-transform: uppercase;
    white-space: nowrap;
    transition: all 0.2s ease;
}

.chat-send-btn:hover:not(:disabled) {
    background-color: #333333;
}

.chat-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Empty State */
.chat-empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    text-align: center;
    color: #666;
}

/* Loading State */
.chat-loading {
    padding: 2rem;
    text-align: center;
    color: #666;
}

/* Backdrop */
.chat-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.3);
    z-index: 9997;
    display: none;
}

.chat-backdrop.active {
    display: block;
}

/* Empty State for Conversation List */
.chat-conversation-empty {
    padding: 2rem;
    text-align: center;
    color: #666;
    font-size: 0.9rem;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .floating-chat-icon {
        width: 50px;
        height: 50px;
        bottom: 15px;
        right: 15px;
    }
    
    .floating-chat-icon svg {
        width: 24px;
        height: 24px;
    }
    
    .floating-chat-panel {
        width: 100%;
        height: 80%;
        bottom: 0;
        right: 0;
        left: 0;
        border-left: none;
        border-right: none;
        border-bottom: none;
    }
    
    .chat-unread-badge {
        width: 20px;
        height: 20px;
        font-size: 0.65rem;
        min-width: 20px;
    }
}
</style>

<!-- Floating Chat Icon -->
<div class="floating-chat-icon" id="floatingChatIcon" onclick="toggleFloatingChat()">
    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
    </svg>
    <span class="chat-unread-badge hidden" id="chatUnreadBadge">0</span>
</div>

<!-- Backdrop -->
<div class="chat-backdrop" id="chatBackdrop" onclick="closeFloatingChat()"></div>

<!-- Chat Panel -->
<div class="floating-chat-panel" id="floatingChatPanel">
    <!-- Panel Header -->
    <div class="chat-panel-header">
        <button class="chat-back-btn" id="chatBackBtn" onclick="showConversationList()" title="Back to conversations" style="grid-column: 1; justify-self: start;">←</button>
        <h3 id="chatPanelTitle" style="grid-column: 2; text-align: center;">CHAT</h3>
        <div style="grid-column: 3; display: flex; gap: 0.5rem; align-items: center; justify-content: flex-end;">
            <button class="chat-clear-btn" id="chatClearBtn" onclick="showClearChatModal()" title="Clear messages" style="display: none;">CLEAR</button>
            <button class="chat-panel-close" onclick="closeFloatingChat()">&times;</button>
        </div>
    </div>
    
    <!-- Delete Message Confirmation Modal -->
    <div class="chat-clear-modal" id="chatDeleteMessageModal">
        <div class="chat-clear-modal-content">
            <h3>Delete Message?</h3>
            <p>This will permanently delete this message.</p>
            <p style="font-size: 0.9rem; color: #dc2626; font-weight: bold;">This action cannot be undone.</p>
            <div class="chat-clear-modal-buttons">
                <button class="chat-clear-modal-btn confirm" onclick="confirmDeleteMessage()">DELETE</button>
                <button class="chat-clear-modal-btn cancel" onclick="closeDeleteMessageModal()">CANCEL</button>
            </div>
        </div>
    </div>
    
    <!-- Hide Reservation Confirmation Modal -->
    <div class="chat-clear-modal" id="chatHideReservationModal">
        <div class="chat-clear-modal-content">
            <h3>Hide Reservation?</h3>
            <p>This will hide this reservation from your chat view. You can still access it from your reservations page.</p>
            <p style="font-size: 0.9rem; color: #666;">The reservation will no longer appear in the chat, but messages will be preserved.</p>
            <div class="chat-clear-modal-buttons">
                <button class="chat-clear-modal-btn confirm" onclick="confirmHideReservation()">HIDE</button>
                <button class="chat-clear-modal-btn cancel" onclick="closeHideReservationModal()">CANCEL</button>
            </div>
        </div>
    </div>
    
    <!-- Clear Chat Confirmation Modal -->
    <div class="chat-clear-modal" id="chatClearModal">
        <div class="chat-clear-modal-content">
            <h3>Clear Chat?</h3>
            <p>This will delete all messages from this reservation. System messages (like "Reservation accepted") will be preserved.</p>
            <p style="font-size: 0.9rem; color: #dc2626; font-weight: bold;">This action cannot be undone.</p>
            <div class="chat-clear-modal-buttons">
                <button class="chat-clear-modal-btn confirm" onclick="confirmClearChat()">CLEAR</button>
                <button class="chat-clear-modal-btn cancel" onclick="closeClearChatModal()">CANCEL</button>
            </div>
        </div>
    </div>
    
    <!-- Panel Content -->
    <div class="chat-panel-content">
        <!-- Conversation List View -->
        <div class="chat-conversation-list-view" id="chatConversationListView">
            <div class="chat-conversations-container" id="chatConversationsContainer">
                <div class="chat-loading">Loading conversations...</div>
            </div>
        </div>
        
        <!-- Message View -->
        <div class="chat-message-view" id="chatMessageView">
            <div class="chat-message-view-header">
                <div class="chat-message-view-header-info">
                    <div class="chat-message-view-header-title" id="chatMessageViewTitle"></div>
                    <div class="chat-message-view-header-subtitle" id="chatMessageViewSubtitle"></div>
                </div>
            </div>
            <div class="chat-messages-container" id="chatMessagesContainer">
                <div class="chat-loading">Loading messages...</div>
            </div>
            <div class="chat-input-container">
                <div class="chat-input-row">
                    <textarea 
                        class="chat-input" 
                        id="chatInput" 
                        placeholder="Type your message..."
                        rows="2"></textarea>
                    <button class="chat-send-btn" id="chatSendBtn" onclick="sendChatMessage()">SEND</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Floating Chat State
let floatingChatState = {
    isOpen: false,
    conversations: [],
    selectedConversationId: null,
    messages: [],
    unreadCount: 0,
    pollInterval: null,
    typingPollInterval: null, // Polling for typing status
    shouldScrollToBottom: true, // Flag to force scroll (e.g., when sending message or first opening)
    lastMessageHash: null, // Hash of last messages to detect changes
    isUserScrolling: false, // Track if user is actively scrolling
    typingTimeout: null // Timeout for typing detection
};

// Initialize floating chat
document.addEventListener('DOMContentLoaded', function() {
    // Update unread count
    updateUnreadCount();
    
    // Start polling for unread count
    startUnreadCountPolling();
    
    // Track user scrolling to prevent auto-updates while scrolling
    const messagesContainer = document.getElementById('chatMessagesContainer');
    if (messagesContainer) {
        let scrollTimeout;
        messagesContainer.addEventListener('scroll', function() {
            floatingChatState.isUserScrolling = true;
            clearTimeout(scrollTimeout);
            // Reset flag after user stops scrolling for 500ms
            scrollTimeout = setTimeout(() => {
                floatingChatState.isUserScrolling = false;
            }, 500);
        });
    }
    
    // Allow Enter key to send message (Shift+Enter for new line)
    const chatInput = document.getElementById('chatInput');
    if (chatInput) {
        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        });
        
        // Typing detection
        let lastTypingUpdate = 0;
        const TYPING_UPDATE_THROTTLE = 1000; // Only update every 1 second to reduce API calls
        
        chatInput.addEventListener('input', function() {
            const now = Date.now();
            
            // Throttle updates to avoid too many API calls
            if (now - lastTypingUpdate < TYPING_UPDATE_THROTTLE) {
                // Still clear the timeout to extend typing duration
                if (floatingChatState.typingTimeout) {
                    clearTimeout(floatingChatState.typingTimeout);
                }
                // Set timeout to mark as "stopped typing" after 8 seconds of inactivity
                floatingChatState.typingTimeout = setTimeout(() => {
                    updateTypingStatus(false);
                }, 8000);
                return;
            }
            
            lastTypingUpdate = now;
            
            // User is typing
            updateTypingStatus(true);
            
            // Clear existing timeout
            if (floatingChatState.typingTimeout) {
                clearTimeout(floatingChatState.typingTimeout);
            }
            
            // Set timeout to mark as "stopped typing" after 8 seconds of inactivity (increased from 5)
            floatingChatState.typingTimeout = setTimeout(() => {
                updateTypingStatus(false);
            }, 8000);
        });
        
        // User left the input field
        chatInput.addEventListener('blur', function() {
            // Don't immediately set to false when blurring - give a small delay
            // in case user is just clicking elsewhere but will come back
            if (floatingChatState.typingTimeout) {
                clearTimeout(floatingChatState.typingTimeout);
            }
            floatingChatState.typingTimeout = setTimeout(() => {
                updateTypingStatus(false);
            }, 2000);
        });
    }
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && floatingChatState.isOpen) {
            closeFloatingChat();
        }
    });
});

// Toggle chat panel
function toggleFloatingChat() {
    if (floatingChatState.isOpen) {
        closeFloatingChat();
    } else {
        openFloatingChat();
    }
}

// Open chat panel
function openFloatingChat() {
    const panel = document.getElementById('floatingChatPanel');
    const backdrop = document.getElementById('chatBackdrop');
    const icon = document.getElementById('floatingChatIcon');
    
    if (panel && backdrop && icon) {
        panel.classList.add('open');
        backdrop.classList.add('active');
        icon.classList.add('active');
        floatingChatState.isOpen = true;
        
        // Set flag to scroll to bottom on initial open
        floatingChatState.shouldScrollToBottom = true;
        
        // Update unread count when opening
        updateUnreadCount();
        
        // Load conversations list
        loadConversations();
        
        // Start conversation polling
        startConversationPolling();
        
        // Start unread count polling
        startUnreadCountPolling();
    }
}

// Close chat panel
function closeFloatingChat() {
    const panel = document.getElementById('floatingChatPanel');
    const backdrop = document.getElementById('chatBackdrop');
    const icon = document.getElementById('floatingChatIcon');
    
    if (panel && backdrop && icon) {
        panel.classList.remove('open');
        backdrop.classList.remove('active');
        icon.classList.remove('active');
        floatingChatState.isOpen = false;
        
        // Reset to conversation list view
        showConversationList();
        
        // Stop polling
        stopConversationPolling();
    }
}

// Load conversations list
function loadConversations(showLoading = true) {
    const conversationsContainer = document.getElementById('chatConversationsContainer');
    if (!conversationsContainer) return;
    
    if (showLoading) {
        conversationsContainer.innerHTML = '<div class="chat-loading">Loading conversations...</div>';
    }
    
    fetch('api/get_chat_conversations.php?csrf_token=<?php echo $csrf_token; ?>')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                floatingChatState.conversations = data.conversations || [];
                renderConversationsList();
                
                // Don't auto-select - show conversation list by default
            } else {
                conversationsContainer.innerHTML = '<div class="chat-conversation-empty">Error: ' + (data.message || 'Failed to load conversations') + '</div>';
            }
        })
        .catch(error => {
            console.error('Error loading conversations:', error);
            conversationsContainer.innerHTML = '<div class="chat-conversation-empty">Error loading conversations</div>';
        });
}

// Render conversations list
function renderConversationsList() {
    const conversationsContainer = document.getElementById('chatConversationsContainer');
    if (!conversationsContainer) return;
    
    if (floatingChatState.conversations.length === 0) {
        conversationsContainer.innerHTML = '<div class="chat-conversation-empty">No conversations yet</div>';
        return;
    }
    
    // Group conversations by person name
    const groupedByPerson = {};
    floatingChatState.conversations.forEach(conv => {
        const personName = conv.other_party_name.toLowerCase();
        if (!groupedByPerson[personName]) {
            groupedByPerson[personName] = [];
        }
        groupedByPerson[personName].push(conv);
    });
    
    // Sort groups by most recent message (get the most recent from each group)
    const sortedGroups = Object.keys(groupedByPerson).sort((a, b) => {
        const aMostRecent = groupedByPerson[a].reduce((latest, conv) => {
            const convTime = new Date(conv.last_message_time || 0);
            const latestTime = new Date(latest.last_message_time || 0);
            return convTime > latestTime ? conv : latest;
        }, groupedByPerson[a][0]);
        
        const bMostRecent = groupedByPerson[b].reduce((latest, conv) => {
            const convTime = new Date(conv.last_message_time || 0);
            const latestTime = new Date(latest.last_message_time || 0);
            return convTime > latestTime ? conv : latest;
        }, groupedByPerson[b][0]);
        
        return new Date(bMostRecent.last_message_time || 0) - new Date(aMostRecent.last_message_time || 0);
    });
    
    let html = '';
    sortedGroups.forEach((personName, groupIndex) => {
        const conversations = groupedByPerson[personName];
        
        // Sort conversations within group by most recent message
        conversations.sort((a, b) => {
            return new Date(b.last_message_time || 0) - new Date(a.last_message_time || 0);
        });
        
        conversations.forEach((conv, convIndex) => {
            const isActive = floatingChatState.selectedConversationId === conv.reservation_id;
            const unreadBadge = conv.unread_count > 0 ? 
                `<span class="chat-conversation-item-unread">${conv.unread_count > 99 ? '99+' : conv.unread_count}</span>` : '';
            
            const timeAgo = formatTimeAgo(conv.last_message_time);
            const preview = conv.last_message ? (conv.last_message.length > 40 ? conv.last_message.substring(0, 40) + '...' : conv.last_message) : 'No messages';
            
            // Add grouped class if this person has multiple conversations
            const isGrouped = conversations.length > 1;
            const isFirstInGroup = convIndex === 0;
            const groupedClass = isGrouped ? 'grouped' : '';
            const firstOfGroupClass = isGrouped && isFirstInGroup ? 'first-of-group' : '';
            
            html += `
                <div class="chat-conversation-item ${isActive ? 'active' : ''} ${groupedClass} ${firstOfGroupClass}" 
                     onclick="selectConversation(${conv.reservation_id})">
                    <div class="chat-conversation-item-header">
                        <div class="chat-conversation-item-name">${escapeHtml(conv.other_party_name)}${unreadBadge}</div>
                        <div class="chat-conversation-item-time">${timeAgo}</div>
                    </div>
                    <div class="chat-conversation-item-product">${escapeHtml(conv.product_summary || conv.product_name || '')}${conv.chat_public_ref ? ' · ' + escapeHtml(conv.chat_public_ref) : ''}</div>
                    <div class="chat-conversation-item-preview">${escapeHtml(preview)}</div>
                </div>
            `;
        });
    });
    
    conversationsContainer.innerHTML = html;
}

// Show conversation list view
function showConversationList() {
    floatingChatState.selectedConversationId = null;
    
    const listView = document.getElementById('chatConversationListView');
    const messageView = document.getElementById('chatMessageView');
    const backBtn = document.getElementById('chatBackBtn');
    const panelTitle = document.getElementById('chatPanelTitle');
    const clearBtn = document.getElementById('chatClearBtn');
    
    if (listView) listView.classList.remove('hidden');
    if (messageView) messageView.classList.remove('active');
    if (backBtn) backBtn.classList.remove('visible');
    if (panelTitle) panelTitle.textContent = 'CHAT';
    if (clearBtn) clearBtn.style.display = 'none';
}

// Select a conversation
function selectConversation(reservationId) {
    floatingChatState.selectedConversationId = reservationId;
    floatingChatState.shouldScrollToBottom = true;
    
    // Update conversation list UI
    renderConversationsList();
    
    // Switch to message view
    const listView = document.getElementById('chatConversationListView');
    const messageView = document.getElementById('chatMessageView');
    const backBtn = document.getElementById('chatBackBtn');
    const panelTitle = document.getElementById('chatPanelTitle');
    const clearBtn = document.getElementById('chatClearBtn');
    const title = document.getElementById('chatMessageViewTitle');
    const subtitle = document.getElementById('chatMessageViewSubtitle');
    
    if (listView) listView.classList.add('hidden');
    if (messageView) messageView.classList.add('active');
    if (backBtn) backBtn.classList.add('visible');
    if (clearBtn) clearBtn.style.display = 'block';
    
    // Update header with conversation info
    const selectedConv = floatingChatState.conversations.find(c => c.reservation_id === reservationId);
    if (selectedConv) {
        if (title) title.textContent = selectedConv.other_party_name;
        if (subtitle) {
            const sum = selectedConv.product_summary || selectedConv.product_name || '';
            const ids = [selectedConv.public_ref ? ('RSV ' + selectedConv.public_ref) : '', selectedConv.chat_public_ref || ''].filter(Boolean).join(' · ');
            subtitle.innerHTML = escapeHtml(sum) + (ids ? '<div style="font-size:11px;opacity:.85;margin-top:4px">' + escapeHtml(ids) + '</div>' : '');
        }
    }
    
    // Load messages for selected conversation
    loadConversationMessages(reservationId);
    
    // Update clear button visibility
    updateClearButtonVisibility();
}

// Load messages for a specific conversation
function loadConversationMessages(reservationId, showLoading = true) {
    const messagesContainer = document.getElementById('chatMessagesContainer');
    if (!messagesContainer) return;
    
    if (showLoading) {
        messagesContainer.innerHTML = '<div class="chat-loading">Loading messages...</div>';
    }
    
    fetch(`api/get_reservation_messages.php?reservation_id=${reservationId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                floatingChatState.messages = data.messages || [];
                renderMessages(floatingChatState.messages);
                const subtitle = document.getElementById('chatMessageViewSubtitle');
                if (subtitle && (data.product_summary || data.public_ref || data.chat_public_ref)) {
                    const sum = data.product_summary || '';
                    const ids = [data.public_ref ? ('RSV ' + data.public_ref) : '', data.chat_public_ref || ''].filter(Boolean).join(' · ');
                    subtitle.innerHTML = escapeHtml(sum) + (ids ? '<div style="font-size:11px;opacity:.85;margin-top:4px">' + escapeHtml(ids) + '</div>' : '');
                }
                
                // Start typing status polling when conversation is loaded (only if not already polling)
                if (!floatingChatState.typingPollInterval) {
                    startTypingPolling();
                }
                
                // Update unread count
                setTimeout(() => {
                    updateUnreadCount();
                    loadConversations(false); // Refresh conversation list to update unread counts
                }, 100);
            } else {
                messagesContainer.innerHTML = '<div class="chat-empty-state"><p>Error: ' + (data.message || 'Failed to load messages') + '</p></div>';
            }
        })
        .catch(error => {
            console.error('Error loading messages:', error);
            messagesContainer.innerHTML = '<div class="chat-empty-state"><p>Error loading messages</p></div>';
        });
}

// Create a hash of messages to detect changes
function createMessageHash(messages) {
    if (!messages || messages.length === 0) return 'empty';
    // Create hash from message IDs and timestamps
    return messages.map(m => `${m.id}-${m.created_at}`).join('|');
}

// Render messages for selected conversation
function renderMessages(messages) {
    const messagesContainer = document.getElementById('chatMessagesContainer');
    if (!messagesContainer) return;
    
    // Create hash of current messages
    const currentHash = createMessageHash(messages);
    
    // If messages haven't changed, skip re-rendering to prevent blinking
    if (currentHash === floatingChatState.lastMessageHash && !floatingChatState.shouldScrollToBottom) {
        const isNearBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight < 100;
        if (!isNearBottom || floatingChatState.isUserScrolling) {
            return;
        }
    }
    
    // Store scroll position before updating
    const scrollTop = messagesContainer.scrollTop;
    const scrollHeight = messagesContainer.scrollHeight;
    const clientHeight = messagesContainer.clientHeight;
    const wasNearBottom = floatingChatState.shouldScrollToBottom || 
                          (scrollHeight - scrollTop - clientHeight < 100);
    
    if (!messages || messages.length === 0) {
        messagesContainer.innerHTML = '<div class="chat-empty-state"><p>No messages yet</p><p style="font-size: 0.9rem; margin-top: 0.5rem;">Start the conversation</p></div>';
        floatingChatState.lastMessageHash = 'empty';
        return;
    }
    
    let html = '';
    messages.forEach(msg => {
        if (msg.is_system || msg.message_type === 'system') {
            // System message
            let systemClass = '';
            const msgLower = msg.message_body.toLowerCase();
            if (msgLower.includes('accepted')) systemClass = 'accepted';
            else if (msgLower.includes('declined')) systemClass = 'declined';
            else if (msgLower.includes('cancelled')) systemClass = 'cancelled';
            else if (msgLower.includes('completed')) systemClass = 'completed';
            
            html += `
                <div class="chat-message-item system ${systemClass}">
                    <div class="chat-message-bubble">
                        ${escapeHtml(msg.message_body)}
                        <div class="chat-message-time">${msg.time_ago}</div>
                    </div>
                </div>
            `;
        } else {
            // Regular message
            const senderClass = msg.is_sender ? 'sender' : '';
            const deleteBtn = msg.is_sender ? 
                `<button class="chat-message-delete-btn" onclick="deleteChatMessage(${msg.id})" title="Delete message">&times;</button>` : '';
            
            // Show "Seen" indicator for messages sent by current user that have been read
            const seenIndicator = (msg.is_sender && msg.read_at) ? 
                `<div class="chat-message-seen">Seen</div>` : '';
            
            html += `
                <div class="chat-message-item ${senderClass}">
                    <div class="chat-message-bubble">
                        ${deleteBtn}
                        ${!msg.is_sender ? `<div class="chat-message-sender">${escapeHtml(msg.sender_name)}</div>` : ''}
                        <div>${escapeHtml(msg.message_body)}</div>
                        <div class="chat-message-time">${msg.time_ago}${seenIndicator}</div>
                    </div>
                </div>
            `;
        }
    });
    
    // Update the hash
    floatingChatState.lastMessageHash = currentHash;
    
    // Store typing indicator state before clearing
    const existingTypingIndicator = document.getElementById('typingIndicator');
    const typingIndicatorHTML = existingTypingIndicator ? existingTypingIndicator.outerHTML : null;
    
    // Update messages
    messagesContainer.innerHTML = html;
    
    // Re-add typing indicator if it existed before (preserve it during message refresh)
    if (typingIndicatorHTML && floatingChatState.typingPollInterval) {
        messagesContainer.insertAdjacentHTML('beforeend', typingIndicatorHTML);
    }
    
    // Restore scroll position or scroll to bottom
    if (wasNearBottom || floatingChatState.shouldScrollToBottom) {
        requestAnimationFrame(() => {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        });
    } else {
        const oldScrollRatio = scrollTop / (scrollHeight - clientHeight || 1);
        const newScrollHeight = messagesContainer.scrollHeight;
        const newScrollTop = oldScrollRatio * (newScrollHeight - messagesContainer.clientHeight);
        messagesContainer.scrollTop = Math.max(0, newScrollTop);
    }
    
    // Reset the flag after rendering
    floatingChatState.shouldScrollToBottom = false;
}

// Create a hash of messages to detect changes
function createMessageHash(messages) {
    if (!messages || messages.length === 0) return 'empty';
    // Create hash from message IDs and timestamps
    return messages.map(m => `${m.id}-${m.created_at}`).join('|');
}


// Helper function to show custom notifications (replaces alert/confirm)
function showChatNotification(message, type = 'error') {
    const colors = {
        error: '#dc2626',
        success: '#10b981',
        info: '#3b82f6',
        warning: '#f59e0b'
    };
    
    const notificationDiv = document.createElement('div');
    notificationDiv.style.cssText = `position: fixed; top: 20px; right: 20px; background: ${colors[type] || colors.error}; color: white; padding: 1rem 1.5rem; border: 2px solid #000; z-index: 10001; font-family: VT323, monospace; font-size: 1.1rem; max-width: 400px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); border-radius: 4px;`;
    notificationDiv.textContent = message;
    document.body.appendChild(notificationDiv);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        notificationDiv.style.opacity = '0';
        notificationDiv.style.transition = 'opacity 0.3s ease';
        setTimeout(() => notificationDiv.remove(), 300);
    }, 3000);
}

// Send chat message
function sendChatMessage() {
    const reservationId = floatingChatState.selectedConversationId;
    
    if (!reservationId) {
        showChatNotification('Please select a conversation first', 'warning');
        return;
    }
    
    const input = document.getElementById('chatInput');
    const sendBtn = document.getElementById('chatSendBtn');
    const message = input.value.trim();
    
    if (!message) return;
    
    sendBtn.disabled = true;
    sendBtn.textContent = 'SENDING...';
    
    fetch('api/send_reservation_message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            reservation_id: reservationId,
            message: message,
            csrf_token: '<?php echo $csrf_token; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            // Stop typing status when message is sent
            updateTypingStatus(false);
            // Set flag to force scroll to bottom after sending
            floatingChatState.shouldScrollToBottom = true;
            // Reload messages for current conversation
            loadConversationMessages(reservationId, false);
            // Refresh conversations list to update last message
            loadConversations(false);
            updateUnreadCount();
        } else {
            showChatNotification(data.message || 'Failed to send message', 'error');
        }
        sendBtn.disabled = false;
        sendBtn.textContent = 'SEND';
    })
    .catch(error => {
        console.error('Error sending message:', error);
        showChatNotification('Error sending message. Please try again.', 'error');
        sendBtn.disabled = false;
        sendBtn.textContent = 'SEND';
    });
}

// Start conversation polling
function startConversationPolling() {
    stopConversationPolling();
    floatingChatState.pollInterval = setInterval(() => {
        if (floatingChatState.isOpen) {
            // Refresh conversations list
            loadConversations(false);
            
            // Refresh messages for selected conversation
            if (floatingChatState.selectedConversationId && !floatingChatState.isUserScrolling) {
                loadConversationMessages(floatingChatState.selectedConversationId, false);
            }
        }
    }, 5000);
}

// Stop conversation polling
function stopConversationPolling() {
    if (floatingChatState.pollInterval) {
        clearInterval(floatingChatState.pollInterval);
        floatingChatState.pollInterval = null;
    }
    // Also stop typing polling
    stopTypingPolling();
}

// Update unread count
function updateUnreadCount() {
    fetch('api/get_chat_unread_count.php?csrf_token=<?php echo $csrf_token; ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                floatingChatState.unreadCount = data.unread_count || 0;
                const badge = document.getElementById('chatUnreadBadge');
                if (badge) {
                    if (floatingChatState.unreadCount > 0) {
                        badge.textContent = floatingChatState.unreadCount > 99 ? '99+' : floatingChatState.unreadCount;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error updating unread count:', error);
        });
}

// Start unread count polling
function startUnreadCountPolling() {
    setInterval(() => {
        updateUnreadCount();
    }, 30000); // Poll every 30 seconds
}

// Update typing status
function updateTypingStatus(isTyping) {
    const reservationId = floatingChatState.selectedConversationId;
    if (!reservationId) {
        console.log('Cannot update typing status: No reservation ID selected');
        return;
    }
    
    console.log('Updating typing status:', { reservationId, isTyping });
    
    fetch('api/update_typing_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            reservation_id: reservationId,
            is_typing: isTyping,
            csrf_token: '<?php echo $csrf_token; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Typing status update failed:', data.message);
        } else {
            console.log('Typing status updated successfully');
        }
    })
    .catch(error => {
        console.error('Error updating typing status:', error);
    });
}

// Render typing indicator
function renderTypingIndicator(typingUsers) {
    const messagesContainer = document.getElementById('chatMessagesContainer');
    if (!messagesContainer) return;
    
    const existingIndicator = document.getElementById('typingIndicator');
    
    // Remove existing indicator
    if (existingIndicator) {
        existingIndicator.remove();
    }
    
    // Add new indicator if someone is typing
    if (typingUsers && typingUsers.length > 0) {
        const typingNames = typingUsers.map(u => escapeHtml(u.user_name)).join(', ');
        const indicator = document.createElement('div');
        indicator.id = 'typingIndicator';
        indicator.className = 'chat-typing-indicator';
        indicator.innerHTML = `
            <div class="chat-typing-dots">
                <span></span><span></span><span></span>
            </div>
            <span class="chat-typing-text">${typingNames} ${typingUsers.length === 1 ? 'is' : 'are'} typing...</span>
        `;
        messagesContainer.appendChild(indicator);
        
        // Scroll to bottom to show typing indicator
        requestAnimationFrame(() => {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        });
    }
}

// Start typing status polling
function startTypingPolling() {
    stopTypingPolling();
    
    const reservationId = floatingChatState.selectedConversationId;
    if (!reservationId) {
        console.log('Cannot start typing polling: No reservation ID selected');
        return;
    }
    
    console.log('Starting typing status polling for reservation:', reservationId);
    
    // Poll every 1 second for typing status
    floatingChatState.typingPollInterval = setInterval(() => {
        const currentReservationId = floatingChatState.selectedConversationId;
        if (!currentReservationId || !floatingChatState.isOpen) {
            return;
        }
        
        fetch(`api/get_typing_status.php?reservation_id=${currentReservationId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Typing status API response:', data);
                if (data.success) {
                    // Log debug info if available
                    if (data.debug) {
                        console.log('Debug info:', {
                            reservation_id: data.debug.reservation_id,
                            viewer_user_id: data.debug.viewer_user_id,
                            all_statuses_count: data.debug.all_statuses_count,
                            typing_users_count: data.debug.typing_users_count,
                            all_statuses: data.debug.all_statuses
                        });
                        
                        // Show what's in the database
                        if (data.debug.all_statuses && data.debug.all_statuses.length > 0) {
                            console.log('All typing statuses in database:', data.debug.all_statuses);
                            data.debug.all_statuses.forEach(status => {
                                console.log(`  - User ${status.user_id} (${status.username || status.full_name || 'unknown'}): is_typing=${status.is_typing}, seconds_ago=${status.seconds_ago || 'N/A'}`);
                            });
                        } else {
                            console.log('No typing statuses found in database at all');
                        }
                    }
                    
                    if (data.typing_users && data.typing_users.length > 0) {
                        console.log('Typing users detected:', data.typing_users);
                    } else {
                        console.log('No typing users found (empty array or null)');
                    }
                    renderTypingIndicator(data.typing_users || []);
                } else {
                    console.error('Failed to get typing status:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching typing status:', error);
            });
    }, 1000);
}

// Stop typing status polling
function stopTypingPolling() {
    if (floatingChatState.typingPollInterval) {
        clearInterval(floatingChatState.typingPollInterval);
        floatingChatState.typingPollInterval = null;
    }
    
    // Remove typing indicator when stopping
    const existingIndicator = document.getElementById('typingIndicator');
    if (existingIndicator) {
        existingIndicator.remove();
    }
}

// Helper functions
function formatTimeAgo(timestamp) {
    if (!timestamp) return '';
    const now = new Date();
    const time = new Date(timestamp);
    const diff = Math.floor((now - time) / 1000);
    
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hour' + (Math.floor(diff / 3600) > 1 ? 's' : '') + ' ago';
    if (diff < 604800) return Math.floor(diff / 86400) + ' day' + (Math.floor(diff / 86400) > 1 ? 's' : '') + ' ago';
    return time.toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Delete a chat message
let deleteMessageId = null;

function deleteChatMessage(messageId) {
    deleteMessageId = messageId;
    const modal = document.getElementById('chatDeleteMessageModal');
    if (modal) {
        modal.classList.add('active');
    }
}

// Close delete message modal
function closeDeleteMessageModal() {
    const modal = document.getElementById('chatDeleteMessageModal');
    if (modal) {
        modal.classList.remove('active');
    }
    deleteMessageId = null;
}

// Confirm delete message
function confirmDeleteMessage() {
    if (!deleteMessageId) {
        closeDeleteMessageModal();
        return;
    }
    
    fetch('api/delete_chat_message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            message_id: deleteMessageId,
            csrf_token: '<?php echo $csrf_token; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteMessageModal();
            // Reload messages for current conversation
            if (floatingChatState.selectedConversationId) {
                loadConversationMessages(floatingChatState.selectedConversationId, false);
            }
            // Refresh conversations list
            loadConversations(false);
            updateUnreadCount();
        } else {
            closeDeleteMessageModal();
            const errorMsg = data.message || 'Failed to delete message';
            showChatNotification(errorMsg, 'error');
        }
    })
    .catch(error => {
        console.error('Error deleting message:', error);
        closeDeleteMessageModal();
        showChatNotification('Error deleting message. Please try again.', 'error');
    });
}

// Show clear chat modal
let clearChatReservationId = null;

function showClearChatModal() {
    const reservationId = floatingChatState.selectedConversationId;
    
    if (!reservationId) {
        showChatNotification('Please select a conversation first', 'warning');
        return;
    }
    
    clearChatReservationId = reservationId;
    const modal = document.getElementById('chatClearModal');
    if (modal) {
        modal.classList.add('active');
    }
}

// Close clear chat modal
function closeClearChatModal() {
    const modal = document.getElementById('chatClearModal');
    if (modal) {
        modal.classList.remove('active');
    }
    clearChatReservationId = null;
}

// Confirm clear chat
function confirmClearChat() {
    if (!clearChatReservationId) {
        closeClearChatModal();
        return;
    }
    
    fetch('api/clear_chat_messages.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            reservation_id: clearChatReservationId,
            csrf_token: '<?php echo $csrf_token; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeClearChatModal();
            // Reload messages for current conversation
            if (floatingChatState.selectedConversationId) {
                loadConversationMessages(floatingChatState.selectedConversationId, false);
            }
            // Refresh conversations list
            loadConversations(false);
            updateUnreadCount();
            showChatNotification('Chat cleared successfully.', 'success');
        } else {
            const errorMsg = data.message || 'Failed to clear chat';
            showChatNotification(errorMsg, 'error');
        }
    })
    .catch(error => {
        console.error('Error clearing chat:', error);
        showChatNotification('Error clearing chat. Please try again.', 'error');
    });
}

// Show/hide clear button based on selected conversation
function updateClearButtonVisibility() {
    const clearBtn = document.getElementById('chatClearBtn');
    
    if (clearBtn) {
        if (floatingChatState.selectedConversationId) {
            clearBtn.style.display = 'block';
        } else {
            clearBtn.style.display = 'none';
        }
    }
}

// Hide reservation from chat
let hideReservationId = null;

function hideReservationFromChat(reservationId) {
    hideReservationId = reservationId;
    const modal = document.getElementById('chatHideReservationModal');
    if (modal) {
        modal.classList.add('active');
    }
}

// Close hide reservation modal
function closeHideReservationModal() {
    const modal = document.getElementById('chatHideReservationModal');
    if (modal) {
        modal.classList.remove('active');
    }
    hideReservationId = null;
}

// Confirm hide reservation
function confirmHideReservation() {
    if (!hideReservationId) {
        closeHideReservationModal();
        return;
    }
    
    fetch('api/hide_reservation_from_chat.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            reservation_id: hideReservationId,
            hide: 1,
            csrf_token: '<?php echo $csrf_token; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeHideReservationModal();
            // Refresh conversations list (will filter out hidden reservation)
            loadConversations(false);
            updateUnreadCount();
            
            // If hidden reservation was selected, return to conversation list
            if (floatingChatState.selectedConversationId === hideReservationId) {
                showConversationList();
            }
            
            // Show success notification
            showChatNotification('Reservation hidden from chat.', 'success');
        } else {
            closeHideReservationModal();
            showChatNotification(data.message || 'Failed to hide reservation', 'error');
        }
    })
    .catch(error => {
        console.error('Error hiding reservation:', error);
        closeHideReservationModal();
        showChatNotification('Error hiding reservation. Please try again.', 'error');
    });
}

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    
    // Close modals on backdrop click
    const clearModal = document.getElementById('chatClearModal');
    if (clearModal) {
        clearModal.addEventListener('click', function(e) {
            if (e.target === clearModal) {
                closeClearChatModal();
            }
        });
    }
    
    const deleteModal = document.getElementById('chatDeleteMessageModal');
    if (deleteModal) {
        deleteModal.addEventListener('click', function(e) {
            if (e.target === deleteModal) {
                closeDeleteMessageModal();
            }
        });
    }
    
    const hideModal = document.getElementById('chatHideReservationModal');
    if (hideModal) {
        hideModal.addEventListener('click', function(e) {
            if (e.target === hideModal) {
                closeHideReservationModal();
            }
        });
    }
});
</script>

