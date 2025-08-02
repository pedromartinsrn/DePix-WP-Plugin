class DepixP2PsManager {
    constructor() {
        this.container = document.querySelector('.depix-p2ps');
        this.form = document.querySelector('.depix-p2ps-search-form');
        this.searchInput = document.querySelector('input[name="depix_p2ps_search"]');
        this.orderBySelect = document.querySelector('select[name="depix_p2ps_orderby"]');
        this.orderSelect = document.querySelector('select[name="depix_p2ps_order"]');
        this.submitButton = document.querySelector('.depix-p2ps-search-form button');
        this.resultsContainer = null;
        this.originalData = [];
        this.filteredData = [];
        this.searchTimeout = null;

        this.init();
    }

    init() {
        if (!this.container) return;

        this.createResultsContainer();
        this.loadInitialData();
        this.bindEvents();
        this.updateUI();
    }

    createResultsContainer() {
        this.resultsContainer = this.container.querySelector('.depix-p2ps-results');
        if (!this.resultsContainer) {
            this.resultsContainer = document.createElement('div');
            this.resultsContainer.className = 'depix-p2ps-results';
            this.container.appendChild(this.resultsContainer);
        }
    }

    loadInitialData() {
        this.showLoading();
        
        this.fetchP2Ps().then(data => {
            this.originalData = data;
            this.filteredData = [...data];
            this.renderResults();
            this.updateResultsCount();
        }).catch(error => {
            this.showError('Erro ao carregar P2Ps. Tente novamente.');
        });
    }

    async fetchP2Ps(filters = {}) {
        try {
            const formData = new FormData();
            formData.append('action', 'depix_get_p2ps');
            formData.append('nonce', window.depix_ajax?.nonce || '');
            
            Object.keys(filters).forEach(key => {
                formData.append(key, filters[key]);
            });

            const response = await fetch(window.depix_ajax?.ajax_url || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Não foi possível carregar os P2Ps via AJAX');
            }

            const data = await response.json();
            
            if (data.success) {
                return data.data;
            } 

            throw new Error(data.data || 'Erro desconhecido');
            
        } catch (error) {
            
            return await this.fetchP2PsFromJSON();
           
        }
    }


    async fetchP2PsFromJSON() {
        try {
            const formData = new FormData();
            formData.append('action', 'depix_get_p2ps_json');
            formData.append('nonce', window.depix_ajax?.nonce || '');

            const response = await fetch(window.depix_ajax?.ajax_url || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Não foi possível carregar JSON via AJAX');
            }

            const data = await response.json();
            
            if (data.success) {
                return data.data;
            } else {
                throw new Error(data.data || 'Erro ao carregar JSON');
            }
        } catch (error) {
            try {
                const jsonUrl = (window.depix_ajax?.plugin_url || '') + 'src/mock/p2p.json';
                const response = await fetch(jsonUrl);
                
                if (!response.ok) {
                    throw new Error('Não foi possível carregar JSON diretamente');
                }
                
                return await response.json();
            } catch (directError) {
                throw directError;
            }
        }
    }

    bindEvents() {
        if (this.searchInput) {
            this.searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.applyFilters();
                }, 300);
            });
        }

        if (this.orderBySelect) {
            this.orderBySelect.addEventListener('change', () => {
                this.applyFilters();
            });
        }

        if (this.orderSelect) {
            this.orderSelect.addEventListener('change', () => {
                this.applyFilters();
            });
        }

        if (this.form) {
            this.form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.applyFilters();
            });
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.clearFilters();
            }
        });
    }

    applyFilters() {
        const filters = this.getFilters();
        
        this.showLoading();
        this.addFilterActiveStates();

        setTimeout(() => {
            this.filteredData = this.filterData(this.originalData, filters);
            this.renderResults();
            this.updateResultsCount();
        }, 300);
    }

    getFilters() {
        return {
            search: this.searchInput?.value.trim() || '',
            orderby: this.orderBySelect?.value || '',
            order: this.orderSelect?.value || 'asc'
        };
    }

    filterData(data, filters) {
        let filtered = [...data];

        if (filters.search) {
            const searchTerm = filters.search.toLowerCase();
            filtered = filtered.filter(item => 
                item.name.toLowerCase().includes(searchTerm) ||
                item.description.toLowerCase().includes(searchTerm) ||
                item.contact.toLowerCase().includes(searchTerm)
            );
        }

        if (filters.orderby) {
            filtered.sort((a, b) => {
                let valueA, valueB;

                if (filters.orderby === 'minValue') {
                    valueA = this.parsePrice(a.minValue);
                    valueB = this.parsePrice(b.minValue);
                } else if (filters.orderby === 'tax') {
                    valueA = this.parsePercentage(a.tax);
                    valueB = this.parsePercentage(b.tax);
                } else {
                    valueA = a[filters.orderby];
                    valueB = b[filters.orderby];
                }

                if (valueA === valueB) return 0;
                
                const comparison = valueA > valueB ? 1 : -1;
                return filters.order === 'desc' ? -comparison : comparison;
            });
        }

        return filtered;
    }

    parsePrice(priceString) {
        return parseFloat(priceString.replace(/[R$\s.]/g, '').replace(',', '.')) || 0;
    }

    parsePercentage(percentString) {
        return parseFloat(percentString.replace('%', '')) || 0;
    }

    addFilterActiveStates() {
        document.querySelectorAll('.depix-filter-active').forEach(el => {
            el.classList.remove('depix-filter-active');
        });

        if (this.searchInput?.value.trim()) {
            this.searchInput.classList.add('depix-filter-active');
        }

        if (this.orderBySelect?.value) {
            this.orderBySelect.classList.add('depix-filter-active');
        }
    }

    clearFilters() {
        if (this.searchInput) this.searchInput.value = '';
        if (this.orderBySelect) this.orderBySelect.selectedIndex = 0;
        if (this.orderSelect) this.orderSelect.selectedIndex = 0;
        
        this.applyFilters();
    }

    showLoading() {
        if (this.submitButton) {
            this.submitButton.classList.add('loading');
            this.submitButton.disabled = true;
        }

        this.resultsContainer.innerHTML = `
            <div class="depix-p2ps-loading">
                Carregando P2Ps...
            </div>
        `;
    }

    showError(message) {
        this.resultsContainer.innerHTML = `
            <div class="depix-p2ps-error">
                <strong>Erro:</strong> ${message}
            </div>
        `;

        this.hideLoading();
    }

    hideLoading() {
        if (this.submitButton) {
            this.submitButton.classList.remove('loading');
            this.submitButton.disabled = false;
        }
    }

    renderResults() {
        this.hideLoading();

        if (this.filteredData.length === 0) {
            this.resultsContainer.innerHTML = `
                <div class="depix-p2ps-empty">
                    <h3>Nenhum P2P encontrado</h3>
                    <p>Tente ajustar os filtros de busca.</p>
                </div>
            `;
            return;
        }

        const gridHTML = `
            <div class="depix-p2ps-grid">
                ${this.filteredData.map(p2p => this.renderP2PCard(p2p)).join('')}
            </div>
        `;

        this.resultsContainer.innerHTML = gridHTML;
    }

    renderP2PCard(p2p) {
        return `
            <div class="depix-p2p">
                <h3>${this.escapeHtml(p2p.name)}</h3>
                <p>${this.escapeHtml(p2p.description)}</p>
                
                <div class="depix-p2p-info">
                    <div class="depix-p2p-info-item">
                        <span class="depix-p2p-info-label">Taxa</span>
                        <span class="depix-p2p-info-value tax">${this.escapeHtml(p2p.tax)}</span>
                    </div>
                    <div class="depix-p2p-info-item">
                        <span class="depix-p2p-info-label">Valor Mínimo</span>
                        <span class="depix-p2p-info-value min-value">${this.escapeHtml(p2p.minValue)}</span>
                    </div>
                </div>
                
                <p><strong>Contato:</strong> ${this.escapeHtml(p2p.contact)}</p>
                <a href="${this.escapeHtml(p2p.link)}" class="depix-p2p-link" target="_blank" rel="noopener">
                    Ver mais
                </a>
            </div>
        `;
    }

    updateResultsCount() {
        const existingCount = this.container.querySelector('.depix-results-count');
        if (existingCount) {
            existingCount.remove();
        }

        const count = document.createElement('div');
        count.className = 'depix-results-count';
        count.textContent = `${this.filteredData.length} P2P${this.filteredData.length !== 1 ? 's' : ''} encontrado${this.filteredData.length !== 1 ? 's' : ''}`;
        
        this.form.parentNode.appendChild(count);
    }

    updateUI() {
        if (this.container) {
            this.container.classList.add('depix-enhanced');
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new DepixP2PsManager();
});

document.addEventListener('depix:reinit', () => {
    new DepixP2PsManager();
});