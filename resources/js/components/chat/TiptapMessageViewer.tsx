import React from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Mention from '@tiptap/extension-mention';

interface TiptapMessageViewerProps {
  content: string;
  className?: string;
}

export default function TiptapMessageViewer({ content, className = '' }: TiptapMessageViewerProps) {
  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading: false,
        codeBlock: false,
        horizontalRule: false,
        blockquote: false,
      }),
      Mention.configure({
        HTMLAttributes: {
          class: 'mention bg-blue-100 text-blue-800 px-1 rounded font-medium',
        },
        renderHTML({ options, node }) {
          return [
            'span',
            {
              'class': 'mention bg-blue-100 text-blue-800 px-1 rounded font-medium',
              'data-id': node.attrs.id,
              'data-label': node.attrs.label,
            },
            `@${node.attrs.label}`,
          ];
        },
      }),
    ],
    content: content || '',
    editable: false,
    editorProps: {
      attributes: {
        class: `prose prose-sm max-w-none break-words ${className}`,
      },
    },
  });

  // Update content when prop changes
  React.useEffect(() => {
    if (editor && content !== editor.getHTML()) {
      editor.commands.setContent(content || '');
    }
  }, [editor, content]);

  if (!editor) return <div>Loading...</div>;

  return <EditorContent editor={editor} />;
}