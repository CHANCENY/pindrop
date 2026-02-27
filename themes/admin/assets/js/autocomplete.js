/**
 * Universal Autocomplete Component for Commerce Store
 * Provides searchable dropdown functionality for any form field
 * Works with backend API that returns standardized {value, label} format
 */
class Autocomplete {
    constructor(config) {
        this.config = {
            fieldId: config.fieldId || null,
            source: config.source || null,
            dataSource: config.dataSource || null,
            limit: config.limit || 10,
            placeholder: config.placeholder || 'Search...',
            minQueryLength: config.minQueryLength || 2,
            delay: config.delay || 300,
            displayField: config.displayField || 'label',
            valueField: config.valueField || 'value',
            searchFields: config.searchFields || ['name', 'email'],
            onSelect: config.onSelect || null,
            onSearch: config.onSearch || null,
            customTemplate: config.customTemplate || null,
            noResultsText: config.noResultsText || 'No results found',
            loadingText: config.loadingText || 'Loading...',
            cssClass: config.cssClass || 'autocomplete-container',
            ...config
        };

        this.element = null;
        this.input = null;
        this.dropdown = null;
        this.currentResults = [];
        this.selectedItem = null;
        this.isLoading = false;
        this.searchTimeout = null;
        this.lastQuery = '';

        this.init();
    }

    init() {
        if (!this.config.fieldId) {
            console.error('Autocomplete: fieldId is required');
            return;
        }

        this.element = document.getElementById(this.config.fieldId);
        if (!this.element) {
            console.error(`Autocomplete: Element with id '${this.config.fieldId}' not found`);
            return;
        }

        this.setup();
    }

    setup() {
        // Create autocomplete container
        this.createContainer();
        
        // Bind events
        this.bindEvents();
        
        // Set the initial placeholder if provided
        if (this.config.placeholder) {
            this.input.placeholder = this.config.placeholder;
        }

    }

    createContainer() {
        const container = document.createElement('div');
        container.className = this.config.cssClass;
        container.style.position = 'relative';
        container.style.width = '100%';

        // Clone the original element to get its attributes
        const originalElement = this.element;
        const originalType = originalElement.tagName.toLowerCase();

        // Create input element
        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.className = originalElement.className || '';
        this.input.style.width = '100%';
        this.input.autocomplete = 'off';
        
        // Copy attributes from original element
        if (originalElement.attributes && typeof originalElement.attributes === 'object') {
            try {
                Array.from(originalElement.attributes).forEach(attr => {
                    if (attr.name !== 'id' && attr.name !== 'type') {
                        this.input.setAttribute(attr.name, attr.value);
                    }
                });
            } catch (error) {
                console.warn('Could not copy attributes from original element:', error);
            }
        }
        this.input.setAttribute('name', this.config.name);

        // Create dropdown
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'autocomplete-dropdown';
        this.dropdown.style.position = 'absolute';
        this.dropdown.style.top = '100%';
        this.dropdown.style.left = '0';
        this.dropdown.style.right = '0';
        this.dropdown.style.backgroundColor = '#fff';
        this.dropdown.style.border = '1px solid #ddd';
        this.dropdown.style.borderTop = 'none';
        this.dropdown.style.maxHeight = '200px';
        this.dropdown.style.overflowY = 'auto';
        this.dropdown.style.zIndex = '1000';
        this.dropdown.style.display = 'none';

        // Create hidden input to store the selected value
        this.hiddenInput = document.createElement('input');
        this.hiddenInput.type = 'hidden';
        this.hiddenInput.name = originalElement.name || this.config.name;
        this.hiddenInput.id = originalElement.id || this.config.fieldId;

        // Replace original element with autocomplete
        originalElement.parentNode.replaceChild(container, originalElement);
        container.appendChild(this.input);
        container.appendChild(this.dropdown);
      //  container.appendChild(this.hiddenInput);

        // Store reference to an original element for form submission
        this.element = this.hiddenInput;
    }

    bindEvents() {
        // Input events
        this.input.addEventListener('input', this.handleInput.bind(this));
        this.input.addEventListener('focus', this.handleFocus.bind(this));
        this.input.addEventListener('blur', this.handleBlur.bind(this));
        this.input.addEventListener('keydown', this.handleKeydown.bind(this));

        // Click outside to close
        document.addEventListener('click', this.handleDocumentClick.bind(this));
    }

    handleInput(event) {
        const query = event.target.value.trim();

        // Clear timeout
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }

        if (query.length < this.config.minQueryLength) {
            this.hideDropdown();
            return;
        }

        // Set loading state immediately
        this.setLoading(true);
        this.showDropdown(); // Show dropdown with loading state

        // Delay search
        this.searchTimeout = setTimeout(() => {
            this.search(query);
        }, this.config.delay);
    }

    handleFocus(event) {
        const query = event.target.value.trim();
        if (query.length >= this.config.minQueryLength) {
            // Always show dropdown on focus if we have enough characters
            if (this.currentResults.length > 0 && this.lastQuery === query) {
                // Show existing results immediately
                this.displayResults(this.currentResults);
            } else {
                // Search for new results
                this.setLoading(true);
                this.showDropdown();
                this.search(query);
            }
        }
        this.lastQuery = query;
    }

    handleBlur(event) {
        // Delay hiding to allow click on dropdown items
        setTimeout(() => {
            this.hideDropdown();
        }, 200);
    }

    handleKeydown(event) {
        const items = this.dropdown.querySelectorAll('.autocomplete-item');
        
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                this.highlightNext(items);
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.highlightPrevious(items);
                break;
            case 'Enter':
                event.preventDefault();
                const highlighted = this.dropdown.querySelector('.autocomplete-item.highlighted');
                if (highlighted) {
                    this.selectItem(highlighted);
                }
                break;
            case 'Escape':
                this.hideDropdown();
                break;
        }
    }

    handleDocumentClick(event) {
        if (!this.input.contains(event.target) && !this.dropdown.contains(event.target)) {
            this.hideDropdown();
        }
    }

    async search(query) {
        try {
            // Call custom search function if provided
            if (this.config.onSearch) {
                const results = await this.config.onSearch(query, this.config.limit);
                this.displayResults(results);
            } else if (this.config.source) {
                // Use backend autocomplete endpoint
                const response = await fetch(`/internal/autocomplete?source=${encodeURIComponent(this.config.source)}&q=${encodeURIComponent(query)}&limit=${this.config.limit}`);
                if (!response.ok) {
                    throw new Error('Autocomplete request failed');
                }
                const data = await response.json();
                
                // Use standardized response format {results: [{value, label}]}
                const results = data.results || [];
                this.displayResults(results);
            } else {
                // Default: return empty results
                this.displayResults([]);
            }
        } catch (error) {
            console.error('Autocomplete search error:', error);
            this.displayResults([]);
        } finally {
            this.setLoading(false);
        }
    }

    displayResults(results) {
        this.currentResults = results;
        this.dropdown.innerHTML = '';

        // Only show loading if we're actually loading and no results yet
        if (this.isLoading && results.length === 0) {
            this.dropdown.innerHTML = `<div class="autocomplete-loading">${this.config.loadingText}</div>`;
            this.showDropdown();
            return;
        }

        if (results.length === 0) {
            this.dropdown.innerHTML = `<div class="autocomplete-no-results">${this.config.noResultsText}</div>`;
            this.showDropdown();
            return;
        }

        results.forEach((item, index) => {
            const itemElement = this.createItemElement(item, index);
            this.dropdown.appendChild(itemElement);
        });

        this.showDropdown();
    }

    createItemElement(item, index) {
        const div = document.createElement('div');
        div.className = 'autocomplete-item';
        div.setAttribute('data-index', index);

        if (this.config.customTemplate) {
            div.innerHTML = this.config.customTemplate(item);
        } else {
            // Use standardized label field for display
            const displayText = item[this.config.displayField] || item.label || '';
            div.textContent = displayText;
        }

        div.addEventListener('click', () => this.selectItem(div));
        div.addEventListener('mouseenter', () => this.highlightItem(div));

        return div;
    }

    selectItem(itemElement) {
        const index = parseInt(itemElement.getAttribute('data-index'));
        const item = this.currentResults[index];

        if (item) {
            this.selectedItem = item;
            
            // Update input with label for display
            const displayText = item[this.config.displayField] || item.label || '';
            this.input.value = displayText;
            
            // Update hidden input with value for form submission
            const formValue = item[this.config.valueField] || item.value || '';
            this.hiddenInput.value = formValue;

            console.log(this.input, this.hiddenInput)

            // Hide dropdown
            this.hideDropdown();

            // Call custom select handler
            if (this.config.onSelect) {
                this.config.onSelect(item);
            }
        }
    }

    highlightItem(itemElement) {
        // Remove previous highlights
        this.dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
            item.classList.remove('highlighted');
        });
        
        itemElement.classList.add('highlighted');
    }

    highlightNext(items) {
        const highlighted = this.dropdown.querySelector('.autocomplete-item.highlighted');
        let nextIndex = 0;

        if (highlighted) {
            const currentIndex = parseInt(highlighted.getAttribute('data-index'));
            nextIndex = currentIndex + 1;
            
            if (nextIndex >= items.length) {
                nextIndex = 0;
            }
        }

        if (items[nextIndex]) {
            this.highlightItem(items[nextIndex]);
        }
    }

    highlightPrevious(items) {
        const highlighted = this.dropdown.querySelector('.autocomplete-item.highlighted');
        let prevIndex = items.length - 1;

        if (highlighted) {
            const currentIndex = parseInt(highlighted.getAttribute('data-index'));
            prevIndex = currentIndex - 1;
            
            if (prevIndex < 0) {
                prevIndex = items.length - 1;
            }
        }

        if (items[prevIndex]) {
            this.highlightItem(items[prevIndex]);
        }
    }

    setLoading(loading) {
        this.isLoading = loading;
        if (loading) {
            this.input.classList.add('loading');
        } else {
            this.input.classList.remove('loading');
        }
    }

    showDropdown() {
        this.dropdown.style.display = 'block';
    }

    hideDropdown() {
        this.dropdown.style.display = 'none';
    }

    // Public methods
    setValue(value) {
        // Find item by value in current results or set directly
        const item = this.currentResults.find(item => 
            (item[this.config.valueField] || item.value) === value
        );
        
        if (item) {
            this.selectItemByData(item);
        } else {
            // Set value directly if item not found
            this.hiddenInput.value = value;
            this.input.value = value;
        }
    }

    getValue() {
        return this.hiddenInput.value;
    }

    getSelectedItem() {
        return this.selectedItem;
    }

    clear() {
        this.input.value = '';
        this.hiddenInput.value = '';
        this.selectedItem = null;
        this.hideDropdown();
    }

    selectItemByData(item) {
        // Update input with label for display
        const displayText = item[this.config.displayField] || item.label || '';
        this.input.value = displayText;
        
        // Update hidden input with value for form submission
        const formValue = item[this.config.valueField] || item.value || '';
        this.hiddenInput.value = formValue;
        this.selectedItem = item;
    }

    destroy() {
        // Clean up event listeners
        this.input.removeEventListener('input', this.handleInput);
        this.input.removeEventListener('focus', this.handleFocus);
        this.input.removeEventListener('blur', this.handleBlur);
        this.input.removeEventListener('keydown', this.handleKeydown);
        document.removeEventListener('click', this.handleDocumentClick);

        // Clear timeout
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }

        // Remove elements
        const container = this.input.parentNode;
       // container.remove();
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Autocomplete;
}