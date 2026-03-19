import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';

export default function tiptapEditor(content = '', uploadUrl = '') {
    return {
        editor: null,
        content: content,
        uploadUrl: uploadUrl,
        uploading: false,

        init() {
            const self = this;

            this.editor = new Editor({
                element: this.$refs.editor,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3, 4] },
                    }),
                    Image.configure({
                        HTMLAttributes: {
                            class: 'rounded-lg max-w-full',
                        },
                    }),
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
                    // Handle pasted/dropped images
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
            if (this.uploadUrl) {
                // Use file picker for upload
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
                // Fallback: URL prompt
                const url = window.prompt('Image URL');
                if (url) {
                    this.editor?.chain().focus().setImage({ src: url }).run();
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
                    this.editor?.chain().focus().setImage({
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
            this.editor?.chain().focus().undo().run();
        },

        redo() {
            this.editor?.chain().focus().redo().run();
        },
    };
}
