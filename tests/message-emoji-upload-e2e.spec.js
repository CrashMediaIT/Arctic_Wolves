/**
 * Tests for Message Emoji, File Upload, and E2E Encryption
 * 
 * Verifies:
 * 1. Emoji picker UI exists with categories and grid
 * 2. File/image upload button and attachment handling
 * 3. E2E encryption is applied to message_body and attachment filenames
 * 4. Attachment rendering (images inline, files as download links)
 * 5. Backend properly validates and stores attachments
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.join(__dirname, '..');

test.describe('Message Emoji Picker UI', () => {
    test('views/messages.php should contain emoji picker elements', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        // Emoji picker button
        expect(content).toContain('toggleEmojiPicker()');
        expect(content).toContain('fa-face-smile');
        
        // Emoji picker panel structure
        expect(content).toContain('id="emojiPicker"');
        expect(content).toContain('emoji-picker-panel');
        expect(content).toContain('emoji-picker-grid');
        expect(content).toContain('emoji-picker-categories');
        expect(content).toContain('id="emojiSearch"');
    });

    test('should define emoji data with multiple categories', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        // Emoji categories defined in JS
        expect(content).toContain("'Smileys'");
        expect(content).toContain("'Gestures'");
        expect(content).toContain("'Hearts'");
        expect(content).toContain("'Sports'");
        expect(content).toContain("'Objects'");
        expect(content).toContain("'Nature'");
        expect(content).toContain("'Food'");
    });

    test('should have insertEmoji function that inserts into message input', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain('function insertEmoji(emoji)');
        expect(content).toContain("getElementById('msgInput')");
        expect(content).toContain('input.selectionStart');
    });

    test('should have emoji search functionality', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain('emojiSearch');
        expect(content).toContain('Search emoji...');
    });

    test('emoji picker should close on outside click', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain("closest('.emoji-picker-container')");
        expect(content).toContain("classList.remove('show')");
    });
});

test.describe('File/Image Upload UI', () => {
    test('views/messages.php should contain file input and attachment button', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        // Attachment button
        expect(content).toContain('fa-paperclip');
        expect(content).toContain('id="fileInput"');
        expect(content).toContain('Attach file');
        
        // File input with proper accept attribute
        expect(content).toContain("accept=\"image/*,.pdf,.doc,.docx,.txt,.xls,.xlsx,.csv\"");
        expect(content).toContain('multiple');
    });

    test('should have pending attachments preview area', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain('id="pendingAttachments"');
        expect(content).toContain('msg-pending-attachments');
        expect(content).toContain('renderPendingAttachments');
    });

    test('should have file selection handler with validation', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain('function handleFileSelect(input)');
        expect(content).toContain('Maximum 5 attachments per message');
        expect(content).toContain('25 * 1024 * 1024');
    });

    test('should have remove file functionality', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain('function removePendingFile(index)');
        expect(content).toContain('pendingFiles.splice');
    });

    test('should send files via FormData with attachments[]', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain("formData.append('attachments[]', file)");
    });

    test('sendMessage should allow sending only attachments without text', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        // Send button enabled when files are attached
        expect(content).toContain('pendingFiles.length > 0');
    });
});

test.describe('Attachment Rendering', () => {
    test('should have renderAttachments function for message bubbles', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain('function renderAttachments(attachments)');
        expect(content).toContain('msg-attachment-preview');
    });

    test('should display images inline with preview', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain('msg-attachment-img');
        expect(content).toContain("startsWith('image/')");
    });

    test('should display non-image files as download links', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain('msg-attachment-item');
        expect(content).toContain('function getFileIcon(mimeType)');
        expect(content).toContain('fa-file-pdf');
        expect(content).toContain('fa-file-word');
    });

    test('should call renderAttachments when rendering messages', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain("renderAttachments(msg.attachments || [])");
    });
});

test.describe('E2E Encryption Verification', () => {
    test('process_messages.php should encrypt message_body before storage', () => {
        const content = fs.readFileSync(path.join(ROOT, 'process_messages.php'), 'utf-8');
        
        // Encrypt message body
        expect(content).toContain("FieldEncryption::encrypt($message_body)");
        expect(content).toContain('$encrypted_body');
        expect(content).toContain('$stmt->execute([$conversation_id, $user_id, $to_user_id, $encrypted_body');
    });

    test('process_messages.php should decrypt messages when loading', () => {
        const content = fs.readFileSync(path.join(ROOT, 'process_messages.php'), 'utf-8');
        
        expect(content).toContain('FieldEncryption::decryptRows($messages, FieldEncryption::MESSAGE_ENCRYPTED_FIELDS)');
    });

    test('process_messages.php should encrypt attachment filenames', () => {
        const content = fs.readFileSync(path.join(ROOT, 'process_messages.php'), 'utf-8');
        
        expect(content).toContain("FieldEncryption::encrypt($safe_filename)");
        expect(content).toContain('$encrypted_filename');
    });

    test('process_messages.php should decrypt attachment filenames when loading', () => {
        const content = fs.readFileSync(path.join(ROOT, 'process_messages.php'), 'utf-8');
        
        expect(content).toContain("FieldEncryption::decrypt($att['filename'])");
    });

    test('MESSAGE_ENCRYPTED_FIELDS constant should exist in FieldEncryption', () => {
        const content = fs.readFileSync(path.join(ROOT, 'lib', 'encryption.php'), 'utf-8');
        
        expect(content).toContain('MESSAGE_ENCRYPTED_FIELDS');
        expect(content).toContain("'message_body'");
    });

    test('views/messages.php should display E2E encryption badge', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain('msg-e2e-badge');
        expect(content).toContain('Encrypted');
        expect(content).toContain('fa-lock');
    });

    test('each message bubble should show encryption lock icon', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        // Lock icon in message time footer
        expect(content).toContain('title="Encrypted"');
    });
});

test.describe('Backend Attachment Handling', () => {
    test('process_messages.php should include file_upload_validator and rustfs_storage', () => {
        const content = fs.readFileSync(path.join(ROOT, 'process_messages.php'), 'utf-8');
        
        expect(content).toContain("require_once __DIR__ . '/lib/file_upload_validator.php'");
        expect(content).toContain("require_once __DIR__ . '/lib/rustfs_storage.php'");
    });

    test('process_messages.php should validate file uploads', () => {
        const content = fs.readFileSync(path.join(ROOT, 'process_messages.php'), 'utf-8');
        
        expect(content).toContain('FileUploadValidator::validate');
        expect(content).toContain('FileUploadValidator::sanitizeFilename');
        expect(content).toContain('FileUploadValidator::generateUniqueFilename');
    });

    test('process_messages.php should enforce 5 attachment limit per message', () => {
        const content = fs.readFileSync(path.join(ROOT, 'process_messages.php'), 'utf-8');
        
        expect(content).toContain('$file_count > 5');
        expect(content).toContain('Maximum 5 attachments per message');
    });

    test('process_messages.php should enforce 25MB per file limit', () => {
        const content = fs.readFileSync(path.join(ROOT, 'process_messages.php'), 'utf-8');
        
        expect(content).toContain('25 * 1024 * 1024');
    });

    test('process_messages.php should store attachments in message_attachments table', () => {
        const content = fs.readFileSync(path.join(ROOT, 'process_messages.php'), 'utf-8');
        
        expect(content).toContain('INSERT INTO message_attachments');
        expect(content).toContain('message_id, filename, file_path, file_size, mime_type');
    });

    test('process_messages.php should load attachments for messages in a conversation', () => {
        const content = fs.readFileSync(path.join(ROOT, 'process_messages.php'), 'utf-8');
        
        expect(content).toContain('SELECT id, message_id, filename, file_path, file_size, mime_type');
        expect(content).toContain('FROM message_attachments');
        expect(content).toContain('$attachments_map');
    });

    test('process_messages.php should upload to RustFS with local fallback', () => {
        const content = fs.readFileSync(path.join(ROOT, 'process_messages.php'), 'utf-8');
        
        expect(content).toContain('uploadToRustFS');
        expect(content).toContain('getRustFSSettings');
        expect(content).toContain('isRustFSConfigured');
        expect(content).toContain("'Messages/attachments/'");
    });

    test('process_messages.php should accept allowed file extensions', () => {
        const content = fs.readFileSync(path.join(ROOT, 'process_messages.php'), 'utf-8');
        
        // Verify image types are accepted
        expect(content).toContain("'jpg'");
        expect(content).toContain("'png'");
        expect(content).toContain("'gif'");
        expect(content).toContain("'webp'");
        
        // Verify document types are accepted
        expect(content).toContain("'pdf'");
        expect(content).toContain("'doc'");
        expect(content).toContain("'docx'");
        expect(content).toContain("'txt'");
    });

    test('process_messages.php should allow sending with only attachments', () => {
        const content = fs.readFileSync(path.join(ROOT, 'process_messages.php'), 'utf-8');
        
        expect(content).toContain('$has_attachments');
        expect(content).toContain("empty($message_body) && !$has_attachments");
    });

    test('process_messages.php should include attachment_count in audit log', () => {
        const content = fs.readFileSync(path.join(ROOT, 'process_messages.php'), 'utf-8');
        
        expect(content).toContain("'attachment_count'");
    });
});

test.describe('Database Schema Support', () => {
    test('database_schema.sql should have message_attachments table', () => {
        const content = fs.readFileSync(path.join(ROOT, 'database_schema.sql'), 'utf-8');
        
        expect(content).toContain('CREATE TABLE IF NOT EXISTS `message_attachments`');
        expect(content).toContain('`message_id` INT NOT NULL');
        expect(content).toContain('`filename` VARCHAR(255) NOT NULL');
        expect(content).toContain('`file_path` VARCHAR(500) NOT NULL');
        expect(content).toContain('`file_size` BIGINT');
        expect(content).toContain('`mime_type` VARCHAR(100)');
    });

    test('messages table should use utf8mb4 for emoji support', () => {
        const content = fs.readFileSync(path.join(ROOT, 'database_schema.sql'), 'utf-8');
        
        // The messages table definition should end with utf8mb4 charset
        const messagesTable = content.substring(
            content.indexOf('CREATE TABLE IF NOT EXISTS `messages`'),
            content.indexOf(';', content.indexOf('CREATE TABLE IF NOT EXISTS `messages`')) + 1
        );
        expect(messagesTable).toContain('utf8mb4');
    });
});

test.describe('Widget Size Toggle', () => {
    test('should have size toggle buttons in widget chat header', () => {
        const content = fs.readFileSync(path.join(ROOT, 'dashboard.php'), 'utf-8');
        
        expect(content).toContain('messenger-size-toggle');
        expect(content).toContain('messenger-size-btn');
        expect(content).toContain('data-size="default"');
        expect(content).toContain('data-size="half"');
        expect(content).toContain('data-size="full"');
    });

    test('should have setWidgetSize function', () => {
        const content = fs.readFileSync(path.join(ROOT, 'dashboard.php'), 'utf-8');
        
        expect(content).toContain('function setWidgetSize(size)');
        expect(content).toContain("classList.add('widget-size-' + size)");
        expect(content).toContain("'widget-size-default', 'widget-size-half', 'widget-size-full'");
    });

    test('default widget size should hide emoji/file upload toolbar', () => {
        const content = fs.readFileSync(path.join(ROOT, 'dashboard.php'), 'utf-8');
        
        expect(content).toContain('.messenger-panel.widget-size-default .messenger-input-toolbar');
        expect(content).toContain('display: none');
    });

    test('half and full widget sizes should have distinct CSS rules', () => {
        const content = fs.readFileSync(path.join(ROOT, 'dashboard.php'), 'utf-8');
        
        expect(content).toContain('.messenger-panel.widget-size-half');
        expect(content).toContain('.messenger-panel.widget-size-full');
    });

    test('size toggle buttons should have descriptive titles', () => {
        const content = fs.readFileSync(path.join(ROOT, 'dashboard.php'), 'utf-8');
        
        expect(content).toContain('title="Compact view"');
        expect(content).toContain('title="Half size"');
        expect(content).toContain('title="Full size"');
    });

    test('messages view should NOT have size toggle', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).not.toContain('msg-size-toggle');
        expect(content).not.toContain('setConversationSize');
        expect(content).not.toContain('size-default');
        expect(content).not.toContain('size-half');
        expect(content).not.toContain('size-full');
    });

    test('messages view should always show toolbar in full screen mode', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain('msg-input-toolbar');
        // Should not have any rule hiding the toolbar
        expect(content).not.toContain('.messages-container.size-default .msg-input-toolbar');
    });
});

test.describe('Image Paste Support', () => {
    test('should have paste event listener on message input in messages view', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain("addEventListener('paste'");
        expect(content).toContain('clipboardData');
        expect(content).toContain("indexOf('image')");
    });

    test('should create File object from pasted image blob', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain('getAsFile()');
        expect(content).toContain('new File([blob]');
        expect(content).toContain('pasted-image-');
    });

    test('should enforce attachment limits for pasted images', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).toContain('pendingFiles.length >= 5');
        expect(content).toContain('Cannot paste image: file size exceeds 25MB limit');
    });

    test('should not have paste hint text in messages view (icon-only buttons)', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        expect(content).not.toContain('msg-paste-hint');
        expect(content).not.toContain('Paste images');
    });

    test('widget should have paste event listener', () => {
        const content = fs.readFileSync(path.join(ROOT, 'dashboard.php'), 'utf-8');
        
        expect(content).toContain("addEventListener('paste'");
        expect(content).toContain('clipboardData');
    });

    test('widget should not have paste hint text (icon-only buttons)', () => {
        const content = fs.readFileSync(path.join(ROOT, 'dashboard.php'), 'utf-8');
        
        expect(content).not.toContain('messenger-paste-hint');
        expect(content).not.toContain('Paste images');
    });
});

test.describe('Emoji Picker Sizing Fix', () => {
    test('emoji picker panel should be wider than 320px', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        // Panel should be at least 360px wide for proper emoji display
        expect(content).toContain('width: 360px');
    });

    test('emoji picker should have larger max-height', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        // Panel max-height should be at least 400px
        expect(content).toContain('max-height: 420px');
    });

    test('emoji grid should have adequate height for visible emojis', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        // Grid max-height should be at least 280px for comfortable scrolling
        expect(content).toContain('max-height: 300px');
    });

    test('emoji categories should use space-between layout', () => {
        const content = fs.readFileSync(path.join(ROOT, 'views', 'messages.php'), 'utf-8');
        
        // Categories should be spread across the width to avoid scrolling
        const catSection = content.substring(
            content.indexOf('.emoji-picker-categories'),
            content.indexOf('}', content.indexOf('.emoji-picker-categories')) + 1
        );
        expect(catSection).toContain('justify-content: space-between');
    });
});
