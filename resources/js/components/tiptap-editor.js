import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';

// Store editor instances outside Alpine's reactive system
// to prevent proxy conflicts with ProseMirror transactions
const editors = new WeakMap();

export default function tiptapEditor(content = '', uploadUrl = '') {
    return {
        content: content,
        uploadUrl: uploadUrl,
        uploading: false,

        init() {
            const self = this;
            const editorElement = this.$refs.editor;

            const editor = new Editor({
                element: editorElement,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3, 4] },
                        link: {
                            openOnClick: false,
                            HTMLAttributes: { rel: 'noopener noreferrer' },
                        },
                    }),
                    Image.configure({
                        HTMLAttributes: {
                            class: 'rounded-lg max-w-full',
                        },
                    }),
                ],
                content: this.content,
                editorProps: {
                    attributes: {
                        class: 'prose max-w-none min-h-[200px] p-4 focus:outline-none',
                    },
                    handleDrop(view, event) {
                        const files = event.dataTransfer?.files;
                        if (files && files.length > 0) {
                            const imageFile = Array.from(files).find(f => f.type.startsWith('image/'));
                            if (imageFile) {
                                event.preventDefault();
                                self.uploadFile(imageFile);
                                return true;
                            }
                        }
                        return false;
                    },
                    handlePaste(view, event) {
                        const items = event.clipboardData?.items;
                        if (items) {
                            for (const item of items) {
                                if (item.type.startsWith('image/')) {
                                    event.preventDefault();
                                    const file = item.getAsFile();
                                    if (file) {
                                        self.uploadFile(file);
                                    }
                                    return true;
                                }
                            }
                        }
                        return false;
                    },
                },
                onUpdate: ({ editor: ed }) => {
                    this.content = ed.getHTML();
                },
            });

            // Store editor outside Alpine's reactive proxy
            editors.set(editorElement, editor);
        },

        destroy() {
            const editor = editors.get(this.$refs.editor);
            if (editor) {
                editor.destroy();
                editors.delete(this.$refs.editor);
            }
        },

        /** Get the raw (non-proxied) editor instance */
        _editor() {
            return editors.get(this.$refs.editor);
        },

        isActive(type, attrs = {}) {
            return this._editor()?.isActive(type, attrs) ?? false;
        },

        toggleBold() {
            this._editor()?.chain().focus().toggleBold().run();
        },

        toggleItalic() {
            this._editor()?.chain().focus().toggleItalic().run();
        },

        toggleStrike() {
            this._editor()?.chain().focus().toggleStrike().run();
        },

        toggleHeading(level) {
            this._editor()?.chain().focus().toggleHeading({ level }).run();
        },

        toggleBulletList() {
            this._editor()?.chain().focus().toggleBulletList().run();
        },

        toggleOrderedList() {
            this._editor()?.chain().focus().toggleOrderedList().run();
        },

        toggleBlockquote() {
            this._editor()?.chain().focus().toggleBlockquote().run();
        },

        toggleCode() {
            this._editor()?.chain().focus().toggleCode().run();
        },

        toggleCodeBlock() {
            this._editor()?.chain().focus().toggleCodeBlock().run();
        },

        setHorizontalRule() {
            this._editor()?.chain().focus().setHorizontalRule().run();
        },

        setLink() {
            const url = window.prompt('URL');
            if (url) {
                this._editor()?.chain().focus().setLink({ href: url }).run();
            }
        },

        unsetLink() {
            this._editor()?.chain().focus().unsetLink().run();
        },

        addImage() {
            if (this.uploadUrl) {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = 'image/*';
                input.onchange = () => {
                    const file = input.files?.[0];
                    if (file) {
                        this.uploadFile(file);
                    }
                };
                input.click();
            } else {
                const url = window.prompt('Image URL');
                if (url) {
                    this._editor()?.chain().focus().setImage({ src: url }).run();
                }
            }
        },

        async uploadFile(file) {
            if (!this.uploadUrl || this.uploading) {
                return;
            }

            this.uploading = true;

            const formData = new FormData();
            formData.append('file', file);

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch(this.uploadUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token || '',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                if (!response.ok) {
                    throw new Error(`Upload failed: ${response.status}`);
                }

                const data = await response.json();

                if (data.url) {
                    this._editor()?.chain().focus().setImage({
                        src: data.url,
                        alt: data.alt || file.name,
                    }).run();
                }
            } catch (error) {
                console.error('Image upload failed:', error);
                alert('Image upload failed. Please try again.');
            } finally {
                this.uploading = false;
            }
        },

        undo() {
            this._editor()?.chain().focus().undo().run();
        },

        redo() {
            this._editor()?.chain().focus().redo().run();
        },
    };
}
