import React, { useState } from 'react';
import { DocumentArrowDownIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { Message, Conversation } from '@/types/chat';

interface ExportMessagesProps {
  isOpen: boolean;
  onClose: () => void;
  conversation: Conversation;
  messages: Message[];
}

type ExportFormat = 'json' | 'txt' | 'csv' | 'html';

export default function ExportMessages({ isOpen, onClose, conversation, messages }: ExportMessagesProps) {
  const [format, setFormat] = useState<ExportFormat>('txt');
  const [dateRange, setDateRange] = useState<'all' | 'week' | 'month' | 'custom'>('all');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [includeMetadata, setIncludeMetadata] = useState(false);
  const [exporting, setExporting] = useState(false);

  const filterMessagesByDate = (messages: Message[]) => {
    if (dateRange === 'all') return messages;
    
    const now = new Date();
    let filterDate = new Date();
    
    switch (dateRange) {
      case 'week':
        filterDate = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
        break;
      case 'month':
        filterDate = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
        break;
      case 'custom':
        if (startDate && endDate) {
          return messages.filter(msg => {
            const msgDate = new Date(msg.created_at);
            return msgDate >= new Date(startDate) && msgDate <= new Date(endDate);
          });
        }
        return messages;
    }
    
    return messages.filter(msg => new Date(msg.created_at) >= filterDate);
  };

  const exportAsText = (messages: Message[]) => {
    const conversationName = conversation.name || `Chat with ${conversation.participants?.map(p => p.user?.name).join(', ')}`;
    let content = `Chat Export: ${conversationName}\n`;
    content += `Exported on: ${new Date().toLocaleString()}\n`;
    content += `Total messages: ${messages.length}\n`;
    content += '='.repeat(50) + '\n\n';
    
    messages.forEach(msg => {
      const date = new Date(msg.created_at).toLocaleString();
      const sender = msg.sender?.name || 'Unknown';
      const priority = msg.message_priority && msg.message_priority !== 'normal' ? ` [${msg.message_priority.toUpperCase()}]` : '';
      const isReply = msg.reply_to ? ` (Reply to: ${msg.reply_to.sender?.name})` : '';
      
      content += `[${date}] ${sender}${priority}${isReply}:\n`;
      
      if (msg.type === 'voice') {
        content += `🎵 Voice message (${msg.voice_duration_seconds}s)\n`;
        if (msg.voice_transcript) {
          content += `Transcript: "${msg.voice_transcript}"\n`;
        }
      } else {
        content += `${msg.content || '[Message could not be decrypted]'}\n`;
      }
      
      if (includeMetadata) {
        content += `  Status: ${msg.status}\n`;
        if (msg.is_edited) content += `  Edited: ${msg.edited_at}\n`;
        if (msg.scheduled_at) content += `  Scheduled: ${msg.scheduled_at}\n`;
      }
      
      content += '\n';
    });
    
    return content;
  };

  const exportAsJSON = (messages: Message[]) => {
    const exportData = {
      conversation: {
        id: conversation.id,
        name: conversation.name,
        type: conversation.type,
        participants: conversation.participants?.map(p => ({
          id: p.user_id,
          name: p.user?.name,
          email: p.user?.email
        }))
      },
      exportedAt: new Date().toISOString(),
      messageCount: messages.length,
      messages: messages.map(msg => ({
        id: msg.id,
        sender: {
          id: msg.sender_id,
          name: msg.sender?.name
        },
        type: msg.type,
        content: msg.content,
        voiceDurationSeconds: msg.voice_duration_seconds,
        voiceTranscript: msg.voice_transcript,
        priority: msg.message_priority || 'normal',
        status: msg.status,
        isEdited: msg.is_edited,
        editedAt: msg.edited_at,
        scheduledAt: msg.scheduled_at,
        createdAt: msg.created_at,
        replyTo: msg.reply_to ? {
          id: msg.reply_to.id,
          sender: msg.reply_to.sender?.name,
          content: msg.reply_to.content
        } : null
      }))
    };
    
    return JSON.stringify(exportData, null, 2);
  };

  const exportAsCSV = (messages: Message[]) => {
    const headers = [
      'Date',
      'Sender',
      'Type', 
      'Content',
      'Priority',
      'Status',
      'Is Reply',
      'Voice Duration',
      'Voice Transcript'
    ];
    
    let csv = headers.join(',') + '\n';
    
    messages.forEach(msg => {
      const row = [
        `"${new Date(msg.created_at).toLocaleString()}"`,
        `"${msg.sender?.name || 'Unknown'}"`,
        msg.type,
        `"${(msg.content || '[Message could not be decrypted]').replace(/"/g, '""')}"`,
        msg.message_priority,
        msg.status,
        msg.reply_to ? 'Yes' : 'No',
        msg.voice_duration_seconds || '',
        `"${(msg.voice_transcript || '').replace(/"/g, '""')}"`
      ];
      
      csv += row.join(',') + '\n';
    });
    
    return csv;
  };

  const exportAsHTML = (messages: Message[]) => {
    const conversationName = conversation.name || `Chat with ${conversation.participants?.map(p => p.user?.name).join(', ')}`;
    
    let html = `
<!DOCTYPE html>
<html>
<head>
    <title>Chat Export: ${conversationName}</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { border-bottom: 2px solid #eee; margin-bottom: 20px; padding-bottom: 20px; }
        .message { margin-bottom: 15px; padding: 10px; border-left: 3px solid #ddd; }
        .message.reply { border-left-color: #007bff; background: #f8f9fa; }
        .message.high-priority { border-left-color: #ffc107; }
        .message.urgent { border-left-color: #dc3545; }
        .message-header { font-weight: bold; color: #666; margin-bottom: 5px; }
        .message-content { line-height: 1.5; }
        .voice-message { background: #e3f2fd; padding: 5px; border-radius: 5px; }
        .metadata { font-size: 0.8em; color: #888; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Chat Export: ${conversationName}</h1>
        <p>Exported on: ${new Date().toLocaleString()}</p>
        <p>Total messages: ${messages.length}</p>
    </div>
    
    <div class="messages">
`;
    
    messages.forEach(msg => {
      const date = new Date(msg.created_at).toLocaleString();
      const sender = msg.sender?.name || 'Unknown';
      const priorityClass = msg.message_priority !== 'normal' ? ` ${msg.message_priority}-priority` : '';
      const replyClass = msg.reply_to ? ' reply' : '';
      
      html += `        <div class="message${priorityClass}${replyClass}">
            <div class="message-header">
                ${sender} - ${date}
                ${msg.message_priority && msg.message_priority !== 'normal' ? ` [${msg.message_priority.toUpperCase()}]` : ''}
                ${msg.reply_to ? ` (Reply to: ${msg.reply_to.sender?.name})` : ''}
            </div>
            <div class="message-content">`;
      
      if (msg.type === 'voice') {
        html += `                <div class="voice-message">
                    🎵 Voice message (${msg.voice_duration_seconds}s)
                    ${msg.voice_transcript ? `<br>Transcript: "${msg.voice_transcript}"` : ''}
                </div>`;
      } else {
        html += `                ${msg.content || '[Message could not be decrypted]'}`;
      }
      
      if (includeMetadata) {
        html += `                <div class="metadata">
                    Status: ${msg.status}
                    ${msg.is_edited ? ` | Edited: ${msg.edited_at}` : ''}
                    ${msg.scheduled_at ? ` | Scheduled: ${msg.scheduled_at}` : ''}
                </div>`;
      }
      
      html += `            </div>
        </div>
`;
    });
    
    html += `    </div>
</body>
</html>`;
    
    return html;
  };

  const handleExport = async () => {
    setExporting(true);
    
    try {
      const filteredMessages = filterMessagesByDate(messages);
      let content = '';
      let filename = '';
      let mimeType = '';
      
      const baseName = `chat_export_${conversation.id}_${new Date().toISOString().split('T')[0]}`;
      
      switch (format) {
        case 'txt':
          content = exportAsText(filteredMessages);
          filename = `${baseName}.txt`;
          mimeType = 'text/plain';
          break;
        case 'json':
          content = exportAsJSON(filteredMessages);
          filename = `${baseName}.json`;
          mimeType = 'application/json';
          break;
        case 'csv':
          content = exportAsCSV(filteredMessages);
          filename = `${baseName}.csv`;
          mimeType = 'text/csv';
          break;
        case 'html':
          content = exportAsHTML(filteredMessages);
          filename = `${baseName}.html`;
          mimeType = 'text/html';
          break;
      }
      
      // Create and download file
      const blob = new Blob([content], { type: mimeType });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      
      onClose();
    } catch (error) {
      console.error('Export failed:', error);
    } finally {
      setExporting(false);
    }
  };

  if (!isOpen) return null;

  const filteredMessages = filterMessagesByDate(messages);

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="p-4 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-medium text-gray-900 flex items-center">
              <DocumentArrowDownIcon className="h-5 w-5 mr-2" />
              Export Messages
            </h3>
            <button
              onClick={onClose}
              className="text-gray-400 hover:text-gray-600"
            >
              <XMarkIcon className="h-5 w-5" />
            </button>
          </div>
        </div>

        <div className="p-4 space-y-4">
          {/* Format Selection */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Export Format
            </label>
            <select
              value={format}
              onChange={(e) => setFormat(e.target.value as ExportFormat)}
              className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
            >
              <option value="txt">Plain Text (.txt)</option>
              <option value="json">JSON (.json)</option>
              <option value="csv">CSV (.csv)</option>
              <option value="html">HTML (.html)</option>
            </select>
          </div>

          {/* Date Range */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Date Range
            </label>
            <select
              value={dateRange}
              onChange={(e) => setDateRange(e.target.value as 'all' | 'week' | 'month' | 'custom')}
              className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
            >
              <option value="all">All messages</option>
              <option value="week">Last week</option>
              <option value="month">Last month</option>
              <option value="custom">Custom range</option>
            </select>
          </div>

          {/* Custom Date Range */}
          {dateRange === 'custom' && (
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-xs text-gray-600 mb-1">From</label>
                <input
                  type="date"
                  value={startDate}
                  onChange={(e) => setStartDate(e.target.value)}
                  className="w-full border border-gray-300 rounded-md px-2 py-1 text-sm"
                />
              </div>
              <div>
                <label className="block text-xs text-gray-600 mb-1">To</label>
                <input
                  type="date"
                  value={endDate}
                  onChange={(e) => setEndDate(e.target.value)}
                  className="w-full border border-gray-300 rounded-md px-2 py-1 text-sm"
                />
              </div>
            </div>
          )}

          {/* Options */}
          <div>
            <label className="flex items-center space-x-2">
              <input
                type="checkbox"
                checked={includeMetadata}
                onChange={(e) => setIncludeMetadata(e.target.checked)}
                className="rounded border-gray-300"
              />
              <span className="text-sm text-gray-700">Include metadata (status, timestamps, etc.)</span>
            </label>
          </div>

          {/* Preview */}
          <div className="bg-gray-50 p-3 rounded-lg">
            <div className="text-xs text-gray-600">
              <p><strong>Preview:</strong></p>
              <p>Messages to export: {filteredMessages.length}</p>
              <p>Format: {format.toUpperCase()}</p>
              <p>Include metadata: {includeMetadata ? 'Yes' : 'No'}</p>
            </div>
          </div>
        </div>

        <div className="p-4 border-t border-gray-200 flex justify-end space-x-3">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            onClick={handleExport}
            disabled={exporting || (dateRange === 'custom' && (!startDate || !endDate))}
            className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
          >
            {exporting ? (
              <>
                <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent mr-2" />
                Exporting...
              </>
            ) : (
              <>
                <DocumentArrowDownIcon className="h-4 w-4 mr-2" />
                Export
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}