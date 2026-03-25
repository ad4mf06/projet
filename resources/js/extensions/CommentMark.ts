import { Mark, mergeAttributes } from '@tiptap/core';

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        comment: {
            /** Applique la marque de correction sur la sélection courante. */
            setComment: (commentId: string) => ReturnType;
            /** Retire la marque de correction dont l'ID correspond. */
            unsetComment: (commentId: string) => ReturnType;
        };
    }
}

/**
 * Mark TipTap représentant une correction inline de l'enseignant.
 * Stocke un UUID (commentId) comme attribut HTML data-comment-id.
 */
export const CommentMark = Mark.create({
    name: 'comment',

    // Les marques de correction peuvent se superposer à d'autres marques
    inclusive: false,
    excludes: '',

    addAttributes() {
        return {
            commentId: {
                default: null,
                parseHTML: (el: HTMLElement) => el.getAttribute('data-comment-id'),
                renderHTML: (attrs: Record<string, string | null>) => ({
                    'data-comment-id': attrs.commentId,
                }),
            },
            isActive: {
                default: false,
                // Pas rendu en HTML — géré en JS uniquement pour le highlight au survol
                rendered: false,
            },
        };
    },

    parseHTML() {
        return [{ tag: 'mark[data-comment-id]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'mark',
            mergeAttributes(HTMLAttributes, { class: 'comment-mark' }),
            0,
        ];
    },

    addCommands() {
        return {
            setComment:
                (commentId: string) =>
                ({ commands }) =>
                    commands.setMark(this.name, { commentId }),

            unsetComment:
                (commentId: string) =>
                ({ tr, state, dispatch }) => {
                    const { doc, schema } = state;
                    const markType = schema.marks.comment;
                    if (!markType || !dispatch) return false;

                    let modified = false;
                    doc.descendants((node, pos) => {
                        const mark = node.marks.find(
                            (m) =>
                                m.type === markType &&
                                m.attrs.commentId === commentId,
                        );
                        if (mark) {
                            tr.removeMark(pos, pos + node.nodeSize, mark);
                            modified = true;
                        }
                    });

                    if (modified) {
                        dispatch(tr);
                    }

                    return modified;
                },
        };
    },
});
