@extends('layouts.app')

@section('title', 'Messages')

@section('styles')
<style>
    .messages-container {
        display: flex;
        gap: 1.5rem;
        height: calc(100vh - 200px);
        min-height: 500px;
    }

    .users-list-container {
        width: 320px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .users-list-header {
        padding: 1.25rem;
        border-bottom: 1px solid #e5e7eb;
        background: #f9fafb;
    }

    .users-list-header h3 {
        font-size: 1rem;
        font-weight: 600;
        color: #1f2937;
        margin: 0 0 0.75rem 0;
    }

    .user-search-box {
        position: relative;
        width: 100%;
    }

    .user-search-input {
        width: 100%;
        padding: 0.5rem 0.75rem 0.5rem 2rem;
        border: 1.5px solid #d1d5db;
        border-radius: 6px;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
    }

    .user-search-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .user-search-icon {
        position: absolute;
        left: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
        width: 16px;
        height: 16px;
        fill: #9ca3af;
        stroke: #9ca3af;
        stroke-width: 2;
    }

    .users-list {
        flex: 1;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .user-item {
        padding: 0.875rem;
        margin-bottom: 0.5rem;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border: 1px solid transparent;
        position: relative;
    }

    .user-item:hover {
        background: #f3f4f6;
        border-color: #e5e7eb;
    }

    .user-item.active {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border-color: #3b82f6;
        box-shadow: 0 1px 3px rgba(59, 130, 246, 0.2);
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.875rem;
        flex-shrink: 0;
    }

    .user-info {
        flex: 1;
        min-width: 0;
    }

    .user-name {
        font-weight: 500;
        color: #1f2937;
        font-size: 0.875rem;
        margin-bottom: 0.125rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Privacy Mode: Hide Name & Email when searching by ID */
    .users-list.mode-id .user-real-name,
    .users-list.mode-id .user-email {
        display: none;
    }

    .users-list.mode-id .user-id-display {
        display: block;
        font-weight: 600;
        color: #111827;
        font-size: 0.95rem;
    }

    .users-list.mode-name .user-id-display {
        display: none;
    }

    .user-real-name {
        font-weight: 600;
        color: #111827;
    }

    .user-id-display {
        display: none; /* Hidden by default in name mode */
        color: #4b5563;
        font-family: monospace;
    }

    .user-email {
        font-size: 0.75rem;
        color: #6b7280;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .search-type-toggle {
        display: flex;
        gap: 1rem;
        margin-bottom: 0.75rem;
        padding: 0 0.5rem;
    }

    .search-type-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8rem;
        color: #4b5563;
        cursor: pointer;
    }

    .search-type-label input {
        accent-color: #3b82f6;
    }

    .user-unread-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: #ef4444;
        color: white;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.25rem 0.5rem;
        border-radius: 10px;
        min-width: 20px;
        text-align: center;
        display: none;
    }

    .user-item.has-unread .user-unread-badge {
        display: block;
    }

    .chat-container {
        flex: 1;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .chat-header {
        padding: 1.25rem;
        border-bottom: 1px solid #e5e7eb;
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .chat-header-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        flex-shrink: 0;
    }

    .chat-header-info h3 {
        font-size: 1rem;
        font-weight: 600;
        color: #1f2937;
        margin: 0 0 0.25rem 0;
    }

    .chat-header-info p {
        font-size: 0.75rem;
        color: #6b7280;
        margin: 0;
    }

    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        background: #fafbfc;
    }

    .empty-state {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 1rem;
        color: #6b7280;
    }

    .empty-state svg {
        width: 64px;
        height: 64px;
        opacity: 0.3;
        fill: currentColor;
    }

    .empty-state-text {
        font-size: 1.125rem;
        font-weight: 500;
    }

    .message-bubble {
        max-width: 70%;
        padding: 0.875rem 1.125rem;
        border-radius: 12px;
        word-wrap: break-word;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .message-sent {
        align-self: flex-end;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border-bottom-right-radius: 4px;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
    }

    .message-received {
        align-self: flex-start;
        background: white;
        color: #1f2937;
        border-bottom-left-radius: 4px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .message-text {
        margin: 0;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .message-time {
        font-size: 0.7rem;
        opacity: 0.7;
        margin-top: 0.25rem;
    }

    .chat-input-container {
        padding: 1.25rem;
        border-top: 1px solid #e5e7eb;
        background: white;
    }

    .chat-input-form {
        display: flex;
        gap: 0.75rem;
    }

    .chat-input {
        flex: 1;
        padding: 0.75rem 1rem;
        border: 1.5px solid #e5e7eb;
        border-radius: 8px;
        font-size: 0.875rem;
        font-family: inherit;
        resize: none;
        min-height: 44px;
        max-height: 120px;
        transition: border-color 0.2s ease;
    }

    .chat-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .send-button {
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
    }

    .send-button:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        transform: translateY(-1px);
    }

    .send-button:disabled {
        background: #9ca3af;
        cursor: not-allowed;
        box-shadow: none;
        transform: none;
    }

    .send-button svg {
        width: 16px;
        height: 16px;
        fill: currentColor;
    }

    /* Notification Toast */
    .notification-toast {
        position: fixed;
        bottom: 20px;
        left: 20px;
        background: white;
        border-left: 4px solid #10b981;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        padding: 1rem 1.25rem;
        z-index: 1000;
        animation: slideInLeft 0.3s ease;
        max-width: 320px;
        display: none;
    }

    .notification-toast.show {
        display: block;
    }

    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .notification-toast.hide {
        animation: slideOutLeft 0.3s ease forwards;
    }

    @keyframes slideOutLeft {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(-100px);
        }
    }

    .notification-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.25rem;
        font-size: 0.875rem;
    }

    .notification-icon {
        width: 20px;
        height: 20px;
        color: #10b981;
    }

    .notification-message {
        font-size: 0.8rem;
        color: #6b7280;
        line-height: 1.4;
    }

    .notification-close {
        position: absolute;
        top: 8px;
        right: 8px;
        background: none;
        border: none;
        color: #9ca3af;
        cursor: pointer;
        font-size: 1.25rem;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
    }

    .notification-close:hover {
        color: #6b7280;
    }

    /* Typing Indicator */
    .typing-indicator {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1rem;
        background: #f3f4f6;
        border-radius: 12px;
        width: fit-content;
        border-bottom-left-radius: 4px;
    }

    .typing-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #6b7280;
        animation: typing 1.4s infinite;
    }

    .typing-dot:nth-child(2) {
        animation-delay: 0.2s;
    }

    .typing-dot:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes typing {
        0%, 60%, 100% {
            opacity: 0.3;
            transform: translateY(0);
        }
        30% {
            opacity: 1;
            transform: translateY(-10px);
        }
    }

    .typing-text {
        font-size: 0.75rem;
        color: #6b7280;
        margin-left: 0.25rem;
        font-style: italic;
    }

    /* Scrollbar Styling */
    .users-list::-webkit-scrollbar,
    .chat-messages::-webkit-scrollbar {
        width: 6px;
    }

    .users-list::-webkit-scrollbar-track,
    .chat-messages::-webkit-scrollbar-track {
        background: #f3f4f6;
        border-radius: 3px;
    }

    .users-list::-webkit-scrollbar-thumb,
    .chat-messages::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 3px;
    }

    .users-list::-webkit-scrollbar-thumb:hover,
    .chat-messages::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }

    @media (max-width: 768px) {
        .messages-container {
            flex-direction: column;
            height: auto;
        }

        .users-list-container {
            width: 100%;
            max-height: 300px;
        }

        .chat-container {
            min-height: 400px;
        }

        .message-bubble {
            max-width: 85%;
        }
    }
</style>
@endsection

@section('content')
<div class="content-header">
    <h1 class="content-title">Messages</h1>
    <p class="content-subtitle">Communication and notifications center</p>
</div>

<div class="messages-container">
    <!-- Users List -->
    <div class="users-list-container">
        <div class="users-list-header">
            <h3>Users</h3>
            
            <div class="search-type-toggle">
                <label class="search-type-label">
                    <input type="radio" name="searchType" value="name" checked onchange="toggleSearchMode()"> Name/Email
                </label>
                <label class="search-type-label">
                    <input type="radio" name="searchType" value="id" onchange="toggleSearchMode()"> User ID
                </label>
            </div>

            <div class="user-search-box">
                <svg class="user-search-icon" viewBox="0 0 24 24" fill="none">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
                <input 
                    type="text" 
                    class="user-search-input" 
                    placeholder="Search users..." 
                    id="userSearchInput"
                    onkeyup="searchUsers()"
                >
            </div>
        </div>
        <div class="users-list mode-name" id="usersList">
            @forelse($users as $user)
                <div class="user-item" data-user-id="{{ $user->id }}" data-user-name="{{ addslashes($user->firstname) }} {{ addslashes($user->lastname) }}" data-user-id-number="{{ str_pad($user->id, 5, '0', STR_PAD_LEFT) }}">
                    <div class="user-avatar">
                        {{ strtoupper(substr($user->firstname, 0, 1)) }}{{ strtoupper(substr($user->lastname, 0, 1)) }}
                    </div>
                    <div class="user-info">
                        <div class="user-real-name">{{ $user->firstname }} {{ $user->lastname }}</div>
                        <div class="user-id-display">ID: {{ str_pad($user->id, 5, '0', STR_PAD_LEFT) }}</div>
                        <div class="user-email">{{ $user->email }}</div>
                    </div>
                    <div class="user-unread-badge">0</div>
                </div>
            @empty
                <div class="empty-state">
                    <p>No users found</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Chat Container -->
    <div class="chat-container">
        <div class="chat-header" id="chatHeader">
            <h3 id="chatHeaderTitle">Select a user to start chatting</h3>
        </div>
        <div class="chat-messages" id="chatMessages">
            <div class="empty-state">
                <svg viewBox="0 0 24 24">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <p class="empty-state-text">Select a user to start chatting</p>
            </div>
        </div>
        <div class="chat-input-container" id="chatInputContainer" style="display: none;">
            <form class="chat-input-form" id="messageForm">
                <textarea 
                    class="chat-input" 
                    id="messageInput" 
                    placeholder="Type a message..." 
                    rows="1"
                    required
                ></textarea>
                <button type="submit" class="send-button" id="sendButton">
                    <svg viewBox="0 0 24 24">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                    Send
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Notification Toast -->
<div class="notification-toast" id="notificationToast">
    <button class="notification-close" onclick="closeNotification()">&times;</button>
    <div class="notification-header">
        <svg class="notification-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        <span id="notificationUsername">New Message</span>
    </div>
    <div class="notification-message" id="notificationMessage">You have a new message</div>
</div>

<!-- Hidden data for JavaScript -->
<div id="app-data" 
     data-current-admin-id="{{ auth()->id() }}" 
     style="display: none;">
</div>
@endsection

@section('scripts')
<script>
    let currentUserId = null;
    let currentUserName = '';
    let messageCheckInterval = null;
    let typingCheckInterval = null;
    let lastMessageTime = null;
    let typingTimeout = null;
    let isCurrentlyTyping = false;
    
    // User interaction tracking for auto-refresh buffer
    let userScrollTimeout = null;
    let isUserScrolling = false;
    let lastScrollTop = 0;
    let isAtBottom = true;

    // Get data from HTML attributes
    const appData = {
        currentAdminId: parseInt(document.getElementById('app-data').dataset.currentAdminId)
    };

    document.addEventListener('DOMContentLoaded', function() {
        // Add click event listeners to user items
        document.querySelectorAll('.user-item').forEach(item => {
            item.addEventListener('click', function() {
                const userId = parseInt(this.dataset.userId);
                const userName = this.dataset.userName;
                selectUser(userId, userName);
            });
        });

        // Add scroll listener to chat messages to detect user interaction
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.addEventListener('scroll', function() {
                // Mark that user is actively scrolling
                isUserScrolling = true;
                
                // Check if user is at the bottom
                const scrollThreshold = 50; // pixels from bottom
                isAtBottom = (this.scrollHeight - this.scrollTop - this.clientHeight) < scrollThreshold;
                
                // Clear previous timeout
                if (userScrollTimeout) {
                    clearTimeout(userScrollTimeout);
                }
                
                // After 3 seconds of no scrolling, resume normal auto-refresh
                userScrollTimeout = setTimeout(() => {
                    isUserScrolling = false;
                }, 3000);
                
                lastScrollTop = this.scrollTop;
            });
        }

        // Add submit event listener to message form
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessage(e);
        });

        // Auto-resize textarea
        const messageInput = document.getElementById('messageInput');
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            
            // Send typing indicator
            if (currentUserId) {
                sendTypingStatus(true);
                
                // Clear previous timeout
                if (typingTimeout) clearTimeout(typingTimeout);
                
                // Set timeout to clear typing status after 3 seconds of inactivity
                typingTimeout = setTimeout(() => {
                    sendTypingStatus(false);
                    isCurrentlyTyping = false;
                }, 3000);
            }
        });

        // Allow Enter to send, Shift+Enter for new line
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('messageForm').dispatchEvent(new Event('submit'));
            }
        });

        // Load conversations list and monitor for new messages
        loadConversationsList();
        setInterval(loadConversationsList, 30000); // Check every 30 seconds
        // Also refresh on live socket events
        window.addEventListener('adminLiveUpdate', loadConversationsList);
    });

    function selectUser(userId, userName) {
        currentUserId = userId;
        currentUserName = userName;
        lastMessageTime = null;
        
        // Reset scroll tracking when switching conversations
        isUserScrolling = false;
        isAtBottom = true;
        if (userScrollTimeout) {
            clearTimeout(userScrollTimeout);
        }

        // Update active state
        document.querySelectorAll('.user-item').forEach(item => {
            item.classList.remove('active');
        });
        const userItem = document.querySelector(`[data-user-id="${userId}"]`);
        userItem.classList.add('active');
        userItem.classList.remove('has-unread');
        userItem.querySelector('.user-unread-badge').textContent = '0';

        // Get id_number from data attribute
        const userIdNumber = userItem.dataset.userIdNumber || 'Unknown';
        // Generate initials from name instead of ID
        const initials = userName.split(' ').map(n => n.charAt(0)).join('').substring(0, 2).toUpperCase();
        
        // Update chat header to show ID number instead of name
        document.getElementById('chatHeader').innerHTML = `
            <div class="chat-header-avatar">${initials}</div>
            <div class="chat-header-info">
                <h3>ID: ${escapeHtml(userIdNumber)}</h3>
                <p>Active now</p>
            </div>
        `;

        // Show input container
        document.getElementById('chatInputContainer').style.display = 'block';

        // Load conversation
        loadConversation(userId);

        // Set up auto-refresh for messages
        if (messageCheckInterval) {
            clearInterval(messageCheckInterval);
        }
        messageCheckInterval = setInterval(() => loadConversation(userId, true), 10000);
        
        // Set up auto-refresh for typing status
        if (typingCheckInterval) {
            clearInterval(typingCheckInterval);
        }
        typingCheckInterval = setInterval(() => checkUserTypingStatus(userId), 3000);
        // Also refresh messages on live socket events
        window.addEventListener('adminLiveUpdate', () => {
            if (currentUserId) loadConversation(currentUserId, true);
        });
    }

    function loadConversation(userId, isBackground = false) {
        // Skip refresh if user is actively scrolling through messages
        if (isUserScrolling) {
            return;
        }
        
        if (!isBackground) {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
               // Optional: showing a small spinner inside chat area instead of full screen
               // But for consistency with "system-wide", we can use global or a local one.
               // Let's use global for initial load as it might be perceived as a "page navigate" within the app.
               // Or better: show a local spinner in chatMessages to avoid blocking the whole UI if they want to click other users.
               // However, user said "match the color of the spinner to the ui of overall system".
               // Let's use the global showLoading for the AUTHORITATIVE load.
               showLoading('Loading conversation...');
            }
        }
        
        fetch(`/messages/conversation/${userId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMessages(data.messages);
                
                if (data.messages.length > 0) {
                    lastMessageTime = new Date(data.messages[data.messages.length - 1].sent_at);
                }
            }
        })
        .catch(error => {
            // Silent fail - don't show errors during background refresh
            console.error(error);
        })
        .finally(() => {
            if (!isBackground) {
                hideLoading();
            }
        });
    }

    function checkUserTypingStatus(userId) {
        fetch(`/messages/typing-status/${userId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayTypingIndicator(data.is_typing);
            }
        })
        .catch(error => {
            // Silent fail
        });
    }

    function displayMessages(messages) {
        const chatMessages = document.getElementById('chatMessages');
        const currentAdminId = appData.currentAdminId;
        
        // Store current scroll position
        const previousScrollTop = chatMessages.scrollTop;
        const previousScrollHeight = chatMessages.scrollHeight;

        if (messages.length === 0) {
            chatMessages.innerHTML = `
                <div class="empty-state">
                    <svg viewBox="0 0 24 24">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <p class="empty-state-text">No messages yet. Start the conversation!</p>
                </div>
            `;
            return;
        }

        chatMessages.innerHTML = messages.map(msg => {
            const isSent = msg.sender_id === currentAdminId;
            const messageClass = isSent ? 'message-sent' : 'message-received';
            
            // Format time in Philippine time
            let time;
            try {
                time = new Date(msg.sent_at).toLocaleString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true,
                    month: 'short',
                    day: 'numeric',
                    timeZone: 'Asia/Manila'
                });
            } catch (e) {
                // Fallback if timeZone is not supported
                time = new Date(msg.sent_at).toLocaleString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true,
                    month: 'short',
                    day: 'numeric'
                });
            }

            return `
                <div class="message-bubble ${messageClass}">
                    <p class="message-text">${escapeHtml(msg.message)}</p>
                    <div class="message-time">${time}</div>
                </div>
            `;
        }).join('');

        // Only auto-scroll if user was already at the bottom or not actively viewing
        // This prevents interrupting the user while they're reading older messages
        if (isAtBottom || !isUserScrolling) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        } else {
            // Maintain scroll position relative to content
            // Adjust scroll to account for new content at the bottom
            const newScrollHeight = chatMessages.scrollHeight;
            const heightDifference = newScrollHeight - previousScrollHeight;
            chatMessages.scrollTop = previousScrollTop + heightDifference;
        }
    }

    function displayTypingIndicator(isTyping) {
        const chatMessages = document.getElementById('chatMessages');
        const existingIndicator = chatMessages.querySelector('.typing-indicator');

        if (isTyping && !existingIndicator) {
            // Create typing indicator
            const indicator = document.createElement('div');
            indicator.className = 'typing-indicator message-bubble message-received';
            indicator.innerHTML = `
                <div style="display: flex; gap: 0.35rem; align-items: center;">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
                <div class="typing-text">typing...</div>
            `;
            chatMessages.appendChild(indicator);
            
            // Only auto-scroll if user is at the bottom
            if (isAtBottom || !isUserScrolling) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        } else if (!isTyping && existingIndicator) {
            // Remove typing indicator
            existingIndicator.remove();
        }
    }

    function loadConversationsList() {
        fetch('/messages/list', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.conversations) {
                // Update unread counts in the UI
                const userItems = document.querySelectorAll('.user-item');
                userItems.forEach(item => {
                    const userId = parseInt(item.dataset.userId);
                    const conv = data.conversations.find(c => c.id === userId);
                    
                    if (conv && conv.unread_count > 0) {
                        item.classList.add('has-unread');
                        item.querySelector('.user-unread-badge').textContent = conv.unread_count;

                        // Show notification if not currently viewing this chat
                        if (currentUserId !== userId) {
                            showNotification(conv.firstname + ' ' + conv.lastname, conv.last_message);
                        }
                    } else {
                        item.classList.remove('has-unread');
                        item.querySelector('.user-unread-badge').textContent = '0';
                    }
                });

                // Sort users list by most recent conversation
                const usersList = document.getElementById('usersList');
                const sorted = Array.from(userItems).sort((a, b) => {
                    const aId = parseInt(a.dataset.userId);
                    const bId = parseInt(b.dataset.userId);
                    const aConv = data.conversations.find(c => c.id === aId);
                    const bConv = data.conversations.find(c => c.id === bId);
                    
                    if (!aConv || !bConv) return 0;
                    return new Date(bConv.last_message_time) - new Date(aConv.last_message_time);
                });

                usersList.innerHTML = '';
                sorted.forEach(item => usersList.appendChild(item));
            }
        })
        .catch(error => {
            console.error('Error loading conversations list:', error);
        });
    }

    function sendMessage(event) {
        event.preventDefault();

        if (!currentUserId) {
            return;
        }

        const messageInput = document.getElementById('messageInput');
        const message = messageInput.value.trim();

        if (!message) {
            return;
        }

        const sendButton = document.getElementById('sendButton');
        sendButton.disabled = true;
        
        // Optional: Show global loading for send? It might be annoying if it blocks typing.
        // But user asked for "anything that is possible to take a while".
        // Let's show it to be safe and responsive.
        showLoading('Sending...');

        fetch('/messages/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                receiver_id: currentUserId,
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageInput.value = '';
                messageInput.style.height = 'auto';
                
                // When user sends a message, mark as at bottom and reload
                isAtBottom = true;
                isUserScrolling = false;
                
                loadConversation(currentUserId, true); // Use background mode to avoid double spinner
                loadConversationsList();
            }
        })
        .catch(error => {
            console.error('Error sending message:', error);
            alert('Failed to send message. Please try again.');
        })
        .finally(() => {
            sendButton.disabled = false;
            messageInput.focus();
            hideLoading();
        });
    }

    function showNotification(userName, message) {
        const toast = document.getElementById('notificationToast');
        const username = document.getElementById('notificationUsername');
        const notificationMessage = document.getElementById('notificationMessage');

        username.textContent = userName;
        notificationMessage.textContent = message.substring(0, 100) + (message.length > 100 ? '...' : '');

        toast.classList.remove('hide');
        toast.classList.add('show');

        // Auto-hide after 5 seconds
        setTimeout(() => {
            closeNotification();
        }, 5000);
    }

    function closeNotification() {
        const toast = document.getElementById('notificationToast');
        toast.classList.add('hide');
        toast.classList.remove('show');
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function sendTypingStatus(isTyping) {
        if (!currentUserId) return;

        fetch('/messages/typing', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                receiver_id: currentUserId,
                is_typing: isTyping
            })
        })
        .catch(error => {
            // Silent fail
        });
    }

    function toggleSearchMode() {
        const searchType = document.querySelector('input[name="searchType"]:checked').value;
        const usersList = document.getElementById('usersList');
        const input = document.getElementById('userSearchInput');
        
        // Reset classes
        usersList.classList.remove('mode-name', 'mode-id');
        usersList.classList.add('mode-' + searchType);
        
        // Update placeholder
        if (searchType === 'id') {
            input.placeholder = "Enter User ID...";
        } else {
            input.placeholder = "Enter Name or Email...";
        }
        
        // Re-run search if there's a value
        searchUsers();
    }

    function searchUsers() {
        const input = document.getElementById('userSearchInput');
        const filter = input.value.toUpperCase();
        const usersList = document.getElementById('usersList');
        const userItems = usersList.getElementsByClassName('user-item');
        const searchType = document.querySelector('input[name="searchType"]:checked').value;
        
        for (let i = 0; i < userItems.length; i++) {
            const item = userItems[i];
            let match = false;
            
            if (searchType === 'id') {
                // Search ONLY by ID Number
                const userIdNumber = item.dataset.userIdNumber ? item.dataset.userIdNumber.toUpperCase() : '';
                if (userIdNumber.indexOf(filter) > -1) {
                    match = true;
                }
            } else {
                // Search by Name OR Email
                const userName = item.dataset.userName.toUpperCase();
                const userEmail = item.querySelector('.user-email').textContent.toUpperCase();
                
                if (userName.indexOf(filter) > -1 || userEmail.indexOf(filter) > -1) {
                    match = true;
                }
            }
            
            item.style.display = match ? '' : 'none';
        }
    }

    // Clean up interval on page unload
    window.addEventListener('beforeunload', function() {
        if (messageCheckInterval) {
            clearInterval(messageCheckInterval);
        }
        if (typingCheckInterval) {
            clearInterval(typingCheckInterval);
        }
        if (typingTimeout) {
            clearTimeout(typingTimeout);
        }
        // Clear typing status when leaving
        if (isCurrentlyTyping && currentUserId) {
            sendTypingStatus(false);
        }
    });
</script>
@endsection
