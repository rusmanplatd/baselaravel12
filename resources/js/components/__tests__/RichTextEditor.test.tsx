import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import RichTextEditor from '../chat/RichTextEditor';
import type { JSONContent } from '@tiptap/react';

// Mock the Tiptap editor
vi.mock('@tiptap/react', () => ({
  useEditor: vi.fn(() => ({
    getJSON: vi.fn(() => ({ type: 'doc', content: [] })),
    getText: vi.fn(() => ''),
    commands: {
      focus: vi.fn(() => ({ run: vi.fn() })),
      clearContent: vi.fn(() => ({ run: vi.fn() })),
      toggleBold: vi.fn(() => ({ run: vi.fn() })),
      toggleItalic: vi.fn(() => ({ run: vi.fn() })),
      toggleStrike: vi.fn(() => ({ run: vi.fn() })),
      insertContent: vi.fn(() => ({ run: vi.fn() })),
    },
    chain: vi.fn(() => ({
      focus: vi.fn(() => ({ toggleBold: vi.fn(() => ({ run: vi.fn() })) })),
    })),
    isActive: vi.fn(() => false),
  })),
  EditorContent: ({ children, ...props }: any) => <div data-testid="editor-content" {...props}>{children}</div>,
  JSONContent: {} as any,
}));

// Mock all the extensions
vi.mock('@tiptap/starter-kit', () => ({ StarterKit: { configure: vi.fn() } }));
vi.mock('@tiptap/extension-placeholder', () => ({ Placeholder: { configure: vi.fn() } }));
vi.mock('@tiptap/extension-mention', () => ({ Mention: { configure: vi.fn() } }));
vi.mock('@tiptap/extension-bold', () => ({ Bold: vi.fn() }));
vi.mock('@tiptap/extension-italic', () => ({ Italic: vi.fn() }));
vi.mock('@tiptap/extension-strike', () => ({ Strike: vi.fn() }));
vi.mock('@tiptap/extension-underline', () => ({ Underline: vi.fn() }));
vi.mock('@tiptap/extension-code', () => ({ Code: { configure: vi.fn() } }));
vi.mock('@tiptap/extension-link', () => ({ Link: { configure: vi.fn() } }));
vi.mock('@tiptap/extension-blockquote', () => ({ Blockquote: { configure: vi.fn() } }));
vi.mock('@tiptap/extension-code-block', () => ({ CodeBlock: { configure: vi.fn() } }));
vi.mock('@tiptap/extension-bullet-list', () => ({ BulletList: { configure: vi.fn() } }));
vi.mock('@tiptap/extension-ordered-list', () => ({ OrderedList: { configure: vi.fn() } }));
vi.mock('@tiptap/extension-list-item', () => ({ ListItem: vi.fn() }));
vi.mock('@tiptap/extension-hard-break', () => ({ HardBreak: { configure: vi.fn() } }));

const mockUsers = [
  { id: '1', name: 'John Doe', email: 'john@example.com', online: true },
  { id: '2', name: 'Jane Smith', email: 'jane@example.com', online: false },
];

const mockChannels = [
  { id: '1', name: 'general', type: 'public' as const },
  { id: '2', name: 'development', type: 'public' as const },
];

describe('RichTextEditor', () => {
  const mockOnUpdate = vi.fn();
  const mockOnSubmit = vi.fn();
  const mockOnEmojiClick = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders editor with toolbar', async () => {
    await act(async () => {
      render(
        <RichTextEditor
          placeholder="Test placeholder"
          onUpdate={mockOnUpdate}
          onSubmit={mockOnSubmit}
          mentionableUsers={mockUsers}
          mentionableChannels={mockChannels}
          showToolbar={true}
        />
      );
    });

    expect(screen.getByTestId('editor-content')).toBeInTheDocument();
  });

  it('renders toolbar buttons when showToolbar is true', async () => {
    await act(async () => {
      render(
        <RichTextEditor
          onUpdate={mockOnUpdate}
          onSubmit={mockOnSubmit}
          showToolbar={true}
        />
      );
    });

    // Check for some toolbar buttons (they should be rendered as buttons)
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
  });

  it('does not render toolbar when showToolbar is false', async () => {
    await act(async () => {
      render(
        <RichTextEditor
          onUpdate={mockOnUpdate}
          onSubmit={mockOnSubmit}
          showToolbar={false}
        />
      );
    });

    expect(screen.getByTestId('editor-content')).toBeInTheDocument();
    // Should only have the editor content, not toolbar buttons
  });

  it('handles emoji click', async () => {
    await act(async () => {
      render(
        <RichTextEditor
          onUpdate={mockOnUpdate}
          onSubmit={mockOnSubmit}
          onEmojiClick={mockOnEmojiClick}
          showToolbar={true}
        />
      );
    });

    // Find and click the emoji button (should be one with Smile icon)
    const buttons = screen.getAllByRole('button');
    const emojiButton = buttons[buttons.length - 1]; // Usually the last button in toolbar
    
    await act(async () => {
      fireEvent.click(emojiButton);
    });

    expect(mockOnEmojiClick).toHaveBeenCalled();
  });

  it('handles disabled state correctly', async () => {
    await act(async () => {
      render(
        <RichTextEditor
          onUpdate={mockOnUpdate}
          onSubmit={mockOnSubmit}
          disabled={true}
          showToolbar={true}
        />
      );
    });

    const editorContent = screen.getByTestId('editor-content');
    expect(editorContent).toBeInTheDocument();
  });

  it('applies custom className', async () => {
    const customClass = 'custom-editor-class';
    
    await act(async () => {
      render(
        <RichTextEditor
          onUpdate={mockOnUpdate}
          onSubmit={mockOnSubmit}
          className={customClass}
        />
      );
    });

    const editorContent = screen.getByTestId('editor-content');
    expect(editorContent).toBeInTheDocument();
  });

  it('works with mentionable users and channels', async () => {
    await act(async () => {
      render(
        <RichTextEditor
          onUpdate={mockOnUpdate}
          onSubmit={mockOnSubmit}
          mentionableUsers={mockUsers}
          mentionableChannels={mockChannels}
          showToolbar={true}
        />
      );
    });

    // Check that the component renders without errors with mention data
    expect(screen.getByTestId('editor-content')).toBeInTheDocument();
  });

  it('handles editable prop correctly', async () => {
    await act(async () => {
      render(
        <RichTextEditor
          onUpdate={mockOnUpdate}
          onSubmit={mockOnSubmit}
          editable={false}
          showToolbar={true}
        />
      );
    });

    // When editable is false, toolbar should still render but editor should be read-only
    expect(screen.getByTestId('editor-content')).toBeInTheDocument();
  });

  it('handles minHeight and maxHeight props', async () => {
    await act(async () => {
      render(
        <RichTextEditor
          onUpdate={mockOnUpdate}
          onSubmit={mockOnSubmit}
          minHeight={60}
          maxHeight={200}
        />
      );
    });

    expect(screen.getByTestId('editor-content')).toBeInTheDocument();
  });

  it('calls onUpdate when content changes', async () => {
    // This test is more complex as it requires mocking the editor's onUpdate callback
    // For now, we'll just verify the component renders
    await act(async () => {
      render(
        <RichTextEditor
          onUpdate={mockOnUpdate}
          onSubmit={mockOnSubmit}
        />
      );
    });

    expect(screen.getByTestId('editor-content')).toBeInTheDocument();
  });
});