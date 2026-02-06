/**
 * Arctic Wolves - Typeahead / Autocomplete Component
 * Replaces <select> dropdowns with a search-as-you-type input.
 *
 * Usage:
 *   new ArcticTypeahead({
 *       container: '#my-container',      // CSS selector or element
 *       name: 'coach_id',                // hidden input name (for form submission)
 *       placeholder: 'Search coaches…',
 *       searchUrl: 'ajax_search_users.php',
 *       roles: 'coach,admin',            // optional role filter
 *       multiple: false,                 // single or multi-select
 *       onSelect: function(item) {},     // callback when item selected
 *       onChange: function(ids) {},       // callback when selection changes
 *       preSelected: [{id:1, name:'John'}], // optional pre-selected items
 *       required: false                  // whether input is required
 *   });
 */
(function(window) {
    'use strict';

    function ArcticTypeahead(options) {
        this.options = Object.assign({
            container: null,
            name: 'user_id',
            placeholder: 'Start typing a name…',
            searchUrl: 'ajax_search_users.php',
            roles: '',
            multiple: false,
            onSelect: null,
            onChange: null,
            preSelected: [],
            required: false,
            minChars: 1,
            debounceMs: 250,
            limit: 15,
            navigateOnSelect: null   // e.g. '?page=coach_goals&athlete_id='
        }, options);

        this.selected = [];
        this.debounceTimer = null;
        this.highlightedIndex = -1;
        this.results = [];
        this.isOpen = false;

        this._init();
    }

    ArcticTypeahead.prototype._init = function() {
        var container = typeof this.options.container === 'string'
            ? document.querySelector(this.options.container)
            : this.options.container;

        if (!container) {
            console.error('ArcticTypeahead: container not found', this.options.container);
            return;
        }

        this.container = container;
        container.classList.add('arctic-typeahead');

        // Build DOM
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'at-wrapper';

        // Tags area (for multi-select)
        this.tagsArea = document.createElement('div');
        this.tagsArea.className = 'at-tags';

        // Search input
        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.className = 'at-input';
        this.input.placeholder = this.options.placeholder;
        this.input.autocomplete = 'off';
        this.input.setAttribute('data-lpignore', 'true');

        // Dropdown
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'at-dropdown';
        this.dropdown.style.display = 'none';

        // Hidden inputs container
        this.hiddenContainer = document.createElement('div');
        this.hiddenContainer.className = 'at-hidden-inputs';

        // Assemble
        this.wrapper.appendChild(this.tagsArea);
        this.tagsArea.appendChild(this.input);
        container.appendChild(this.wrapper);
        container.appendChild(this.dropdown);
        container.appendChild(this.hiddenContainer);

        // Bind events
        this._bindEvents();

        // Pre-select items
        if (this.options.preSelected && this.options.preSelected.length) {
            var self = this;
            this.options.preSelected.forEach(function(item) {
                self._addSelection(item);
            });
        }

        // Update placeholder based on single/multi
        this._updatePlaceholder();
    };

    ArcticTypeahead.prototype._bindEvents = function() {
        var self = this;

        this.input.addEventListener('input', function() {
            self._onInput();
        });

        this.input.addEventListener('keydown', function(e) {
            self._onKeydown(e);
        });

        this.input.addEventListener('focus', function() {
            if (self.input.value.length >= self.options.minChars) {
                self._search(self.input.value);
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!self.container.contains(e.target)) {
                self._closeDropdown();
            }
        });

        // Handle backspace to remove last tag
        this.input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && self.input.value === '' && self.selected.length > 0 && self.options.multiple) {
                self._removeSelection(self.selected[self.selected.length - 1].id);
            }
        });
    };

    ArcticTypeahead.prototype._onInput = function() {
        var self = this;
        var query = this.input.value.trim();

        clearTimeout(this.debounceTimer);

        if (query.length < this.options.minChars) {
            this._closeDropdown();
            return;
        }

        this.debounceTimer = setTimeout(function() {
            self._search(query);
        }, this.options.debounceMs);
    };

    ArcticTypeahead.prototype._search = function(query) {
        var self = this;
        var url = this.options.searchUrl + '?q=' + encodeURIComponent(query)
            + '&limit=' + this.options.limit;

        if (this.options.roles) {
            url += '&roles=' + encodeURIComponent(this.options.roles);
        }

        fetch(url)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    // Filter out already selected items
                    var selectedIds = self.selected.map(function(s) { return s.id; });
                    self.results = data.results.filter(function(r) {
                        return selectedIds.indexOf(r.id) === -1;
                    });
                    self._renderDropdown();
                }
            })
            .catch(function(err) {
                console.error('ArcticTypeahead search error:', err);
            });
    };

    ArcticTypeahead.prototype._renderDropdown = function() {
        var self = this;
        this.dropdown.innerHTML = '';
        this.highlightedIndex = -1;

        if (this.results.length === 0) {
            var noResults = document.createElement('div');
            noResults.className = 'at-no-results';
            noResults.textContent = 'No matches found';
            this.dropdown.appendChild(noResults);
            this.dropdown.style.display = 'block';
            this.isOpen = true;
            return;
        }

        this.results.forEach(function(item, index) {
            var row = document.createElement('div');
            row.className = 'at-result';
            row.setAttribute('data-index', index);

            var nameSpan = document.createElement('span');
            nameSpan.className = 'at-result-name';
            nameSpan.textContent = item.name;

            var roleSpan = document.createElement('span');
            roleSpan.className = 'at-result-role';
            roleSpan.textContent = item.role;

            row.appendChild(nameSpan);
            row.appendChild(roleSpan);

            row.addEventListener('click', function(e) {
                e.stopPropagation();
                self._selectItem(item);
            });

            row.addEventListener('mouseenter', function() {
                self.highlightedIndex = index;
                self._updateHighlight();
            });

            self.dropdown.appendChild(row);
        });

        this.dropdown.style.display = 'block';
        this.isOpen = true;
    };

    ArcticTypeahead.prototype._onKeydown = function(e) {
        if (!this.isOpen) return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.highlightedIndex = Math.min(this.highlightedIndex + 1, this.results.length - 1);
                this._updateHighlight();
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.highlightedIndex = Math.max(this.highlightedIndex - 1, 0);
                this._updateHighlight();
                break;
            case 'Enter':
                e.preventDefault();
                if (this.highlightedIndex >= 0 && this.highlightedIndex < this.results.length) {
                    this._selectItem(this.results[this.highlightedIndex]);
                }
                break;
            case 'Escape':
                this._closeDropdown();
                break;
        }
    };

    ArcticTypeahead.prototype._updateHighlight = function() {
        var items = this.dropdown.querySelectorAll('.at-result');
        items.forEach(function(item, i) {
            item.classList.toggle('at-highlighted', i === this.highlightedIndex);
        }.bind(this));

        // Scroll into view
        if (items[this.highlightedIndex]) {
            items[this.highlightedIndex].scrollIntoView({ block: 'nearest' });
        }
    };

    ArcticTypeahead.prototype._selectItem = function(item) {
        // For navigation-based selects
        if (this.options.navigateOnSelect) {
            window.location.href = this.options.navigateOnSelect + item.id;
            return;
        }

        if (!this.options.multiple) {
            // Single-select: replace current selection
            this.selected = [];
            this.hiddenContainer.innerHTML = '';
            this.tagsArea.querySelectorAll('.at-tag').forEach(function(t) { t.remove(); });
        }

        this._addSelection(item);
        this.input.value = '';
        this._closeDropdown();

        if (!this.options.multiple) {
            this._updatePlaceholder();
        }

        if (typeof this.options.onSelect === 'function') {
            this.options.onSelect(item);
        }
        if (typeof this.options.onChange === 'function') {
            this.options.onChange(this.getSelectedIds());
        }

        this.input.focus();
    };

    ArcticTypeahead.prototype._addSelection = function(item) {
        this.selected.push(item);

        // Hidden input for form submission
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = this.options.multiple
            ? this.options.name + '[]'
            : this.options.name;
        hidden.value = item.id;
        hidden.setAttribute('data-at-id', item.id);
        this.hiddenContainer.appendChild(hidden);

        // Visual tag
        var tag = document.createElement('span');
        tag.className = 'at-tag';
        tag.setAttribute('data-at-id', item.id);

        var tagText = document.createElement('span');
        tagText.className = 'at-tag-text';
        tagText.textContent = item.name;
        tag.appendChild(tagText);

        if (item.role) {
            var roleLabel = document.createElement('span');
            roleLabel.className = 'at-tag-role';
            roleLabel.textContent = item.role;
            tag.appendChild(roleLabel);
        }

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'at-tag-remove';
        removeBtn.innerHTML = '&times;';
        removeBtn.setAttribute('aria-label', 'Remove ' + item.name);
        var self = this;
        removeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            self._removeSelection(item.id);
        });
        tag.appendChild(removeBtn);

        // Insert tag before the input
        this.tagsArea.insertBefore(tag, this.input);
        this._updatePlaceholder();
    };

    ArcticTypeahead.prototype._removeSelection = function(id) {
        this.selected = this.selected.filter(function(s) { return s.id !== id; });

        // Remove hidden input
        var hidden = this.hiddenContainer.querySelector('[data-at-id="' + id + '"]');
        if (hidden) hidden.remove();

        // Remove tag
        var tag = this.tagsArea.querySelector('.at-tag[data-at-id="' + id + '"]');
        if (tag) tag.remove();

        this._updatePlaceholder();

        if (typeof this.options.onChange === 'function') {
            this.options.onChange(this.getSelectedIds());
        }
    };

    ArcticTypeahead.prototype._updatePlaceholder = function() {
        if (this.selected.length > 0 && !this.options.multiple) {
            this.input.placeholder = this.selected[0].name;
        } else if (this.selected.length > 0 && this.options.multiple) {
            this.input.placeholder = '';
        } else {
            this.input.placeholder = this.options.placeholder;
        }
    };

    ArcticTypeahead.prototype._closeDropdown = function() {
        this.dropdown.style.display = 'none';
        this.isOpen = false;
        this.highlightedIndex = -1;
    };

    ArcticTypeahead.prototype.getSelectedIds = function() {
        return this.selected.map(function(s) { return s.id; });
    };

    ArcticTypeahead.prototype.getSelected = function() {
        return this.selected.slice();
    };

    ArcticTypeahead.prototype.clear = function() {
        this.selected = [];
        this.hiddenContainer.innerHTML = '';
        this.tagsArea.querySelectorAll('.at-tag').forEach(function(t) { t.remove(); });
        this.input.value = '';
        this._updatePlaceholder();
    };

    ArcticTypeahead.prototype.setPreSelected = function(items) {
        this.clear();
        var self = this;
        items.forEach(function(item) {
            self._addSelection(item);
        });
    };

    // Export to global
    window.ArcticTypeahead = ArcticTypeahead;

})(window);
