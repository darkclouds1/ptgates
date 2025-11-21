(function ($) {
    'use strict';

    const MyNote = {
        init: function () {
            this.container = $('#ptg-mynote-app');
            if (!this.container.length) return;

            this.config = window.ptgMyNote || {};
            this.filterType = 'all';
            this.searchQuery = '';

            this.bindEvents();
            this.loadNotes();
        },

        bindEvents: function () {
            const self = this;

            // Tab switching
            this.container.on('click', '.ptg-tab-btn', function () {
                $('.ptg-tab-btn').removeClass('active');
                $(this).addClass('active');
                self.filterType = $(this).data('type');
                self.loadNotes();
            });

            // Search
            this.container.on('input', '#ptg-note-search', function () {
                self.searchQuery = $(this).val();
                self.loadNotes(); // Should implement debounce in real world
            });

            // Delete
            this.container.on('click', '.ptg-note-delete', function () {
                if (confirm('정말 삭제하시겠습니까?')) {
                    const id = $(this).data('id');
                    self.deleteNote(id);
                }
            });
        },

        loadNotes: function () {
            const self = this;
            const listContainer = this.container.find('.ptg-note-list');

            listContainer.html('<div class="ptg-loading">로딩 중...</div>');

            $.ajax({
                url: self.config.restUrl + 'notes',
                method: 'GET',
                data: {
                    type: self.filterType,
                    search: self.searchQuery
                },
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
                },
                success: function (response) {
                    self.renderList(response);
                },
                error: function (err) {
                    listContainer.html('<div class="ptg-error">노트를 불러오는데 실패했습니다.</div>');
                    console.error(err);
                }
            });
        },

        renderList: function (notes) {
            const listContainer = this.container.find('.ptg-note-list');

            if (!notes || notes.length === 0) {
                listContainer.html('<div class="ptg-empty">저장된 노트가 없습니다.</div>');
                return;
            }

            let html = '<ul>';
            notes.forEach(function (note) {
                html += '<li class="ptg-note-item">';
                html += '<div class="ptg-note-header">';
                html += '<span class="ptg-badge">' + note.source_type + '</span>';
                html += '<span class="ptg-date">' + note.created_at + '</span>';
                html += '</div>';
                html += '<div class="ptg-note-content">' + note.content + '</div>';
                html += '<div class="ptg-note-actions">';
                html += '<button class="ptg-btn ptg-btn-text ptg-note-delete" data-id="' + note.note_id + '">삭제</button>';
                html += '</div>';
                html += '</li>';
            });
            html += '</ul>';

            listContainer.html(html);
        },

        deleteNote: function (id) {
            const self = this;
            $.ajax({
                url: self.config.restUrl + 'notes/' + id,
                method: 'DELETE',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
                },
                success: function () {
                    self.loadNotes();
                },
                error: function () {
                    alert('삭제 실패');
                }
            });
        }
    };

    $(document).ready(function () {
        MyNote.init();
    });

})(jQuery);
