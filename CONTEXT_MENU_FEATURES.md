# Message Context Menu Implementation

A comprehensive context menu system for chat messages with support for reply, reaction, edit, delete, forward, info, read receipts, bookmarks, flags, and more.

## Features Implemented

### 🎯 Context Menu Actions
- **Reply** - Reply to any message with keyboard shortcut (⌘R)
- **Forward** - Forward messages to multiple conversations with search
- **Copy** - Copy message content to clipboard (⌘C)
- **Quote** - Quote message in new message
- **Edit** - Edit own messages within 24 hours (⌘E)
- **Pin/Unpin** - Pin important messages to conversation
- **Bookmark** - Bookmark messages for personal reference (⌘B)
- **Flag** - Flag inappropriate content for moderation
- **Message Info** - Detailed message metadata dialog (⌘I)
- **Download** - Download file attachments
- **Delete** - Delete own messages or as moderator

### 🚀 User Experience
- **Right-click Context Menu** - Native right-click support on message bubbles
- **Hover Actions** - Quick actions visible on message hover
- **Keyboard Shortcuts** - Full keyboard navigation support
- **Touch Support** - Long-press support for mobile devices
- **Visual Feedback** - Loading states, success/error toasts

### 🎨 UI Components
- **Quick Reactions** - One-click emoji reactions (👍❤️😂😮😢😡👎🔥)
- **Search & Filter** - Conversation search in forward dialog
- **Batch Actions** - Select multiple conversations for forwarding
- **Rich Metadata** - Complete message info with timestamps, read receipts
- **Responsive Design** - Works on all screen sizes

## File Structure

### Frontend Components
```
resources/js/components/chat/
├── MessageContextMenu.tsx              # Main context menu component
├── MessageBubbleWithContextMenu.tsx    # Wrapper with right-click support
├── MessageBubbleContextMenu.tsx        # Context menu integration helper
└── message-bubble.tsx                  # Enhanced message bubble
```

### Backend API Endpoints
```
app/Http/Controllers/Api/Chat/MessageController.php
```

New endpoints added:
- `POST /api/v1/chat/conversations/{conversation}/messages/{message}/pin`
- `POST /api/v1/chat/conversations/{conversation}/messages/{message}/bookmark` 
- `POST /api/v1/chat/conversations/{conversation}/messages/{message}/flag`
- `GET /api/v1/chat/conversations/{conversation}/messages/{message}/read-receipts`
- `GET /api/v1/chat/conversations/{conversation}/messages/{message}/download`

## Context Menu Features Detail

### Message Info Dialog
Displays comprehensive message metadata:
- **Message Content** - Decrypted content with encryption status
- **Sender Details** - User information and avatar
- **Timestamps** - Created, edited, and read times
- **Delivery Status** - Sent, delivered, read status with icons
- **File Details** - Filename, size, type for attachments
- **Read Receipts** - Who has read the message and when
- **Message Properties** - Badges for edited, forwarded, pinned, bookmarked, flagged status
- **Technical Details** - Message ID, sender ID, file URLs for debugging

### Forward Message Dialog
Advanced forwarding capabilities:
- **Conversation Search** - Real-time search through available conversations
- **Multi-select** - Forward to multiple conversations at once
- **Message Preview** - Preview of message being forwarded
- **Participant Count** - Shows member count for each conversation
- **Permission Checking** - Only shows conversations user can send to
- **Progress Tracking** - Loading states and success feedback

### Keyboard Shortcuts
- **⌘R** - Reply to message
- **⌘C** - Copy message content
- **⌘E** - Edit message (if own and within 24h)
- **⌘I** - Show message info dialog
- **⌘B** - Toggle bookmark
- **Escape** - Close context menu/dialogs

### Permission System
- **Own Messages** - Can edit, delete, pin, bookmark
- **Moderators** - Can delete any message, view all read receipts
- **All Users** - Can reply, forward, react, flag, bookmark
- **File Access** - Download only if participant in conversation

### Security Features
- **E2EE Compatible** - All operations work with encrypted messages
- **Audit Logging** - All actions logged for security monitoring
- **Rate Limiting** - Prevents abuse of message actions
- **Access Control** - Conversation membership required
- **Content Moderation** - Flag inappropriate content

## Usage Examples

### Basic Context Menu
```tsx
import { MessageContextMenu } from '@/components/chat/MessageContextMenu';

<MessageContextMenu
  message={message}
  currentUserId={currentUser.id}
  conversations={conversations}
  onReply={handleReply}
  onEdit={handleEdit}
  onDelete={handleDelete}
  onForward={handleForward}
  onAddReaction={handleReaction}
  onPin={handlePin}
  onBookmark={handleBookmark}
  onFlag={handleFlag}
  onDownload={handleDownload}
  onQuote={handleQuote}
/>
```

### Right-click Context Menu
```tsx
import MessageBubbleWithContextMenu from '@/components/chat/MessageBubbleWithContextMenu';

<MessageBubbleWithContextMenu
  message={message}
  isOwn={message.sender_id === currentUser.id}
  currentUser={currentUser}
  conversations={conversations}
  enableRightClickMenu={true}
  onReply={handleReply}
  // ... other handlers
/>
```

### API Usage
```javascript
// Pin a message
await fetch(`/api/v1/chat/conversations/${conversationId}/messages/${messageId}/pin`, {
  method: 'POST',
  headers: { 'Authorization': `Bearer ${token}` }
});

// Get read receipts
const response = await fetch(`/api/v1/chat/conversations/${conversationId}/messages/${messageId}/read-receipts`);
const { read_receipts } = await response.json();

// Flag a message
await fetch(`/api/v1/chat/conversations/${conversationId}/messages/${messageId}/flag`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    reason: 'Inappropriate content',
    category: 'inappropriate'
  })
});
```

## Integration Notes

### With Existing Chat System
- Integrates seamlessly with existing E2EE chat
- Preserves all encryption and security features
- Compatible with existing message types
- Works with quantum-resistant encryption

### Database Schema
The implementation assumes the following database structure:
- `chat_messages` table with columns: `is_pinned`, `pinned_by`, `pinned_at`
- `message_bookmarks` table for user bookmarks
- `message_flags` table for content moderation
- `message_read_receipts` table for delivery tracking

### Frontend Dependencies
- React 18+ with TypeScript
- shadcn/ui component library
- Lucide React icons
- Sonner for toast notifications
- Radix UI primitives

## Customization

### Theming
The context menu respects the application theme:
- Light/dark mode compatible
- CSS custom properties for colors
- Responsive breakpoints
- Consistent with shadcn/ui design system

### Permissions
Customize permissions by modifying:
- `canEdit` logic in components
- Backend middleware and permission checks
- Role-based access control integration

### Actions
Add custom actions by:
1. Adding new handler props to `MessageContextMenuProps`
2. Adding menu items to the component
3. Creating corresponding backend endpoints
4. Adding keyboard shortcuts if needed

This comprehensive context menu system provides a modern, accessible, and feature-rich message interaction experience while maintaining security and performance standards.