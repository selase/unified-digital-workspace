import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';

export default function tiptapEditor(content = '') {
    return {
        editor: null,
        content: content,

        init() {
            this.editor = new Editor({
                element: this.$refs.editor,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3, 4] },
                    }),
                    Image,
                    Link.configure({
                        openOnClick: false,
                        HTMLAttributes: { rel: 'noopener noreferrer' },
                    }),
                ],
                content: this.content,
                editorProps: {
                    attributes: {
                        class: 'prose max-w-none min-h-[200px] p-4 focus:outline-none',
                    },
                },
                onUpdate: ({ editor }) => {
                    this.content = editor.getHTML();
                },
            });
        },

        destroy() {
            if (this.editor) {
                this.editor.destroy();
            }
        },

        isActive(type, attrs = {}) {
            return this.editor?.isActive(type, attrs) ?? false;
        },

        toggleBold() {
            this.editor?.chain().focus().toggleBold().run();
        },

        toggleItalic() {
            this.editor?.chain().focus().toggleItalic().run();
        },

        toggleStrike() {
            this.editor?.chain().focus().toggleStrike().run();
        },

        toggleHeading(level) {
            this.editor?.chain().focus().toggleHeading({ level }).run();
        },

        toggleBulletList() {
            this.editor?.chain().focus().toggleBulletList().run();
        },

        toggleOrderedList() {
            this.editor?.chain().focus().toggleOrderedList().run();
        },

        toggleBlockquote() {
            this.editor?.chain().focus().toggleBlockquote().run();
        },

        toggleCode() {
            this.editor?.chain().focus().toggleCode().run();
        },

        toggleCodeBlock() {
            this.editor?.chain().focus().toggleCodeBlock().run();
        },

        setHorizontalRule() {
            this.editor?.chain().focus().setHorizontalRule().run();
        },

        setLink() {
            const url = window.prompt('URL');
            if (url) {
                this.editor?.chain().focus().setLink({ href: url }).run();
            }
        },

        unsetLink() {
            this.editor?.chain().focus().unsetLink().run();
        },

        addImage() {
            const url = window.prompt('Image URL');
            if (url) {
                this.editor?.chain().focus().setImage({ src: url }).run();
            }
        },

        undo() {
            this.editor?.chain().focus().undo().run();
        },

        redo() {
            this.editor?.chain().focus().redo().run();
        },
    };
}
